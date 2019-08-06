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
    const MATCH_ANY   = null;  // like: /./
    const MATCH_MANY0 = false; // like: /.*/
    const MATCH_MANY1 = true;  // like: /.+/

    /** @var int ショートタグ互換モード */
    private $compatibleShortTagMode;

    /** @var Token[] トークン配列 */
    private $tokens = [];

    public function __construct($eitherCodeOrTokens, int $compatibleShortTagMode = self::SHORT_TAG_NOTHING)
    {
        // php.ini でショートタグが有効なら標準に身を任せてこのクラスでは何もしない
        $this->compatibleShortTagMode = ini_get('short_open_tag') ? self::SHORT_TAG_NOTHING : $compatibleShortTagMode;

        $tokens = is_string($eitherCodeOrTokens) ? token_get_all($eitherCodeOrTokens) : array_values($eitherCodeOrTokens);
        foreach ($tokens as $i => $token) {
            // ショートタグ互換ならそのインラインテキストを再パース
            if ($this->compatibleShortTagMode > 0 && is_array($token) && $token[0] === T_INLINE_HTML) {
                $inline = str_replace('<?', self::SHORT_OPEN_TAG, $token[1]);
                $this->tokens = array_merge($this->tokens, (new self($inline, self::SHORT_TAG_NOTHING))->tokens);
                continue;
            }

            // 配列だったり文字列トークンだったりで統一性がないので配列に正規化する
            if (is_string($token)) {
                // 次のトークンが T_WHITESPACE だったら行番号も同じ確率が**高い**（確実ではないので参考程度に）
                $line = null;
                for ($j = $i + 1, $l = count($tokens); $j < $l; $j++) {
                    if (($tokens[$j][0] ?? null) === T_WHITESPACE) {
                        $line = $tokens[$j][2];
                        break;
                    }
                }

                $token = Token::instance(TOKEN::UNKNOWN_ID, $token, $line);
            }
            else {
                $token = Token::instance($token);
            }
            $this->tokens[] = $token;
        }
    }

    public function __toString()
    {
        $tokens = $this->tokens;
        if ($this->compatibleShortTagMode === self::SHORT_TAG_REPLACE) {
            $tokens = array_map(function (Token $token) {
                if ($token->equals(self::SHORT_OPEN_TAG)) {
                    $token->token = '<?';
                }
                return $token;
            }, $tokens);
        }
        return implode('', array_column($tokens, 'token'));
    }

    public function offsetExists($offset)
    {
        return isset($this->tokens[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->tokens[$offset];
    }

    public function offsetSet($offset, $value)
    {
        assert(!($offset !== null && !isset($this->tokens[$offset])));

        if ($offset === null) {
            $this->tokens[] = $value;
        }
        else {
            $this->tokens[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->tokens[$offset]);
        $this->tokens = array_values($this->tokens);
    }

    /**
     * @return \Generator|\Traversable|Token[] Token[] はコード補完用
     */
    public function getIterator()
    {
        foreach ($this->tokens as $i => $token) {
            yield $i => $token;
        }
    }

    public function count()
    {
        return count($this->tokens);
    }

    /**
     * トークン先頭から1つ返す
     *
     * T_WHITESPACE はスキップ。取り出したトークンは失われない。
     *
     * @return Token|null 先頭トークン
     */
    public function first(): ?Token
    {
        for ($i = 0; $i < count($this->tokens); $i++) {
            if (!$this->tokens[$i]->isWhitespace()) {
                return $this->tokens[$i];
            }
        }
        return null;
    }

    /**
     * トークン末尾から1つ返す
     *
     * T_WHITESPACE はスキップ。取り出したトークンは失われない。
     *
     * @return Token|null 末尾トークン
     */
    public function last(): ?Token
    {
        for ($i = count($this->tokens) - 1; $i >= 0; $i--) {
            if (!$this->tokens[$i]->isWhitespace()) {
                return $this->tokens[$i];
            }
        }
        return null;
    }

    /**
     * トークン先頭・末尾から1つ返す
     *
     * T_WHITESPACE はスキップ。取り出したトークンは失われる。
     *
     * @return Token[] トークン配列（[前, 後]）
     */
    public function both()
    {
        return [
            $this->first(),
            $this->last(),
        ];
    }

    /**
     * トークン先頭から1つ取り出す
     *
     * T_WHITESPACE はスキップ。取り出したトークンは失われる。
     *
     * @return Token|null 先頭トークン
     */
    public function shift(): ?Token
    {
        while ($token = array_shift($this->tokens)) {
            if (!$token->isWhitespace()) {
                return $token;
            }
        }
        return null;
    }

    /**
     * トークン末尾から1つ取り出す
     *
     * T_WHITESPACE はスキップ。取り出したトークンは失われる。
     *
     * @return Token|null 末尾トークン
     */
    public function pop(): ?Token
    {
        while ($token = array_pop($this->tokens)) {
            if (!$token->isWhitespace()) {
                return $token;
            }
        }
        return null;
    }

    /**
     * トークン先頭・末尾から1つ取り出す
     *
     * T_WHITESPACE はスキップ。取り出したトークンは失われる。
     *
     * @return Token[] トークン配列（[前, 後]）
     */
    public function shrink()
    {
        return [
            $this->shift(),
            $this->pop(),
        ];
    }

    /**
     * 指定トークンで分割してトークン配列の配列で返す
     *
     * @param mixed $condition 分割条件
     * @return self[] トークン配列の配列
     */
    public function split($condition)
    {
        $parts = array_explode($this->tokens, function (Token $token) use ($condition) { return $token->equals($condition); });

        $result = [];
        foreach ($parts as $part) {
            $result[] = new self($part);
        }
        return $result;
    }

    /**
     * 最初の名前空間宣言の値を返す
     *
     * 他の場所でも結構使うので共通処理としてこのクラスに定義する。
     * 混在していたり複数宣言されていたりはサポートしない。
     *
     * @return string 最初に見つかった名前空間
     */
    public function namespace(): string
    {
        $namespace_parts = $this->match([T_NAMESPACE, Source::MATCH_MANY1, ';']);
        if ($namespace_parts) {
            $namespace_parts[0]->shrink();
            return '\\' . $namespace_parts[0];
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
     * - MATCH_MANY0: 消費しない量的マッチ（自身を含まず次の要素にマッチするまでマッチし続ける）
     * - MATCH_MANY1: 消費する量的マッチ（自身を含んで次の要素にマッチするまでマッチし続ける）
     *
     * 上記を配列で与え、それが連続してマッチする場合にマッチとみなす。
     * いくつか具体例をあげると下記のようになる。
     *
     * - [T_LNUMBER, '+', T_LNUMBER]: 整数リテラル同士の加算トークンが得られる
     * - [T_LNUMBER, MATCH_ANY, T_LNUMBER]: 整数リテラル同士の何らかの式トークンが得られる（整数で囲まれたなにか、とも言える）
     * - [T_NAMESPACE, MATCH_MANY1 ';']: namespace 文 ～ ";" までが得られる（つまり名前空間宣言が得られる）
     * - [T_STRING, MATCH_MANY0 ';']: T_STRING ～ ";" までが得られる（上記と違いその間に何もなくてもマッチする）
     *
     * @param array $matchers マッチ条件
     * @return self[]
     */
    public function match(array $matchers)
    {
        $result = [];
        $this->scan($matchers, function ($matched) use (&$result) {
            $result[] = new self($matched);
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
     * @param array $matchers マッチ条件
     * @param mixed|\Closure $replace 置換トークン・文字列
     * @param int|null $skip 置換後のポインタのオフセット（null 指定で置換後トークンは対象にならない、0 指定で再マッチが行われる）
     * @return $this
     */
    public function replace(array $matchers, $replace, int $skip = null)
    {
        $this->scan($matchers, function ($matched) use ($replace, $skip) {
            if ($replace instanceof \Closure) {
                $replace = $replace(new self($matched), $skip);
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
            $replace = array_map([Token::class, 'instance'], $replace);

            $keys = array_keys($matched);
            $min = min($keys);
            $max = max($keys);
            array_splice($this->tokens, $min, $max - $min + 1, $replace);

            if ($skip === null) {
                $skip = count($replace);
            }
            return $skip - 1;
        });
        return $this;
    }

    private function scan(array $matchers, \Closure $callback)
    {
        // @todo T_WHITESPACE が死ぬほど邪魔でいちいちループしてるのをなんとかしたい

        $next = function (&$tindex, $mindex) use (&$match) {
            for (; $tindex < count($this->tokens); $tindex++) {
                if ($this->tokens[$tindex]->isWhitespace()) {
                    continue;
                }

                $matched = [];
                if ($match($tindex, $mindex, $matched)) {
                    return array_filter($matched, function (Token $token) { return !$token->isWhitespace(); });
                }
            }
        };
        $match = function ($tindex, $mindex, &$result) use (&$next, &$match, $matchers) {
            if (!array_key_exists($mindex, $matchers)) {
                // 一つ前が量的指定ならすべて結合しなればならない
                if (in_array($matchers[$mindex - 1] ?? null, [self::MATCH_MANY0, self::MATCH_MANY1], true)) {
                    $result += array_slice($this->tokens, $tindex, null, true);
                }
                return true;
            }

            $matcher = $matchers[$mindex];
            for ($i = $tindex; $i < count($this->tokens); $i++) {
                $token = $this->tokens[$i];
                if ($token->isWhitespace()) {
                    continue;
                }

                if ($matcher === self::MATCH_ANY || $token->equals($matcher)) {
                    $result[$i] = $token;
                    return $match($i + 1, $mindex + 1, $result);
                }
                elseif ($matcher === self::MATCH_MANY0) {
                    $n = $i;
                    if ($next($n, $mindex + 1)) {
                        $result += array_slice($this->tokens, $i, $n - $i, true);
                        return $match($n, $mindex + 1, $result);
                    }
                }
                elseif ($matcher === self::MATCH_MANY1) {
                    $n = $i + 1;
                    if ($next($n, $mindex + 1)) {
                        $result += array_slice($this->tokens, $i, $n - $i, true);
                        return $match($n, $mindex + 1, $result);
                    }
                }
                break;
            }
            return false;
        };

        for ($start = 0; $matched = $next($start, 0); $start++) {
            $start += $callback($matched);
        }
    }
}
