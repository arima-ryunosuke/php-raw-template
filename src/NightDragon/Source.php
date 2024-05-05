<?php

namespace ryunosuke\NightDragon;

class Source implements \ArrayAccess, \IteratorAggregate, \Countable
{
    const SHORT_OPEN_TAG = '<?pHP ';

    /// ショートタグ互換モード定数
    const SHORT_TAG_NOTHING = 0; // <? に関して何もしない
    const SHORT_TAG_REPLACE = 1; // <? を <?php に読み替えるがパース時のみで書き下しには影響しない
    const SHORT_TAG_REWRITE = 2; // <? を <?php に読み替えてパース時だけでなく書き下しにも影響する

    /// matcher 定数
    const MATCH_ANY       = true; // 任意のトークンにマッチ
    const MATCH_MANY      = -1;   // 指定トークンに量的マッチ
    const MATCH_NOT       = -98;  // 否定フラグ
    const MATCH_NOCAPTURE = -99;  // キャプチャしないフラグ

    private int $compatibleShortTagMode;

    /** @var Token[] */
    private array $tokens = [];

    public function __construct(string|array $eitherCodeOrTokens, int $compatibleShortTagMode = self::SHORT_TAG_NOTHING)
    {
        // php.ini でショートタグが有効なら標準に身を任せてこのクラスでは何もしない
        $this->compatibleShortTagMode = ini_get('short_open_tag') ? self::SHORT_TAG_NOTHING : $compatibleShortTagMode;

        $tokens = is_string($eitherCodeOrTokens) ? Token::tokenize($eitherCodeOrTokens) : array_values($eitherCodeOrTokens);
        foreach ($tokens as $token) {
            // ショートタグ互換ならそのインラインテキストを再パース
            if ($this->compatibleShortTagMode > 0 && $token->is(T_INLINE_HTML)) {
                $inline = str_replace('<?', self::SHORT_OPEN_TAG, $token->text);
                $this->tokens = array_merge($this->tokens, (new self($inline, self::SHORT_TAG_NOTHING))->tokens);
                continue;
            }

            $this->tokens[] = $token;
        }
    }

    public function __toString(): string
    {
        $tokens = $this->tokens;
        if ($this->compatibleShortTagMode === self::SHORT_TAG_REPLACE) {
            $tokens = array_map(function (Token $token) {
                if ($token->is(self::SHORT_OPEN_TAG)) {
                    $token->text = '<?';
                }
                return $token;
            }, $tokens);
        }
        return implode('', array_column($tokens, 'text'));
    }

    private function actualOffset(mixed $offset)
    {
        $n = 0;
        foreach ($this->tokens as $i => $token) {
            if (!$token->is(T_WHITESPACE)) {
                if ($offset === $n++) {
                    return $i;
                }
            }
        }
        return null;
    }

    public function offsetExists(mixed $offset): bool
    {
        $offset = $this->actualOffset($offset);
        return isset($this->tokens[$offset]);
    }

    public function offsetGet(mixed $offset): Token
    {
        $offset = $this->actualOffset($offset);
        return $this->tokens[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offset = $this->actualOffset($offset);
        assert(!($offset !== null && !isset($this->tokens[$offset])));

        if ($offset === null) {
            $this->tokens[] = $value;
        }
        else {
            $this->tokens[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $offset = $this->actualOffset($offset);
        unset($this->tokens[$offset]);
        $this->tokens = array_values($this->tokens);
    }

    /**
     * @return \Generator|\Traversable|Token[] Token[] はコード補完用
     */
    public function getIterator(): \Generator
    {
        $n = 0;
        foreach ($this->tokens as $token) {
            if (!$token->is(T_WHITESPACE)) {
                yield $n++ => $token;
            }
        }
    }

    /**
     * 指定 type の Generator を返す
     *
     * @return Token[] $type に一致したトークン配列
     */
    public function filter(mixed $type): array
    {
        $result = [];
        foreach ($this->tokens as $token) {
            if ($token->is($type)) {
                $result[] = $token;
            }
        }
        return $result;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->tokens as $token) {
            if (!$token->is(T_WHITESPACE)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * トークン先頭から1つ取り出す
     *
     * 取り出したトークンは失われる。
     */
    public function shift(mixed $type = T_WHITESPACE): ?Token
    {
        while ($token = array_shift($this->tokens)) {
            if (!$token->is($type)) {
                return $token;
            }
        }
        return null;
    }

    /**
     * トークン末尾から1つ取り出す
     *
     * 取り出したトークンは失われる。
     */
    public function pop(mixed $type = T_WHITESPACE): ?Token
    {
        while ($token = array_pop($this->tokens)) {
            if (!$token->is($type)) {
                return $token;
            }
        }
        return null;
    }

    /**
     * トークン先頭・末尾から1つ取り出す
     *
     * 取り出したトークンは失われる。

     * @return Token[] トークン配列（[前, 後]）
     */
    public function shrink(mixed $type = T_WHITESPACE): array
    {
        return [
            $this->shift($type),
            $this->pop($type),
        ];
    }

    /**
     * 全ての T_WHITESPACE を除去する
     */
    public function strip(mixed $type = T_WHITESPACE): static
    {
        $this->tokens = array_values(array_filter($this->tokens, fn(Token $token) => !$token->is($type)));
        return $this;
    }

    /**
     * 前後の T_WHITESPACE を除去する
     */
    public function trim(mixed $type = T_WHITESPACE): static
    {
        for ($i = count($this->tokens) - 1; $i >= 0; $i--) {
            if (!$this->tokens[$i]->is($type)) {
                break;
            }
            unset($this->tokens[$i]);
        }

        for ($i = 0; $i < count($this->tokens); $i++) {
            if (!$this->tokens[$i]->is($type)) {
                break;
            }
            unset($this->tokens[$i]);
        }

        $this->tokens = array_values($this->tokens);

        return $this;
    }

    /**
     * 指定トークンで分割してトークン配列の配列で返す
     *
     * @return self[] トークン配列の配列
     */
    public function split(mixed $condition): array
    {
        $parts = array_explode($this->tokens, fn(Token $token) => $token->is($condition));

        $result = [];
        foreach ($parts as $part) {
            $result[] = new self($part, $this->compatibleShortTagMode);
        }
        return $result;
    }

    /**
     * 最初の名前空間宣言の値を返す
     *
     * 他の場所でも結構使うので共通処理としてこのクラスに定義する。
     * 混在していたり複数宣言されていたりはサポートしない。
     */
    public function namespace(): string
    {
        $namespace_parts = $this->match([T_NAMESPACE, Source::MATCH_ANY, Source::MATCH_MANY, ';']);
        if ($namespace_parts) {
            $namespace_parts[0]->shrink();
            return '\\' . $namespace_parts[0]->strip();
        }

        return '';
    }

    /**
     * マッチ条件を指定して部分トークンを得る
     *
     * マッチ条件の仕様は下記（T_WHITESPACE は全てにおいて無視される）。
     *
     * - \Closure: トークンを受け取ってマッチするかを返すクロージャとなる
     * - TOKEN_ID: 指定したトークンIDにマッチする
     * - "TOKEN": 指定したトークン文字列にマッチする
     * - [TOKEN_ID => "TOKEN"]: ID, トークン文字列の AND でマッチする（複数指定すると OR になる）
     * - [TOKEN_ID, "TOKEN"]: ID, トークン文字列の OR でマッチする（複数指定すると OR になる）
     * - MATCH_ANY: 任意のトークンにマッチする
     * - MATCH_MANY: 量的マッチ（自身を含まず次の要素にマッチするまでマッチし続ける）
     *
     * 上記を配列で与え、それが連続してマッチする場合にマッチとみなす。
     * いくつか具体例をあげると下記のようになる。
     *
     * - [T_LNUMBER, '+', T_LNUMBER]: 整数リテラル同士の加算トークンが得られる
     * - [T_LNUMBER, MATCH_ANY, T_LNUMBER]: 整数リテラル同士の何らかの式トークンが得られる（整数で囲まれたなにか、とも言える）
     * - [T_NAMESPACE, MATCH_MANY ';']: namespace 文 ～ ";" までが得られる（つまり名前空間宣言が得られる）
     *
     * @return static[]
     */
    public function match(array $matchers): array
    {
        $result = [];
        $this->scan($matchers, function ($matched) use (&$result) {
            $result[] = new self($matched, $this->compatibleShortTagMode);
        });
        return $result;
    }

    /**
     * マッチ条件と置換トークンを指定してトークンを書き換える
     *
     * マッチ条件の仕様は match と同じ。
     *
     * 置換トークンの仕様は下記。
     *
     * - \Closure: マッチしたトークンを引数にとり、置換結果を返り値として受け取る。後の流れは下記と同じ。
     *   ただし、 false を返したときのみは例外で T_WHITESPACE も含めて一切の置換を行わない。
     * - Token: トークンとしてそのまま使う
     * - string: [-1, string, ...] とした単一トークンとみなす
     * - array:
     *     - [ID, TOKEN, ...] のようなフラット配列: 単一トークンとみなす
     *     - [[ID, TOKEN, ...], [ID, TOKEN, ...], ...] のような階層配列: 複数トークンとみなす
     *     - [[ID, TOKEN, ...], TOKEN, ...] のような混在配列: 上記の複合とみなす
     *
     * @return $this
     */
    public function replace(array $matchers, mixed $replace, ?int $skip = null): static
    {
        return $this->scan($matchers, function ($matched) use ($replace, $skip) {
            if ($replace instanceof \Closure) {
                $replace = $replace(new self($matched, $this->compatibleShortTagMode), $skip);
            }

            if ($replace === false) {
                return 0;
            }

            if ($replace instanceof Source) {
                $replace = $replace->tokens;
            }

            assert(is_string($replace) || is_array($replace) || is_null($replace) || $replace instanceof Token);

            // null は空配列化する（return しないこともあるだろうので利便性のため）
            $replace = $replace ?? [];

            // トークン文字列のみ・[ID, TOKEN] 形式のシンプルなトークン配列は配列化
            if (false
                || is_string($replace)
                || (is_array($replace) && is_int($replace[0] ?? '') && is_string($replace[1] ?? ''))
            ) {
                $replace = [$replace];
            }

            // Token インスタンスの配列に正規化
            $replace = array_each($replace, function (&$carry, $item) {
                $carry = array_merge($carry, $item instanceof Source ? $item->tokens : [Token::instance($item)]);
            }, []);

            $keys = array_keys($matched);
            $min = min($keys);
            $max = max($keys);
            array_splice($this->tokens, $min, $max - $min + 1, $replace);

            if ($skip === null) {
                $skip = count($replace);
            }
            return $skip - 1;
        });
    }

    private function scan(array $matchers, \Closure $callback): static
    {
        $options = [];
        foreach ($matchers as $mindex => $matcher) {
            $option = [
                self::MATCH_MANY      => false,
                self::MATCH_NOCAPTURE => false,
                self::MATCH_NOT       => false,
            ];
            if (is_array($matcher)) {
                foreach ($option as $name => $default) {
                    $option[$name] = $matcher[$name] ?? $default;
                    unset($matcher[$name]);
                }
                $matcher = array_values($matcher);
                if (!$matcher) {
                    $matcher = true;
                }
            }
            elseif ($matcher === self::MATCH_MANY) {
                $option[self::MATCH_MANY] = true;
                $matcher = true;
            }
            $matchers[$mindex] = $matcher;
            $options[$mindex] = $option;
        }

        $match = function ($tindex, $mindex, &$result = []) use (&$match, $matchers, $options) {
            if (!array_key_exists($mindex, $matchers)) {
                return true;
            }
            if (!array_key_exists($tindex, $this->tokens)) {
                return false;
            }

            $matcher = $matchers[$mindex];
            $option = $options[$mindex];
            $token = $this->tokens[$tindex];

            if ($token->is(T_WHITESPACE)) {
                $result[$tindex] = $token;
                return $match($tindex + 1, $mindex, $result);
            }
            elseif ($option[self::MATCH_NOT] xor $token->is($matcher)) {
                // MATCH_MANY は先読み
                if ($option[self::MATCH_MANY]) {
                    for ($n = $tindex; $n < count($this->tokens); $n++) {
                        if (array_key_exists($mindex + 1, $matchers)) {
                            if ($match($n, $mindex + 1)) {
                                break;
                            }
                        }
                        elseif (!$match($n + 1, $mindex)) {
                            $n++;
                            break;
                        }
                    }
                }
                else {
                    $n = $tindex + 1;
                }

                if (!$option[self::MATCH_NOCAPTURE]) {
                    $result += array_slice($this->tokens, $tindex, $n - $tindex, true);
                }
                return $match($n, $mindex + 1, $result);
            }
            return false;
        };

        for ($tindex = 0; $tindex < count($this->tokens); $tindex++) {
            if (!$this->tokens[$tindex]->is(T_WHITESPACE)) {
                $matched = [];
                if ($match($tindex, 0, $matched)) {
                    foreach (array_reverse($matched, true) as $i => $token) {
                        if (!$token->is(T_WHITESPACE)) {
                            break;
                        }
                        unset($matched[$i]);
                    }
                    $tindex += $callback($matched);
                }
            }
        }

        return $this;
    }
}
