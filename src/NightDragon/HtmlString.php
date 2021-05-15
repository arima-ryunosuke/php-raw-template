<?php

namespace ryunosuke\NightDragon;

class HtmlString
{
    private $html;

    public function __construct($string)
    {
        $this->html = $string;
    }

    public function __toString()
    {
        return $this->html;
    }
}
