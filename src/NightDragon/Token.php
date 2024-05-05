<?php

namespace ryunosuke\NightDragon;

class Token extends \PhpToken
{
    public static function instance(...$arguments): Token
    {
        assert(count($arguments) > 0);

        if (is_array($arguments[0])) {
            $arguments[0][0] ??= ord($arguments[0][1]);
            return new Token(...$arguments[0]);
        }
        if (is_string($arguments[0])) {
            return new Token(ord($arguments[0]), $arguments[0]);
        }
        if (count($arguments) >= 2) {
            return new Token(...$arguments);
        }
        if ($arguments[0] instanceof Token) {
            return $arguments[0];
        }

        throw new \InvalidArgumentException('invalid');
    }

    public function is($that): bool
    {
        if (is_array($that)) {
            return parent::is($that);
        }
        if (is_bool($that)) {
            return $that;
        }
        if (is_scalar($that)) {
            return parent::is($that);
        }
        if ($that instanceof Token) {
            return $this->id === $that->id && $this->text === $that->text;
        }
        if ($that instanceof \Closure) {
            return $that($this);
        }
        return false;
    }
}
