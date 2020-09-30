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
        $lines = self::$lineMapping[(string) realpath($file)] ?? [];

        // 完全一致するならそれを返せば良い
        if (isset($lines[$line])) {
            return $lines[$line];
        }

        // php コードが複数行ある場合は一致しないが、近傍範囲にズレを加算すれば求められる
        foreach ($lines as $from => $to) {
            if ($from > $line && isset($delta)) {
                return $line + $delta;
            }
            $delta = $to - $from;
        }

        // 書き換えてないとかとんでもなく複雑なコードだとかの場合は本当に確定できない。その場合はそのまま返す
        return $line;
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
        $data = file_get_contents($this->path);
        if ($data === false) {
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
        $froms = [];
        $source = new Source($source, $options['compatibleShortTag'] ? Source::SHORT_TAG_REWRITE : Source::SHORT_TAG_NOTHING);
        foreach ($source->filter([T_OPEN_TAG_WITH_ECHO, T_OPEN_TAG]) as $i => $token) {
            $froms[$i] = $token->line;
        }

        $tags = implode('|', array_keys($options['customTagHandler'] ?? []));
        $source = preg_replace_callback("#<($tags)(.*?)</\\1>#su", function ($m) use ($options) {
            $p = strpos_quoted($m[2], '>');
            $attrstr = substr($m[2], 0, $p);
            $content = substr($m[2], $p + 1);
            $sxe = simplexml_load_string('<attrnode ' . preg_replace_callback('#<\?.*\?>#us', function ($m) {
                    return htmlspecialchars($m[0]);
                }, $attrstr) . '></attrnode>');
            $attrs = [];
            foreach ($sxe->attributes() as $a => $b) {
                $attrs[$a] = (string) $b;
            }
            $result = $options['customTagHandler'][$m[1]]($content, $attrs);
            return $result ?? $m[0];
        }, $source);

        $source = new Source($source, $options['compatibleShortTag'] ? Source::SHORT_TAG_REWRITE : Source::SHORT_TAG_NOTHING);

        foreach ($source->filter([T_OPEN_TAG_WITH_ECHO, T_OPEN_TAG]) as $i => $token) {
            self::$lineMapping[$this->path][$token->line] = $froms[$i];
        }

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
                return [-100, "$var,$key"];
            }
            return "{$var}[$key]";
        }, 0);

        $nullsafe = !!$tokens->match([T_COALESCE]);
        $tokens->replace([-100], function (Source $tokens) use ($getter, $nullsafe) {
            return [-100, ($nullsafe ? '@' : '') . "$getter($tokens)"];
        });
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
