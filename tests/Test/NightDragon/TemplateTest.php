<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\Renderer;
use ryunosuke\NightDragon\Template;

class TemplateTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_render()
    {
        $renderer = new Renderer([
            'debug'      => true,
            'compileDir' => self::COMPILE_DIR,
        ]);
        $template = new Template($renderer, self::TEMPLATE_DIR . '/simple.phtml');
        $contents = $template->render([
            'int'    => 123,
            'bool'   => true,
            'string' => '<tag>hoge</tag>',
            'array'  => [1, 2, 3],
            'object' => new \Exception('exception', 123),
            'key'    => 'key',
            'nest'   => [
                'key' => [
                    'stdClass' => (object) [
                        'ArrayObject' => new \ArrayObject(['e' => new \Exception('nesting')]),
                    ],
                ]
            ],
        ]);
        $this->assertEquals('&lt;tag&gt;hoge&lt;/tag&gt;
123
1
1, 2, 3
exception
nesting
', $contents);
    }

    function test_content()
    {
        $template = new Template(new Renderer([]), '');

        ob_start();

        $template->begin('blocking1');
        echo 'contents1';
        $template->end();
        $template->block('blocking2', 'contents2');

        $this->assertEquals('contents1contents2', ob_get_clean());
    }

    function test_content_parent()
    {
        $template = new Template(new Renderer([]), '');

        ob_start();

        $parent = $template->extend('');
        $template->begin('blocking1');
        echo 'contents1';
        $template->parent();
        $template->end();
        $template->block('blocking2', 'contents2');

        $parent->block('blocking1', 'parentContent1');
        $parent->block('blocking2', 'parentContent2');

        // parentContent2 は含まれないのが正しい（子で使ってないので）
        $this->assertEquals('contents1parentContent1contents2', ob_get_clean());
    }

    function test_extend()
    {
        $renderer = new Renderer([
            'debug'      => true,
            'compileDir' => self::COMPILE_DIR,
        ]);
        $template = new Template($renderer, self::TEMPLATE_DIR . '/extend3.phtml');
        $contents = $template->render([
            'sitename' => "That's Example",
        ]);
        $this->assertEquals('<html lang="ja">
<head>
    <title>1/2/3 - That&#039;s Example</title>
</head>
<body>
This is 3 body.
This is 1 body.
This is 2 body.
</body>
</html>
', $contents);
    }

    function test_include()
    {
        $renderer = new Renderer([
            'debug'      => true,
            'compileDir' => self::COMPILE_DIR,
        ]);
        $template = new Template($renderer, self::TEMPLATE_DIR . '/container.phtml');
        $contents = $template->render([
            'variable' => "<tag>hoge</tag>",
        ]);
        $this->assertEquals('including
&lt;tag&gt;hoge&lt;/tag&gt;
&lt;tag&gt;hoge&lt;/tag&gt;
&lt;tag&gt;fuga&lt;/tag&gt;
', $contents);
    }
}
