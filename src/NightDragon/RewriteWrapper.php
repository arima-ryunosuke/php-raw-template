<?php

namespace ryunosuke\NightDragon;

class RewriteWrapper
{
    private static $lineMapping = [];

    private $path;
    private $pos;
    private $stat;
    private $data;
    private $options;

    public static function getLineMapping(string $file, int $line)
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

    public static function register(string $scheme)
    {
        if (!in_array($scheme, stream_get_wrappers(), true)) {
            stream_wrapper_register($scheme, __CLASS__);
        }
    }

    public function __construct($options = [])
    {
        $this->options = $options;
    }

    public function stream_open($path)
    {
        // include/require でも使用したいので $context が渡せない。のでクエリパラメータでオプションを受け取る
        $parts = parse_url($path);
        parse_str($parts['query'] ?? '', $this->options);

        $this->path = realpath(substr($parts['path'], 1));
        if ($this->path === false || ($data = file_get_contents($this->path)) === false) {
            return false;
        }

        $this->pos = 0;
        $this->stat = stat($this->path);
        $this->data = (string) $this->rewrite($data, $this->options);
        return true;
    }

    public function stream_stat()
    {
        return $this->stat;
    }

    public function url_stat($path)
    {
        return stat(substr(parse_url($path, PHP_URL_PATH), 1));
    }

    public function stream_read($count)
    {
        $ret = substr($this->data, $this->pos, $count);
        $this->pos += strlen($ret);
        return $ret;
    }

    public function stream_eof()
    {
        return $this->pos >= strlen($this->data);
    }

    private function rewrite(string $source, array $options): Source
    {
        $source = $this->rewriteCustomTag($source, $options['customTagHandler'] ?? [], self::$lineMapping[$this->path]);
        $source = new Source($source, $options['compatibleShortTag'] ? Source::SHORT_TAG_REWRITE : Source::SHORT_TAG_NOTHING);

        $options['defaultNamespace'][] = $source->namespace();

        $source->replace([
            [T_OPEN_TAG_WITH_ECHO, T_OPEN_TAG],
            Source::MATCH_ANY,
            Source::MATCH_MANY,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($options) {
            // <?php タグは絶対に触らない
            $open_tag = substr($tokens[0]->token, 0, 5);
            if ($open_tag === '<?php') {
                return false;
            }

            $filter = $options['defaultFilter'];
            if ($tokens[1]->token === $options['nofilter']) {
                $filter = '';
                unset($tokens[1]);
            }

            $this->rewriteExpand($tokens, $options['varExpander']);
            $this->rewriteAccessKey($tokens, $options['varAccessor'], $options['defaultGetter']);
            $this->rewriteModifier($tokens, $options['varReceiver'], $options['varModifier'], $options['defaultNamespace'], $options['defaultClass']);
            $this->rewriteFilter($tokens, $filter, $options['defaultCloser']);

            // <? タグは 7.4 で非推奨になるので警告が出るようになるが、せっかくプリプロセス的な処理をしてるので置換して警告を抑止する
            if (($open_tag[2] ?? '') !== '=') {
                $tokens[0]->token = '<?php';
            }

            return $tokens;
        });

        return $source;
    }

    private function rewriteCustomTag(string $tokens, array $handlers, &$mappling = null)
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

            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            if (reflect_types(reflect_callable($handler)->getParameters()[0] ?? null)->allows($html)) {
                $replaced = $handler($html) ?? $matched;
            }
            // for compatible
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

    private function rewriteExpand(Source $tokens, string $expander)
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
                    'defaultFilter' => '',
                    'defaultCloser' => '',
                ]));
                $inner->shrink();
                return ['".(', $inner, ')."'];
            });
            return ['"', $tokens, '"'];
        });
    }

    private function rewriteAccessKey(Source $tokens, string $accessor, string $getter)
    {
        // "." の場合は $array.123 のような記述が T_DNUMBER として得られてしまうので特別扱いで対応（"." はデフォルトオプションなので面倒見る）
        if ($accessor === '.') {
            $tokens->replace([T_DNUMBER], function (Source $tokens) {
                [$int, $float] = explode('.', $tokens[0]->token);

                if ($int !== '') {
                    return $tokens;
                }
                return ['.', [T_LNUMBER, $float]];
            });
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
                    [Token::UNKNOWN_ID, '"'],
                    [Token::UNKNOWN_ID, '.'],
                ],
                iterator_to_array($tokens),
                [
                    [Token::UNKNOWN_ID, '.'],
                    [Token::UNKNOWN_ID, '"'],
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
                $id = $var->id === -101 ? -101 : -100;
                return [[$id, $var], [-101, $key]];
            }
            return "{$var}[$key]";
        }, 0);

        if (strlen($getter)) {
            $nullsafe = !!$tokens->match([-101, T_COALESCE]);
            $tokens->replace([
                -100,
                -101,
                Source::MATCH_MANY,
                [Source::MATCH_NOCAPTURE => true, Source::MATCH_NOT => true, -101],
            ], function (Source $tokens) use ($getter, $nullsafe) {
                $var = $tokens->shift();
                $args = implode(',', iterator_to_array($tokens));
                $atmark = $nullsafe ? '@' : '';
                return [-100, "$atmark$getter($var,$args)"];
            });
        }
    }

    private function rewriteModifier(Source $tokens, string $receiver, array $modifiers, array $namespaces, array $classes)
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
                    $parts->replace([T_CURLY_OPEN, $receiver, '}'], function () use ($receiver) {
                        return [
                            [Token::UNKNOWN_ID, '"'],
                            [Token::UNKNOWN_ID, '.'],
                            [T_VARIABLE, $receiver],
                            [Token::UNKNOWN_ID, '.'],
                            [Token::UNKNOWN_ID, '"'],
                        ];
                    });

                    $enclosing = false;
                    foreach ($parts as $token) {
                        if ($token->token === '"') {
                            $enclosing = !$enclosing;
                        }
                        if ($token->equals([T_VARIABLE => $receiver])) {
                            $token->token = $enclosing ? ('".' . $modifier['proxyvar'] . '."') : $modifier['proxyvar'];
                        }
                    }
                    $stmt = sprintf($modifier['template'], $scope, $stmt, $resolve($parts));
                }

                $stmt .= $default === null ? '' : " ?? $default";
            }

            return [$open, $stmt, $close];
        });
    }

    private function rewriteFilter(Source $tokens, string $filter, string $closer)
    {
        $tokens->replace([
            T_OPEN_TAG_WITH_ECHO,
            Source::MATCH_ANY,
            Source::MATCH_MANY,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($filter, $closer) {
            [$open, $close] = $tokens->shrink();
            $tokens->trim();

            $nl = (strlen($closer) && !ctype_graph($close->token)) ? ',' . json_encode($closer) : '';
            $content = $filter ? "$filter($tokens)$nl" : "$tokens$nl";

            return [$open, $content, $close];
        });
    }
}
