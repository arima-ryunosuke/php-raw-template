<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\Renderer;

class RendererTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_html()
    {
        $this->assertEquals('&lt;&#039;&gt;', Renderer::html("<'>"));
        $this->assertEquals('&lt;&#039;&gt;', Renderer::html("<", "'", ">"));
    }

    function test_access()
    {
        $this->assertEquals('X', Renderer::access(['x' => 'X'], 'x'));
        $this->assertEquals('X', Renderer::access((object) ['x' => 'X'], 'x'));
        $this->assertEquals('X', Renderer::access(new \ArrayObject(['x' => 'X']), 'x'));
    }

    function test_strip()
    {
        $this->assertEquals('hello world', Renderer::strip(' hello world '));
        $this->assertEquals('あいうえお', Renderer::strip(' あいうえお '));
        $this->assertEquals('<div><span class="x y z">hello world</span></div>', Renderer::strip('
<div>
    <span
      class="x y z"
      >
      hello world
    </span>
</div>
', ['placeholder' => '']));

        $this->assertEquals('test nightdragonboundary<div><strong id="strong1" class="hoge fuga piyo"><? $multiline ?><br><?php foreach($array as $k=>$v) {
            echo $k, $v;
        }
        ?></strong><pre>
      line1
        line2
          line3
    </pre><textarea>
      line1
        line2
          line3
    </textarea><script>
      var a = 0;
      if (a >= 0)
        alert(a);
    </script><strong id="strong2" class="hoge fuga piyo"><span>asd</span>line1 line2 line3</strong></div>', Renderer::strip('

test
nightdragonboundary
<div>
    <strong
        id   = "strong1"
        class= "hoge fuga piyo"
    >
        <? $multiline ?>
        <br>
        <?php foreach($array as $k=>$v) {
            echo $k, $v;
        }
        ?>
    </strong>
    <pre>
      line1
        line2
          line3
    </pre>
    <textarea>
      line1
        line2
          line3
    </textarea>
    <script>
      var a = 0;
      if (a >= 0)
        alert(a);
    </script>
    <strong
        id=\'strong2\'
        class="hoge fuga piyo"
    >
    
    <span>
    asd
</span>
        line1
line2

line3
    </strong>
</div>

', ['placeholder' => '']));

        $this->assertEquals('<div><s>content</s></div>', @Renderer::strip('<div><s>content</div>', ['noerror' => false]));
        $this->assertEquals("Opening and ending tag mismatch: div and s", trim(error_get_last()['message']));
    }

    function test___destruct()
    {
        @unlink(self::COMPILE_DIR . '/defined.php');

        $renderer = new Renderer([
            'debug'         => true,
            'constFilename' => self::COMPILE_DIR . '/defined.php',
        ]);
        $renderer->compile(self::TEMPLATE_DIR . '/dummy.phtml', []);
        $renderer->__destruct();

        $this->assertFileExists(self::COMPILE_DIR . '/defined.php');
    }

    function test_compile()
    {
        $renderer = new Renderer([
            'debug'      => true,
            'compileDir' => self::COMPILE_DIR,
        ]);
        $fileid = $renderer->compile(realpath(self::TEMPLATE_DIR . '/dummy.phtml'), []);

        // キャッシュが使用される
        $this->assertEquals($fileid, $renderer->compile(realpath(self::TEMPLATE_DIR . '/dummy.phtml'), []));

        // 存在しないと例外が飛ぶ
        $this->assertException('notfound-file is not readable', [$renderer, 'compile'], 'notfound-file', []);
    }

    function test_compile_gather()
    {
        $VARS = [
            'int'    => 123,
            'bool'   => true,
            'string' => 'hoge',
            'array'  => ['x' => 'X', 'y' => 'Y', 'z' => 'Z'],
            'object' => new \Exception('exception', 123),
        ];

        $renderer = new Renderer([
            'debug'        => true,
            'compileDir'   => self::COMPILE_DIR,
            'defaultClass' => \template\T::class
        ]);

        // 全メタ情報が埋め込まれる
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertContains("define('x', 'x')", $template);
        $this->assertContains("define('strtoupper', \\strtoupper(...[]))", $template);
        $this->assertContains('@var integer $int', $template);
        $this->assertContains('@var boolean $bool', $template);
        $this->assertContains('@var string $string', $template);
        $this->assertContains('@var string[] $array', $template);
        $this->assertContains('@var \Exception $object', $template);

        // 名前空間も問題ない
        $renderer->compile(self::TEMPLATE_DIR . '/namespace.phtml', []);
        $template = file_get_contents(self::TEMPLATE_DIR . '/namespace.phtml');
        $this->assertContains("define('globaled', \\globaled(...[]))", $template);
        $this->assertContains("define('spaced', \\template\\spaced(...[]))", $template);
        $this->assertContains("define('fully\\\\qualified', \\fully\\qualified(...[]))", $template);
        $this->assertContains("define('method', \\template\\T::method(...[]))", $template);
        $this->assertNotContains("define('ucwords", $template); // 引数付きは埋め込まれない

        $renderer = new Renderer([
            'debug'          => true,
            'gatherVariable' => true,
            'gatherModifier' => false,
            'gatherAccessor' => false,
            'compileDir'     => self::COMPILE_DIR,
        ]);

        // 指定した Variable だけのメタ情報が埋め込まれている
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertNotContains("define('x', 'x')", $template);
        $this->assertNotContains("define('strtoupper', strtoupper(...[]))", $template);
        $this->assertContains('@var integer $int', $template);
        $this->assertContains('@var boolean $bool', $template);
        $this->assertContains('@var string $string', $template);
        $this->assertContains('@var string[] $array', $template);
        $this->assertContains('@var \Exception $object', $template);
    }

    function test_compile_dir()
    {
        \ryunosuke\NightDragon\rm_rf(self::COMPILE_DIR);
        clearstatcache();
        $this->assertFileNotExists(self::COMPILE_DIR);

        $renderer = new Renderer([
            'debug'      => false,
            'compileDir' => self::COMPILE_DIR,
        ]);
        $this->assertEquals("path1path2", $renderer->render(self::TEMPLATE_DIR . '/path.phtml'));

        $this->assertFileExists(self::COMPILE_DIR);
    }

    function test_gatherVariable()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);

        /** @see Renderer::gatherVariable() */
        $detectType = $this->publishMethod($renderer, 'detectType');

        // シンプルな奴ら
        $this->assertEquals('integer', $detectType(123));
        $this->assertEquals('double', $detectType(3.14));
        $this->assertEquals('boolean', $detectType(false));
        $this->assertEquals('string', $detectType('string'));
        $this->assertEquals('resource', $detectType(STDOUT));
        $this->assertEquals('array', $detectType([]));
        $this->assertEquals('\\Exception', $detectType(new \Exception()));

        // 素の匿名クラスは object
        $this->assertEquals('object', $detectType(new class {
        }));
        // 継承していばそいつ
        $this->assertEquals('\\stdClass', $detectType(new class extends \stdClass {
        }));
        // 実装していばそいつ
        $this->assertEquals('\\Countable', $detectType(new class implements \Countable {
            public function count() { }
        }));
        // 継承も実装もしてれば | で両方
        $this->assertEquals('\\stdClass|\\Countable', $detectType(new class extends \stdClass implements \Countable {
            public function count() { }
        }));
        // ただし継承元に実装メソッドが含まれている場合は含まれない
        $this->assertEquals('\\ArrayObject|\\JsonSerializable', $detectType(new class extends \ArrayObject implements \Countable, \JsonSerializable {
            public function jsonSerialize() { }
        }));

        // 配列系のシンプルな奴ら
        $this->assertEquals('array', $detectType([1, 'a', null]));
        // 配列の配列系
        $this->assertEquals('string[]', $detectType(["a", "b", "c"]));
        $this->assertEquals('string[][]', $detectType([["a"], ["b"], ["c"]]));
        // オブジェクト配列反変
        $this->assertEquals('\\Exception[]', $detectType([new \Exception(), new \RuntimeException()]));
        $this->assertEquals('\\Exception[]', $detectType([new \RuntimeException(), new \Exception()]));
        $this->assertEquals('\\Exception[][]', $detectType([[new \Exception()], [new \Exception()]]));
    }

    function test_outputConstFile()
    {
        $FILENAME = self::COMPILE_DIR . '/const.php';
        @unlink($FILENAME);

        $renderer = new Renderer([
            'debug' => true,
        ]);

        /** @see Renderer::outputConstFile() */
        $outputConstFile = $this->publishMethod($renderer, 'outputConstFile');

        $this->assertFalse($outputConstFile($FILENAME, [
            'accessor' => [],
            'modifier' => [],
        ]));

        $outputConstFile($FILENAME, [
            'accessor' => [
                'hoge1' => '"hoge1"'
            ],
            'modifier' => [
                'fuga1' => '"fuga1"'
            ],
        ]);
        $this->assertStringEqualsFile($FILENAME, '<?php
// using modifier functions:
define("fuga1", fuga1(...[]));
// using array keys:
define("hoge1", "hoge1");
');

        $outputConstFile($FILENAME, [
            'accessor' => [
                'ns\\hoge2' => '"ns\\\\hoge2"',
                'over'      => '"over"',
            ],
            'modifier' => [
                '\\ns\\fuga2' => '"ns\\\\fuga2"',
                'over'        => '"over"',
            ],
        ]);
        $this->assertStringEqualsFile($FILENAME, '<?php
// using modifier functions:
define("ns\\\\fuga2", \\ns\\fuga2(...[]));
define("fuga1", fuga1(...[]));
define("over", over(...[]));
// using array keys:
define("hoge1", "hoge1");
define("ns\\\\hoge2", "ns\\\\hoge2");
');
    }

    function test_assign()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        $renderer->assign('int', 456);
        $renderer->assign([
            'fuga' => 'FUGA',
            'hoge' => 'HOGE',
        ]);
        $renderer->assign([
            'appendix' => 'APPENDIX',
        ]);
        $renderer->render(self::TEMPLATE_DIR . '/vars.phtml', [
            'int'  => 123,
            'piyo' => 'PIYO',
        ]);

        // テンプレートのものがマージされているし、優先もされている
        $this->assertEquals([
            'int'      => 123,
            'piyo'     => 'PIYO',
            'fuga'     => 'FUGA',
            'hoge'     => 'HOGE',
            'appendix' => 'APPENDIX',
        ], $renderer->getAssignedVars());
    }

    function test_errorHandling_no()
    {
        $renderer = new Renderer([
            'debug'         => true,
            'errorHandling' => false,
        ]);
        error_clear_last();
        $this->assertEquals("az", @$renderer->render(self::TEMPLATE_DIR . '/notice.phtml'));
        $this->assertEquals('Undefined variable: undefined', error_get_last()['message']);
    }

    function test_errorHandling_report0()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $receiver = null;
            set_error_handler(static function ($v) use (&$receiver) {
                $receiver = true;
            });
            $this->assertEquals('az', @$renderer->render(self::TEMPLATE_DIR . '/notice.phtml', []));
            $this->assertTrue($receiver);
        }
        catch (\Throwable $e) {
            $this->fail();
        }
        finally {
            restore_error_handler();
        }
    }

    function test_errorHandling_notice()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $renderer->render(self::TEMPLATE_DIR . '/notice.phtml', []);
        }
        catch (\Throwable $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(realpath(self::TEMPLATE_DIR . '/notice.phtml'), $e->getFile());
            $this->assertEquals('Undefined variable: undefined
near:
*a<?= $undefined ?>z
', $e->getMessage());
            $this->assertNotContains(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
            return;
        }
        $this->fail();
    }

    function test_errorHandling_uncatch_error()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $renderer->render(self::TEMPLATE_DIR . '/error.phtml', ['object' => new \stdClass()]);
        }
        catch (\Throwable $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(realpath(self::TEMPLATE_DIR . '/error.phtml'), $e->getFile());
            $this->assertEquals('Call to undefined method stdClass::undefinedMethod()
near:
 dummy line 3
 <?php
 // dummy line
*$object->undefinedMethod()
 ?>
 dummy line 8
', $e->getMessage());
            $this->assertNotContains(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
            return;
        }
        $this->fail();
    }

    function test_errorHandling_uncatch_exception()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $renderer->render(self::TEMPLATE_DIR . '/error.phtml', [
                'object' => new class {
                    public function undefinedMethod() { throw new \Exception('msg'); }
                }
            ]);
        }
        catch (\Throwable $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(__FILE__, $e->getFile());
            $this->assertEquals('msg', $e->getMessage());
            $this->assertNotContains(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
            return;
        }
        $this->fail();
    }

    function test_errorHandling_nest_error()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $renderer->render(self::TEMPLATE_DIR . '/error-nest.phtml', [
                'object' => new class {
                    public function undefinedMethod($arg = []) { return $arg['undefined']; }
                },
            ]);
        }
        catch (\Throwable $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals('Undefined index: undefined', $e->getMessage());
            $this->assertStringContainsString('error-nest9.phtml(12)', $e->getTraceAsString());
            $this->assertStringContainsString('error-nest2.phtml(6)', $e->getTraceAsString());
            $this->assertStringContainsString('error-nest1.phtml(6)', $e->getTraceAsString());
            $this->assertStringContainsString('error-nest.phtml(6)', $e->getTraceAsString());
            return;
        }
        $this->fail();
    }

    function test_errorHandling_nest_exception()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $renderer->render(self::TEMPLATE_DIR . '/error-nest.phtml', [
                'object' => new class {
                    public function undefinedMethod() { throw new \Exception('msg'); }
                },
            ]);
        }
        catch (\Throwable $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(__FILE__, $e->getFile());
            $this->assertEquals('msg', $e->getMessage());
            $this->assertStringContainsString('error-nest9.phtml(12)', $e->getTraceAsString());
            $this->assertStringContainsString('error-nest2.phtml(6)', $e->getTraceAsString());
            $this->assertStringContainsString('error-nest1.phtml(6)', $e->getTraceAsString());
            $this->assertStringContainsString('error-nest.phtml(6)', $e->getTraceAsString());
            return;
        }
        $this->fail();
    }

    function test_errorHandling_previous()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $renderer->render(self::TEMPLATE_DIR . '/error.phtml', [
                'object' => new class {
                    public function undefinedMethod() { throw new \Exception('msg2', 2, new \Exception('msg1', 1)); }
                }
            ]);
        }
        catch (\Throwable $e) {
            $this->assertEquals(2, $e->getCode());
            $this->assertEquals(__FILE__, $e->getFile());
            $this->assertEquals('msg2', $e->getMessage());
            $this->assertNotContains(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());

            $e = $e->getPrevious();
            $this->assertEquals(1, $e->getCode());
            $this->assertEquals(__FILE__, $e->getFile());
            $this->assertEquals('msg1', $e->getMessage());
            $this->assertNotContains(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());

            return;
        }
        $this->fail();
    }

    function test_errorHandling_parserr()
    {
        $renderer = new Renderer([
            'debug' => true,
        ]);
        try {
            $renderer->render(self::TEMPLATE_DIR . '/syntax.html');
        }
        catch (\Throwable $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(realpath(self::TEMPLATE_DIR . '/syntax.html'), $e->getFile());
            $this->assertEquals('syntax error, unexpected \'is\' (T_STRING)
near:
 dummy line 1
 dummy line 2
*<?php this is syntax error ?>
 dummy line 4
 dummy line 5
 dummy line 6
', $e->getMessage());
            $this->assertNotContains(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
            return;
        }
        $this->fail();
    }
}
