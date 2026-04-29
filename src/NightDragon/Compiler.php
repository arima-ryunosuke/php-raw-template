<?php

namespace ryunosuke\NightDragon;

class Compiler
{
    private static $lineMapping = [];

    private array  $options;
    private string $filename;

    public static function getLineMapping(string $file, int $line): array
    {
        $mapping = self::$lineMapping[(string) realpath($file)] ?? [];

        // 完全一致するならそれを返せば良い
        if (isset($mapping[$line])) {
            return range(...$mapping[$line]);
        }

        // php コードが複数行ある場合は一致しないが、近傍範囲にズレを加算すれば求められる
        foreach (array_reverse($mapping, true) as $to => $froms) {
            if ($to < $line) {
                return [$froms[1] - $to + $line];
            }
        }

        // 書き換えてないとかとんでもなく複雑なコードだとかの場合は本当に確定できない。その場合はそのまま返す
        return [$line];
    }

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function compile(string $filename): Source
    {
        $this->filename = realpath($filename);
        return $this->rewrite(file_get_contents($filename), $this->options);
    }

    private function rewrite(string $source, array $options): Source
    {
        $source = $this->rewriteCustomTag($source, $options['customTagHandler'] ?? [], self::$lineMapping[$this->filename ?? '']);

        $sourceOptions = 0;
        if ($options['compatibleShortTag']) {
            $sourceOptions |= Source::COMPATIBLE_SHORT_TAG_REWRITE;
        }
        if (($options['varModifier'][0] ?? null) === '|>' || ($options['varModifier'][1] ?? null) === '&>') {
            $sourceOptions |= Source::COMPATIBLE_PIPE_OPERATOR_REPLACE;
        }
        $source = new Source($source, $sourceOptions);

        $this->rewriteWhitespace($source, $options['stripWhitespace'], self::$lineMapping[$this->filename ?? '']);

        $options['defaultNamespace'][] = $source->namespace();

        $source->replace([
            [T_OPEN_TAG_WITH_ECHO, T_OPEN_TAG],
            Source::MATCH_ANY,
            Source::MATCH_MANY,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($options) {
            // <?php タグは絶対に触らない
            $open_tag = substr($tokens[0]->text, 0, 5);
            if ($open_tag === '<?php') {
                return false;
            }

            $filter = $options['defaultFilter'];
            if ($tokens[1]->text === $options['nofilter']) {
                $filter = '';
                unset($tokens[1]);
            }

            $this->rewriteExpand($tokens, $options['varExpander']);
            $this->rewriteAccessKey($tokens, $options['varAccessor'], $options['defaultGetter']);
            $this->rewriteModifier($tokens, $options['varReceiver'], $options['varModifier'], $options['defaultNamespace'], $options['defaultClass']);
            $this->rewriteFilter($tokens, $filter, $options['defaultCloser']);

            // <? タグは 7.4 で非推奨になるので警告が出るようになるが、せっかくプリプロセス的な処理をしてるので置換して警告を抑止する
            if (($open_tag[2] ?? '') !== '=') {
                $tokens[0]->text = '<?php';
            }

            return $tokens;
        });

        return $source;
    }

    private function rewriteCustomTag(string $tokens, array $handlers, &$mappling = null): string
    {
        $line_count = function ($subject) {
            preg_match_all('#\R#u', $subject, $m, PREG_SET_ORDER);
            return count($m);
        };

        $last = 0;
        $mappling ??= [];
        $tags = implode('|', array_keys($handlers ?? []));
        return preg_replace_callback("#<($tags)(.*?)</\\1>#su", function ($m) use ($tokens, $handlers, &$mappling, &$last, $line_count) {
            [$matched, $offset] = $m[0];
            $html = new HtmlObject($matched);
            $handler = $handlers[$html->tagname()];

            if (reflect_types(reflect_callable($handler)->getParameters()[0] ?? null)->allows($html)) {
                $replaced = $handler($html) ?? $matched;
            }
            else {
                $replaced = $handler($html->contents(), $html->attributes()) ?? $matched;
            }

            $matched_len = $line_count($matched);
            $replaced_len = $line_count($replaced);
            $begin = $line_count(substr($tokens, 0, $offset)) + 1;
            $end = $begin + $matched_len;
            foreach (range(0, $replaced_len) as $n) {
                $mappling[$begin + $last + $n] = [$begin, $end];
            }
            $last += $replaced_len - $matched_len;

            return $replaced;
        }, $tokens, -1, $count, PREG_OFFSET_CAPTURE);
    }

    private function rewriteWhitespace(Source $tokens, string $mode, &$mappling = null)
    {
        $mappling ??= [];

        $line_count = function ($subject) {
            preg_match_all('#\R#u', $subject, $m, PREG_SET_ORDER);
            return count($m);
        };

        if ($mode === 'phpize') {
            // 現状ではファイル冒頭をひっかける術がないので強制的に突っ込む（こうしないと <?php から始まるファイルが引っ掛からない）
            $tokens->unshift(T_INLINE_HTML, '');

            $tokens->replace([
                T_INLINE_HTML,
                T_OPEN_TAG,
                Source::MATCH_MANY,
                T_CLOSE_TAG,
                T_INLINE_HTML,
            ], function (Source $tokens) use ($line_count) {
                [$prefix, $suffix] = $tokens->shrink();
                [$open, $close] = $tokens->shrink();

                $prefixes = [];
                $prefixes[] = $prefix;
                if (preg_match('#([\s\r\n]+)\z#u', $prefix->text, $matches)) {
                    $prefixes[] = [T_OPEN_TAG, '<?php', $prefix->line];
                    $prefixes[] = [T_WHITESPACE, $matches[1], $prefix->line];
                    $prefixes[] = [T_CLOSE_TAG, '?>', $prefix->line + $line_count($prefix->text)];
                    $prefix->text = rtrim($prefix->text);
                }

                $suffixes = [];
                if (preg_match('#\A([\s\r\n]+)#u', substr($close->text, 2) . $suffix->text, $matches)) {
                    $suffixes[] = [T_OPEN_TAG, '<?php', $suffix->line];
                    $suffixes[] = [T_WHITESPACE, $matches[1], $suffix->line];
                    $suffixes[] = [T_CLOSE_TAG, '?>', $suffix->line + $line_count($suffix->text)];
                    $suffix->text = ltrim($suffix->text);
                    $close->text = rtrim($close->text);
                }
                $suffixes[] = $suffix;

                return [...$prefixes, $open, $tokens, $close, ...$suffixes];
            }, -1);
        }
    }

    private function rewriteExpand(Source $tokens, string $expander): void
    {
        $tokens->replace([
            $expander,
            Source::MATCH_MANY,
            $expander,
        ], function (Source $tokens) {
            $tokens->shrink();
            $tokens->replace([
                T_DOLLAR_OPEN_CURLY_BRACES,
                Source::MATCH_MANY,
                '}',
            ], function ($curlys) {
                $curlys->shrink();
                $inner = $this->rewrite("<?=$curlys?>", array_replace($this->options, [
                    'defaultFilter'   => '',
                    'defaultCloser'   => '',
                    'stripWhitespace' => '',
                ]));
                $inner->shrink();
                return ['".(', $inner, ')."'];
            });
            return ['"', $tokens, '"'];
        });
    }

    private function rewriteAccessKey(Source $tokens, string $accessor, string $getter): void
    {
        // "." の場合は $array.123 のような記述が T_DNUMBER として得られてしまうので特別扱いで対応（"." はしばしばアクセス子として選ばれる）
        if ($accessor === '.') {
            $tokens->replace([T_DNUMBER], function (Source $tokens) {
                [$int, $float] = explode('.', $tokens[0]->text);

                if ($int !== '') {
                    return $tokens;
                }
                return ['.', [T_LNUMBER, $float]];
            });
        }
        // "->" の場合もコンビである "?->" を使いたいことがあるので特別扱い
        if ($accessor === '->') {
            $accessor = ['->', '?->'];
        }

        // isset/empry は言語構造であり、関数ベースで置換することはできない。しかしそれぞれ
        // isset: エラーの出ない `!is_null`
        // empty: エラーの出ない `!boolval`
        // と読み替えることは可能
        // （isset は複数値が指定できるがそれはどうしようもない。もっと言うと empty の boolval は不要だが明示的な意味で付与している）
        $tokens->replace([
            [T_ISSET, T_EMPTY],
        ], function (Source $tokens) {
            if ($tokens->match([T_ISSET])) {
                return ['!@is_null'];
            }
            if ($tokens->match([T_EMPTY])) {
                return ['!@boolval'];
            }
        });

        $tokens->replace([
            T_CURLY_OPEN,
            T_VARIABLE,
            $accessor,
            Source::MATCH_MANY,
            '}',
        ], function (Source $tokens) {
            $tokens->shrink();
            return array_merge(
                [
                    [null, '{'],
                    [null, '$this->E'],
                    [null, '('],
                ],
                iterator_to_array($tokens),
                [
                    [null, ')'],
                    [null, '}'],
                ]
            );
        });

        $tokens->replace([
            [Source::MATCH_NOT => true, '"'],
            $accessor,
            [T_STRING, T_LNUMBER, T_VARIABLE],
            [Source::MATCH_NOCAPTURE => true, Source::MATCH_NOT => true, '('],
        ], function (Source $tokens) use ($getter) {
            [$var, $key] = $tokens->shrink();

            if ($key->id !== T_VARIABLE) {
                $key = var_export("$key", true);
            }
            if (strlen($getter)) {
                $id = in_array($var->id, [-101, -102], true) ? $var->id : -100;
                return [[$id, $var], [-101, "$tokens"], [-102, $key]];
            }
            return "{$var}[$key]";
        }, 0);

        if (strlen($getter)) {
            $coalesce = !!$tokens->match([-102, T_COALESCE]);
            $tokens->replace([
                -100,
                -101,
                -102,
                Source::MATCH_MANY,
                [Source::MATCH_NOCAPTURE => true, Source::MATCH_NOT => true, -101, -102],
            ], function (Source $tokens) use ($getter, $coalesce) {
                $var = $tokens->shift();
                $args = [];
                foreach ($tokens as $n => $token) {
                    if ($n % 2 === 0) {
                        $args[] = [var_export($token->text === '?->', true)];
                    }
                    else {
                        $args[array_key_last($args)][] = $token->text;
                    }
                }
                $args = implode(',', array_map(fn($arg) => '[' . implode(',', $arg) . ']', $args));
                $atmark = $coalesce ? '@' : '';
                return [-100, "$atmark$getter($var,$args)"];
            });
        }
    }

    private function rewriteModifier(Source $tokens, string $receiver, array $modifiers, array $namespaces, array $classes): void
    {
        // @todo <?= ～ > でざっくりやってるのでもっと局所的に当てていくほうが良い
        // @todo というか普通に汚すぎるのでリファクタ対象（1.2.2 で何してるか分からなかった）

        $resolve = function (string $stmt) use ($namespaces, $classes) {
            if ($stmt[0] !== '\\') {
                $stmtstr = strstr($stmt, '(', true) ?: $stmt;
                foreach ($classes as $class) {
                    if (method_exists($class, $stmtstr)) {
                        return '\\' . ltrim($class, '\\') . '::' . $stmt;
                    }
                }
                foreach ($namespaces as $namespace) {
                    if (function_exists("$namespace\\$stmtstr")) {
                        return concat($namespace, '\\') . $stmt;
                    }
                }
            }
            return $stmt;
        };

        $tokens->replace([
            T_OPEN_TAG_WITH_ECHO,
            Source::MATCH_ANY,
            Source::MATCH_MANY,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($receiver, $modifiers, $resolve) {
            [$open, $close] = $tokens->shrink();
            $tokens->trim();

            $scope = '${"\\0"}';
            $positions = $tokens->filter($modifiers);
            $sources = $tokens->split($modifiers);
            $stmt = trim((string) array_shift($sources));
            foreach ($sources as $n => $parts) {
                $parts->strip();
                $modifiermap = [
                    $modifiers[0] => [
                        'proxyvar' => $stmt,
                        'template' => '%3$s',
                    ],
                    $modifiers[1] => [
                        'proxyvar' => $scope,
                        'template' => '((%1$s=%2$s) === null ? %1$s : %3$s)',
                    ],
                ];
                $modifier = $modifiermap[(string) $positions[$n]];

                $default = null;
                if ($parts->match([T_COALESCE])) {
                    [$parts, $default] = $parts->split(T_COALESCE);
                }

                // () がないなら単純呼び出し
                if (!$parts->match(['('])) {
                    $stmt = sprintf($modifier['template'], $scope, $stmt, $resolve("$parts") . "({$modifier['proxyvar']})");
                }
                // () があるがレシーバ変数がないなら第1引数に適用
                elseif (!$parts->match([$receiver])) {
                    $stmt = sprintf($modifier['template'], $scope, $stmt, $resolve($parts->replace(['('], "({$modifier['proxyvar']},")));
                }
                // () があるしレシーバ変数もあるならその箇所に適用
                else {
                    // 事前準備として "{$_}" を "" . $_ . "" にバラす
                    $receivevar = str_starts_with($receiver, '$') ? [$receiver] : ['$', $receiver];
                    $parts->replace([T_CURLY_OPEN, ...$receivevar, '}'], function () use ($receiver) {
                        return [
                            [null, '"'],
                            [null, '.'],
                            [T_VARIABLE, $receiver],
                            [null, '.'],
                            [null, '"'],
                        ];
                    });

                    $enclosing = false;
                    foreach ($parts as $token) {
                        if ($token->text === '"') {
                            $enclosing = !$enclosing;
                        }
                        if ($token->is([T_VARIABLE => $receiver])) {
                            $token->text = $enclosing ? ('".' . $modifier['proxyvar'] . '."') : $modifier['proxyvar'];
                        }
                    }
                    $stmt = sprintf($modifier['template'], $scope, $stmt, $resolve($parts));
                }

                $stmt .= $default === null ? '' : " ?? $default";
            }

            return [$open, $stmt, $close];
        });
    }

    private function rewriteFilter(Source $tokens, string $filter, string $closer): void
    {
        $tokens->replace([
            T_OPEN_TAG_WITH_ECHO,
            Source::MATCH_ANY,
            Source::MATCH_MANY,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($filter, $closer) {
            [$open, $close] = $tokens->shrink();
            $tokens->trim();

            $nl = (strlen($closer) && !ctype_graph($close->text)) ? ',' . json_encode($closer) : '';
            $content = $filter ? "$filter($tokens)$nl" : "$tokens$nl";

            return [$open, $content, $close];
        });
    }
}
