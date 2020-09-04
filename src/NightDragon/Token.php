<?php

namespace ryunosuke\NightDragon;

class Token
{
    const UNKNOWN_ID = -1;

    /** @var int トークン ID */
    public $id;

    /** @var string トークン文字列 */
    public $token;

    /** @var int 行番号 */
    public $line;

    /** @var string トークン名 */
    public $name;

    public static function instance(...$arguments): Token
    {
        assert(count($arguments) > 0);

        if (is_array($arguments[0])) {
            return new Token(...$arguments[0]);
        }
        if (is_string($arguments[0])) {
            return new Token(self::UNKNOWN_ID, $arguments[0]);
        }
        if (count($arguments) >= 2) {
            return new Token(...$arguments);
        }
        if ($arguments[0] instanceof Token) {
            return $arguments[0];
        }

        throw new \InvalidArgumentException('invalid');
    }

    private function __construct(int $id, string $token, int $line = null)
    {
        $this->id = $id;
        $this->token = $token;
        $this->line = $line;
        $this->name = token_name($id);
    }

    public function __toString()
    {
        return $this->token;
    }

    public function equals($that): bool
    {
        if (is_array($that)) {
            if (is_hasharray($that)) {
                foreach ($that as $id => $token) {
                    if ($this->id === $id && $this->token === $token) {
                        return true;
                    }
                }
            }
            else {
                foreach ($that as $e) {
                    if ($this->id === $e || $this->token === $e) {
                        return true;
                    }
                }
            }
            return false;
        }
        if (is_bool($that)) {
            return $that;
        }
        if (is_scalar($that)) {
            return $this->id === $that || $this->token === $that;
        }
        if ($that instanceof Token) {
            return $this->id === $that->id && $this->token === $that->token;
        }
        if ($that instanceof \Closure) {
            return $that($this);
        }
        return false;
    }

    public function isWhitespace(): bool
    {
        return $this->id === T_WHITESPACE;
    }
}
