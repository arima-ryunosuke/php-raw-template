<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\HtmlString;
use ryunosuke\NightDragon\Renderer;
use ryunosuke\NightDragon\Source;

class RendererTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_html()
    {
        $this->assertEquals('&lt;&#039;&gt;', Renderer::html("<'>"));
        $this->assertEquals('&lt;&#039;&gt;', Renderer::html("<", "'", ">"));
        $this->assertEquals("<'>", Renderer::html(new HtmlString("<'>")));
    }

    function test_access()
    {
        $this->assertEquals('X', Renderer::access(['x' => 'X'], 'x'));
        $this->assertEquals('X', Renderer::access((object) ['x' => 'X'], 'x'));
        $this->assertEquals('X', Renderer::access(new \ArrayObject(['x' => 'X']), 'x'));

        $this->assertEquals(['y' => ['z' => 999]], Renderer::access(['x' => ['y' => ['z' => 999]]], 'x'));
        $this->assertEquals(['z' => 999], Renderer::access(['x' => ['y' => ['z' => 999]]], 'x', 'y'));
        $this->assertEquals('999', Renderer::access(['x' => ['y' => ['z' => 999]]], 'x', 'y', 'z'));
    }

    function test_strip()
    {
        $this->assertEquals('hello world', Renderer::strip(' hello world '));
        $this->assertEquals('あいうえお', Renderer::strip(' あいうえお '));
        $this->assertEquals('<div><span class="x y z">hello world </span></div>', Renderer::strip('
<div>
    <span
      class="x y z"
      >
      hello world
    </span>
</div>
', ['placeholder' => '']));

        $this->assertEquals('test nightdragonboundary <div><strong id="strong1" class="hoge fuga piyo"> <? $multiline ?>
 <br><?php foreach($array as $k=>$v) {
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
    </script><strong id="strong2" class="hoge fuga piyo"><span id="<? $id ?>"> asd </span>line1 line2 line3 </strong></div>', Renderer::strip('

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
        ?></strong><pre>
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
    
    <span id="<? $id ?>">
    asd
</span>
        line1
line2

line3
    </strong>
</div>

', ['placeholder' => '']));

        $this->assertEquals('<div><s>content</s></div>', @Renderer::strip('<div><s>content</div>', ['noerror' => false]));
        $this->assertEquals("76: Opening and ending tag mismatch: div and s", trim(error_get_last()['message']));
    }

    function test___construct()
    {
        $this->assertException('is not writable directory', function () {
            return new Renderer([
                'compileDir' => DIRECTORY_SEPARATOR === '/' ? '/dev/null' : 'nul',
            ]);
        });
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
            'debug'      => false,
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
            'local' => null,
            'int'   => 123,
        ];
        $PARENT = [
            'parent'  => null,
            'parent1' => 123,
        ];

        $renderer = new Renderer([
            'debug'           => true,
            'compileDir'      => self::COMPILE_DIR,
            'defaultClass'    => \template\T::class,
            'specialVariable' => [
                '$undef' => 'int',
            ],
        ]);

        $renderer->assign('global', null);
        $renderer->assign('mixed', 123);

        $gatherOptions = $this->publishProperty($renderer, 'gatherOptions');
        $current = $gatherOptions();

        file_put_contents(self::TEMPLATE_DIR . '/meta.phtml', <<<'PHP'
<?php
# meta template data
?>
<?= $null ?>
<?= $int ?>
<?= $parent1 ?>
<?= $undef ?>
PHP
        );

        // 固定が埋め込まれる
        $current['gatherVariable'] = Renderer::FIXED;
        $gatherOptions($current);
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS, $PARENT);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertStringContainsString('@var \\ryunosuke\\NightDragon\\Template $this', $template);
        $this->assertStringContainsString('@var mixed $_', $template);
        $this->assertStringNotContainsString('$undef */', $template);
        $this->assertStringNotContainsString('@var int $int', $template);
        $this->assertStringNotContainsString('@var int $parent1', $template);
        $this->assertStringNotContainsString('@var null $global', $template);
        $this->assertStringNotContainsString('@var null $local', $template);
        $this->assertStringNotContainsString('@var null $parent', $template);

        // 固定と使用が埋め込まれる
        $current['gatherVariable'] = Renderer::FIXED | Renderer::USING;
        $gatherOptions($current);
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS, $PARENT);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertStringContainsString('@var \\ryunosuke\\NightDragon\\Template $this', $template);
        $this->assertStringContainsString('@var mixed $_', $template);
        $this->assertStringContainsString('$undef */', $template);
        $this->assertStringContainsString('@var int $int', $template);
        $this->assertStringContainsString('@var int $parent1', $template);
        $this->assertStringNotContainsString('@var null $global', $template);
        $this->assertStringNotContainsString('@var null $local', $template);
        $this->assertStringNotContainsString('@var null $parent', $template);

        // 固定と使用とグローバルが埋め込まれる
        $current['gatherVariable'] = Renderer::FIXED | Renderer::USING | Renderer::GLOBAL;
        $gatherOptions($current);
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS, $PARENT);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertStringContainsString('@var \\ryunosuke\\NightDragon\\Template $this', $template);
        $this->assertStringContainsString('@var mixed $_', $template);
        $this->assertStringContainsString('$undef */', $template);
        $this->assertStringContainsString('@var int $int', $template);
        $this->assertStringContainsString('@var int $parent1', $template);
        $this->assertStringContainsString('@var null $global', $template);
        $this->assertStringNotContainsString('@var null $local', $template);
        $this->assertStringNotContainsString('@var null $parent', $template);

        // 固定とグローバルとアサインが埋め込まれる
        $current['gatherVariable'] = Renderer::FIXED | Renderer::USING | Renderer::GLOBAL | Renderer::ASSIGNED;
        $gatherOptions($current);
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS, $PARENT);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertStringContainsString('@var \\ryunosuke\\NightDragon\\Template $this', $template);
        $this->assertStringContainsString('@var mixed $_', $template);
        $this->assertStringContainsString('$undef */', $template);
        $this->assertStringContainsString('@var int $int', $template);
        $this->assertStringContainsString('@var int $parent1', $template);
        $this->assertStringContainsString('@var null $global', $template);
        $this->assertStringContainsString('@var null $local', $template);
        $this->assertStringContainsString('@var null $parent', $template);

        // これまでの結果がマージされる
        $current['gatherVariable'] = Renderer::FIXED | Renderer::USING | Renderer::GLOBAL | Renderer::ASSIGNED | Renderer::DECLARED;
        $gatherOptions($current);
        $renderer->assign('mixed', 'string');
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS, $PARENT);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertStringContainsString('@var string|int $mixed', $template);

        // 名前空間も問題ない
        $renderer->compile(self::TEMPLATE_DIR . '/namespace.phtml', []);
        $template = file_get_contents(self::TEMPLATE_DIR . '/namespace.phtml');
        $this->assertStringContainsString("define('globaled', \\globaled(...[]))", $template);
        $this->assertStringContainsString("define('spaced', \\template\\spaced(...[]))", $template);
        $this->assertStringContainsString("define('fully\\\\qualified', \\fully\\qualified(...[]))", $template);
        $this->assertStringContainsString("define('method', \\template\\T::method(...[]))", $template);
        $this->assertStringContainsString("define('ucwords", $template);

        $renderer = new Renderer([
            'debug'          => true,
            'gatherModifier' => false,
            'gatherAccessor' => false,
            'compileDir'     => self::COMPILE_DIR,
        ]);

        // 指定した Variable だけのメタ情報が埋め込まれている
        $renderer->compile(self::TEMPLATE_DIR . '/meta.phtml', $VARS);
        $template = file_get_contents(self::TEMPLATE_DIR . '/meta.phtml');
        $this->assertStringContainsString(Renderer::VARIABLE_COMMENT, $template);
        $this->assertStringNotContainsString(Renderer::MODIFIER_FUNCTION_COMMENT, $template);
        $this->assertStringNotContainsString(Renderer::ACCESS_KEY_COMMENT, $template);
    }

    function test_compile_dir()
    {
        \ryunosuke\NightDragon\rm_rf(self::COMPILE_DIR);
        clearstatcache();
        $this->assertFileDoesNotExist(self::COMPILE_DIR);

        $renderer = new Renderer([
            'debug'      => false,
            'compileDir' => self::COMPILE_DIR,
        ]);
        $this->assertEquals("path1path2", $renderer->render(self::TEMPLATE_DIR . '/path.phtml'));

        $this->assertFileExists(self::COMPILE_DIR);
    }

    function test_detectType()
    {
        $renderer = new Renderer([
            'debug'       => true,
            'typeMapping' => [
                '\\DateTime' => [\DateTime::class, \DateTimeImmutable::class],
            ],
        ]);

        /** @see Renderer::detectType() */
        $detectType = $this->publishMethod($renderer, 'detectType');

        // シンプルな奴ら
        $this->assertEquals(['int'], $detectType(123));
        $this->assertEquals(['float'], $detectType(3.14));
        $this->assertEquals(['bool'], $detectType(false));
        $this->assertEquals(['string'], $detectType('string'));
        $this->assertEquals(['resource'], $detectType(STDOUT));
        $this->assertEquals(['array'], $detectType([]));
        $this->assertEquals(['\\Exception'], $detectType(new \Exception()));
        $this->assertEquals(['DateTime', 'DateTimeImmutable'], $detectType(new \DateTime()));

        // 素の匿名クラスは object
        $this->assertEquals(['object'], $detectType(new class {
        }));
        // 継承していばそいつ
        $this->assertEquals(['\\stdClass'], $detectType(new class extends \stdClass {
        }));
        // 実装していばそいつ
        $this->assertEquals(['\\Countable'], $detectType(new class implements \Countable {
            public function count(): int { }
        }));
        // 継承も実装もしてれば両方
        $this->assertEquals(['\\stdClass', '\\Countable'], $detectType(new class extends \stdClass implements \Countable {
            public function count(): int { }
        }));
        // ただし継承元に実装メソッドが含まれている場合は含まれない
        $this->assertEquals(['\\ArrayObject', '\\JsonSerializable'], $detectType(new class extends \ArrayObject implements \Countable, \JsonSerializable {
            #[\ReturnTypeWillChange]
            public function jsonSerialize() { }
        }));

        // 配列系のシンプルな奴ら
        $this->assertEquals(['int[]'], $detectType([1, 2, 3]));
        $this->assertEquals(['array'], $detectType([1, 'a', null]));
        $this->assertEquals(['DateTime[]', 'DateTimeImmutable[]'], $detectType([new \DateTime(), new \DateTime()]));
        $this->assertEquals(['DateTime[][]', 'DateTimeImmutable[][]'], $detectType([[new \DateTime()], [new \DateTime()]]));
        // 配列の配列系
        $this->assertEquals(['string[]'], $detectType(["a", "b", "c"]));
        $this->assertEquals(['string[][]'], $detectType([["a"], ["b"], ["c"]]));
        // オブジェクト配列反変
        $this->assertEquals(['\\Exception[]'], $detectType([new \Exception(), new \RuntimeException()]));
        $this->assertEquals(['\\Exception[]'], $detectType([new \RuntimeException(), new \Exception()]));
        $this->assertEquals(['\\Exception[][]'], $detectType([[new \Exception()], [new \Exception()]]));
    }

    function test_gatherVariable()
    {
        $renderer = new Renderer([
            'debug'           => true,
            'typeMapping'     => [
                '\\DateTime' => [\DateTime::class, \DateTimeImmutable::class],
            ],
            'specialVariable' => [
                '$var'      => ['float', 'int', 'array'],
                '$multiple' => ['array', 'stdClass'],
                '$mixed'    => ['mixed', 'array', 'resource'],
            ],
        ]);
        $renderer->assign('multiple', new \RuntimeException());

        /** @see Renderer::gatherVariable() */
        $gatherVariable = $this->publishMethod($renderer, 'gatherVariable');

        // 普通にマージされる
        $vars = $gatherVariable(new Source(""), '$_', [], [
            'var' => 'string',
        ]);
        $this->assertEquals('array|string|int|float', $vars['$var']);

        // mixed は単一になる
        $vars = $gatherVariable(new Source(""), '$_', [], [
            'mixed' => ['string', 'int'],
        ]);
        $this->assertEqualsCanonicalizing(['mixed'], explode('|', $vars['$mixed']));

        // 明示的な配列を含む場合は array は消える
        $vars = $gatherVariable(new Source(""), '$_', [], [
            'var' => ['a', 'b'],
        ]);
        $this->assertEqualsCanonicalizing(['string[]', 'int', 'float'], explode('|', $vars['$var']));

        // 継承関係は末端が優先される
        $vars = $gatherVariable(new Source(""), '$_', [], [
            'multiple' => new \Exception(),
        ]);
        $this->assertEqualsCanonicalizing(['\\RuntimeException', 'stdClass', 'array'], explode('|', $vars['$multiple']));
        $vars = $gatherVariable(new Source(""), '$_', [], [
            'multiple' => new \UnexpectedValueException(),
        ]);
        $this->assertEqualsCanonicalizing(['\\UnexpectedValueException', 'stdClass', 'array'], explode('|', $vars['$multiple']));

        // 既存宣言の並び順が維持される
        $vars = $gatherVariable(new Source(<<<'PHP'
        <?php
        # meta template data
        /** @var string $a */
        /** @var string $b */
        /** @var string $c */
        /** @var string $d */
        /** @var string $e */
        ?>
        PHP
        ), '$_', [], [
            'b' => 'string',
            'c' => 'string',
            'a' => 'string',
            'x' => 'string',
        ]);
        $this->assertSame([
            '$a'        => 'string',
            '$b'        => 'string',
            '$c'        => 'string',
            '$d'        => 'string',
            '$e'        => 'string',
            '$this'     => '\\ryunosuke\\NightDragon\\Template',
            '$_'        => 'mixed',
            '$multiple' => 'array|\\RuntimeException|stdClass',
            '$x'        => 'string',
        ], $vars);
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

        $this->assertNull($outputConstFile($FILENAME, [
            'accessor' => [],
            'modifier' => [],
        ]));

        $outputConstFile($FILENAME, [
            'accessor' => [
                'hoge1' => 'hoge1',
            ],
            'modifier' => [
                'fuga1' => 'fuga1',
            ],
        ]);
        $this->assertStringEqualsFile($FILENAME, <<<'EXPECTED'
<?php
if (null) {
    // using modifier functions:
    function fuga1(...$args){define('fuga1', fuga1(...[]));return fuga1(...$args);}
    // using array keys:
    define('hoge1', 'hoge1');
}
return [
    "accessor" => [
        "hoge1" => "hoge1",
    ],
    "modifier" => [
        "fuga1" => "fuga1",
    ],
];

EXPECTED
        );

        $outputConstFile($FILENAME, [
            'accessor' => [
                'ns\\hoge2' => 'ns\\hoge2',
                'over'      => 'over',
            ],
            'modifier' => [
                '\\ns\\fuga2' => 'ns\\fuga2',
                'over'        => 'over',
            ],
        ]);
        $this->assertStringEqualsFile($FILENAME, <<<'EXPECTED'
<?php
if (null) {
    // using modifier functions:
    function \ns\fuga2(...$args){define('\\ns\\fuga2', ns\fuga2(...[]));return ns\fuga2(...$args);}
    function fuga1(...$args){define('fuga1', fuga1(...[]));return fuga1(...$args);}
    function over(...$args){define('over', over(...[]));return over(...$args);}
    // using array keys:
    define('hoge1', 'hoge1');
    define('ns\\hoge2', 'ns\\hoge2');
    define('over', 'over');
}
return [
    "accessor" => [
        "hoge1"     => "hoge1",
        "ns\\hoge2" => "ns\\hoge2",
        "over"      => "over",
    ],
    "modifier" => [
        "\\ns\\fuga2" => "ns\\fuga2",
        "fuga1"       => "fuga1",
        "over"        => "over",
    ],
];

EXPECTED
        );

        file_put_contents($FILENAME, '<?php syntax error.');
        $outputConstFile($FILENAME, [
            'accessor' => [
                'hoge1' => 'hoge1',
            ],
            'modifier' => [
                'fuga1' => 'fuga1',
            ],
        ]);
        $this->assertStringEqualsFile($FILENAME, <<<'EXPECTED'
<?php
if (null) {
    // using modifier functions:
    function fuga1(...$args){define('fuga1', fuga1(...[]));return fuga1(...$args);}
    // using array keys:
    define('hoge1', 'hoge1');
}
return [
    "accessor" => [
        "hoge1" => "hoge1",
    ],
    "modifier" => [
        "fuga1" => "fuga1",
    ],
];

EXPECTED
        );
    }

    function test_assign()
    {
        $renderer = new Renderer([
            'debug'          => true,
            'gatherVariable' => Renderer::FIXED | Renderer::USING,
        ]);
        $renderer->assign('int', 456);
        $renderer->assign([
            'fuga' => 'FUGA',
            'hoge' => 'HOGE',
        ]);
        $renderer->assign([
            'appendix' => 'APPENDIX',
        ]);
        $content = $renderer->render(self::TEMPLATE_DIR . '/vars.phtml', [
            'int'  => 123,
            'piyo' => 'PIYO',
        ]);
        $this->assertEquals(trim('
ex-var1
ex-var2
var1
var2
'), trim($content));

        // テンプレートのものがマージされているし、優先もされている
        $this->assertEquals([
            'int'      => 123,
            'piyo'     => 'PIYO',
            'fuga'     => 'FUGA',
            'hoge'     => 'HOGE',
            'appendix' => 'APPENDIX',
            'var1'     => 'ex-var1',
            'var2'     => 'ex-var2',
            'inner1'   => 'inner1',
            'inner2'   => 'inner2',
            'inner3'   => 'ex-inner3',
        ], $renderer->getAssignedVars());

        // テンプレート毎に返す
        $this->assertEquals([
            '(global)'                                          => [
                'int'      => 456,
                'fuga'     => 'FUGA',
                'hoge'     => 'HOGE',
                'appendix' => 'APPENDIX',
            ],
            realpath(self::TEMPLATE_DIR . '/vars.phtml')        => [
                'int'  => 123,
                'piyo' => 'PIYO',
                'var1' => 'ex-var1',
                'var2' => 'ex-var2',
            ],
            realpath(self::TEMPLATE_DIR . '/vars-parent.phtml') => [
                'int'    => 123,
                'piyo'   => 'PIYO',
                'var1'   => 'var1',
                'var2'   => 'var2',
                'inner1' => 'inner1',
                'inner2' => 'inner2',
                'inner3' => 'ex-inner3',
            ],
            realpath(self::TEMPLATE_DIR . '/vars-inner.phtml')  => [
                'inner1' => 'ex-inner1',
                'inner2' => 'ex-inner2',
                'inner3' => 'ex-inner3',
            ],
        ], $renderer->getAssignedVars(true));
    }

    function test_errorHandling_no()
    {
        $renderer = new Renderer([
            'debug'         => true,
            'errorHandling' => false,
        ]);
        error_clear_last();
        $this->assertEquals("az", @$renderer->render(self::TEMPLATE_DIR . '/notice.phtml'));
        $this->assertStringContainsString('Undefined variable', error_get_last()['message']);
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
        catch (\Throwable) {
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
            $this->assertStringContainsString('Undefined variable', $e->getMessage());
            $this->assertStringContainsString('*a<?= $undefined ?>z', $e->getMessage());
            $this->assertStringNotContainsString(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
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
 dummy line 9
', $e->getMessage());
            $this->assertStringNotContainsString(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
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
                },
            ]);
        }
        catch (\Throwable $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(__FILE__, $e->getFile());
            $this->assertEquals('msg', $e->getMessage());
            $this->assertStringNotContainsString(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
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
            $this->assertStringContainsString('Undefined', $e->getMessage());
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
                },
            ]);
        }
        catch (\Throwable $e) {
            $this->assertEquals(2, $e->getCode());
            $this->assertEquals(__FILE__, $e->getFile());
            $this->assertEquals('msg2', $e->getMessage());
            $this->assertStringNotContainsString(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());

            $e = $e->getPrevious();
            $this->assertEquals(1, $e->getCode());
            $this->assertEquals(__FILE__, $e->getFile());
            $this->assertEquals('msg1', $e->getMessage());
            $this->assertStringNotContainsString(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());

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
            $this->assertStringContainsString('syntax error, unexpected', $e->getMessage());
            $this->assertStringContainsString('*<?php this is syntax error ?>', $e->getMessage());
            $this->assertStringNotContainsString(' ' . Renderer::DEFAULT_PROTOCOL, $e->getTraceAsString());
            return;
        }
        $this->fail();
    }
}
