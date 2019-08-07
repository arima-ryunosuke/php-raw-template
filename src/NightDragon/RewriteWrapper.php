<?php

namespace ryunosuke\NightDragon;

class RewriteWrapper
{
    private $pos;
    private $stat;
    private $data;

    public static function register(string $scheme)
    {
        if (!in_array($scheme, stream_get_wrappers(), true)) {
            stream_wrapper_register($scheme, __CLASS__);
        }
    }

    public function stream_open($path)
    {
        // include/require でも使用したいので $context が渡せない。のでクエリパラメータでオプションを受け取る
        $parts = parse_url($path);
        parse_str($parts['query'] ?? '', $option);

        $path = substr($parts['path'], 1);
        $data = file_get_contents($path);
        if ($data === false) {
            return false;
        }

        $this->pos = 0;
        $this->stat = stat($path);
        $this->data = $this->rewrite($data, $option);
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

    private function rewrite(string $source, array $options): string
    {
        $source = new Source($source, $options['compatibleShortTag'] ? Source::SHORT_TAG_REWRITE : Source::SHORT_TAG_NOTHING);

        $options['defaultNamespace'][] = $source->namespace();

        $source->replace([
            [T_OPEN_TAG_WITH_ECHO, T_OPEN_TAG],
            Source::MATCH_MANY1,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($options) {
            // <?php タグは絶対に触らない
            $open_tag = substr($tokens[0]->token, 0, 5);
            if ($open_tag === '<?php') {
                return false;
            }

            $this->rewriteAccessKey($tokens, $options['varAccessor'], $options['defaultGetter']);
            $this->rewriteModifier($tokens, $options['varReceiver'], $options['varModifier'], $options['defaultNamespace']);
            $this->rewriteFilter($tokens, $options['defaultFilter'], $options['defaultCloser']);

            // <? タグは 7.4 で非推奨になるので警告が出るようになるが、せっかくプリプロセス的な処理をしてるので置換して警告を抑止する
            if (($open_tag[2] ?? '') !== '=') {
                $tokens[0]->token = '<?php ';
            }

            return $tokens;
        });

        return (string) $source;
    }

    private function rewriteAccessKey(Source $tokens, string $accessor, string $getter)
    {
        // "." の場合は $array.123 のような記述が T_DNUMBER として得られてしまうので特別扱いで対応（"." はデフォルトオプションなので面倒見る）
        if ($accessor === '.') {
            $tokens->replace([T_DNUMBER], function (Source $tokens) {
                list($int, $float) = explode('.', $tokens[0]->token);

                if ($int !== '') {
                    return $tokens;
                }
                return ['.', [T_LNUMBER, $float]];
            });
        }

        $tokens->replace([
            Source::MATCH_ANY,
            $accessor,
            [T_STRING, T_LNUMBER, T_VARIABLE],
        ], function (Source $tokens) use ($getter) {
            list($var, $key) = $tokens->shrink();

            if ($key->id !== T_VARIABLE) {
                $key = var_export("$key", true);
            }
            if (strlen($getter)) {
                return "$getter($var,$key)";
            }
            return "{$var}[$key]";
        }, 0);
    }

    private function rewriteModifier(Source $tokens, string $receiver, string $modifier, array $namespaces)
    {
        // @todo <?= ～ > でざっくりやってるのでもっと局所的に当てていくほうが良い

        $tokens->replace([
            T_OPEN_TAG_WITH_ECHO,
            Source::MATCH_MANY0,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($receiver, $modifier, $namespaces) {
            list($open, $close) = $tokens->shrink();

            $sources = $tokens->split($modifier);
            $stmt = (string) array_shift($sources);
            foreach ($sources as $parts) {
                // () がないなら単純呼び出し
                if (!$parts->match(['('])) {
                    $stmt = $parts . "($stmt)";
                }
                // () があるがレシーバ変数がないなら第1引数に適用
                elseif (!$parts->match([$receiver])) {
                    $stmt = (string) $parts->replace(['('], "($stmt,");
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
                            $token->token = $enclosing ? ('".' . $stmt . '."') : $stmt;
                        }
                    }
                    $stmt = (string) $parts;
                }

                if ($stmt[0] !== '\\') {
                    foreach ($namespaces as $namespace) {
                        if (function_exists("$namespace\\" . strstr($stmt, '(', true))) {
                            $stmt = concat($namespace, '\\') . $stmt;
                            break;
                        }
                    }
                }
            }

            return [$open, $stmt, $close];
        });
    }

    private function rewriteFilter(Source $tokens, string $filter, string $closer)
    {
        $tokens->replace([
            T_OPEN_TAG_WITH_ECHO,
            Source::MATCH_MANY0,
            T_CLOSE_TAG,
        ], function (Source $tokens) use ($filter, $closer) {
            list($open, $close) = $tokens->shrink();

            $nl = (strlen($closer) && !ctype_graph($close->token)) ? ',' . json_encode($closer) : '';

            return [$open, "$filter($tokens)$nl", $close];
        });
    }
}
