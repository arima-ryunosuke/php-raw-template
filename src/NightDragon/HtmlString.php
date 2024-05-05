<?php

namespace ryunosuke\NightDragon;

class HtmlString implements \Stringable
{
    public function __construct(private string $html) { }

    public function __toString(): string
    {
        return $this->html;
    }
}
