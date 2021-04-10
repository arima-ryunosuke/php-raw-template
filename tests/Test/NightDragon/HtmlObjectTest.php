<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\HtmlObject;

class HtmlObjectTest extends \ryunosuke\Test\AbstractTestCase
{
    function test___construct()
    {
        @new HtmlObject('<a>bold</b>');
        $this->assertStringStartsWith('76: Unexpected end tag', error_get_last()['message']);
    }

    function test___toString()
    {
        $html = new HtmlObject('<x-button>text</x-button>');
        $this->assertEquals('<x-button>text</x-button>', (string) $html);

        $html = new HtmlObject('<x-button attr1=" a b " flag attr3 = \' c d \'>te<a>A</a>xt</x-button>');
        $this->assertEquals('<x-button attr1=" a b " flag attr3=" c d ">te<a>A</a>xt</x-button>', (string) $html);

        $html = new HtmlObject('<x-button <?= $attrstring ?> attr1="<? "pre{$hoge}fix" ?>" attr2="<? implode(" ", array_filter($fuga)) ?>"><? some_filter($piyo) ?></x-button>');
        $this->assertEquals('<x-button <?= $attrstring ?> attr1="<? "pre{$hoge}fix" ?>" attr2="<? implode(" ", array_filter($fuga)) ?>"><? some_filter($piyo) ?></x-button>', (string) $html);
    }

    function test_ArrayAccess()
    {
        $html = new HtmlObject('<a>link</a>');
        $this->assertEquals('<a>link</a>', (string) $html);

        $this->assertFalse(isset($html['flag']));
        $html['flag'] = null;
        $this->assertFalse(isset($html['flag']));
        $html['flag'] = 'hoge';
        $this->assertTrue(isset($html['flag']));
        $this->assertEquals('hoge', $html['flag']);
        unset($html['flag']);
        $this->assertFalse(isset($html['flag']));

        $html['flag'] = true;
        $this->assertEquals('<a flag>link</a>', (string) $html);

        $html['flag'] = '';
        $this->assertEquals('<a flag="">link</a>', (string) $html);

        $html['flag'] = false;
        $this->assertEquals('<a>link</a>', (string) $html);

        $html['flag'] = false;
        $this->assertEquals('<a>link</a>', (string) $html);
    }

    function test_tagname()
    {
        $html = new HtmlObject('<a>link</a>');
        $this->assertEquals('a', $html->tagname());
        $this->assertEquals('b', $html->tagname('b'));
        $this->assertEquals('b', $html->tagname());
    }
}
