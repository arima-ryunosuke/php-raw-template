<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\HtmlString;

class HtmlStringTest extends \ryunosuke\Test\AbstractTestCase
{
    function test()
    {
        $html = new HtmlString('<b>bold</b>');
        $this->assertEquals('<b>bold</b>', (string) $html);
    }
}
