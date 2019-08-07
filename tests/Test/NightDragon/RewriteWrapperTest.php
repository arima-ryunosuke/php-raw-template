<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\RewriteWrapper;
use ryunosuke\NightDragon\Source;
use function ryunosuke\NightDragon\is_arrayable;

class RewriteWrapperTest extends \ryunosuke\Test\AbstractTestCase
{
    const defaultOption = [
        'compatibleShortTag' => false,
        'defaultNamespace'   => ['\\'],
        'defaultFilter'      => 'html',
        'defaultGetter'      => 'access',
        'defaultCloser'      => "\n",
        'varReceiver'        => '$_',
        'varModifier'        => '|',
        'varAccessor'        => '.',
    ];

    function test_stream()
    {
        RewriteWrapper::register('hogera');

        $path = 'hogera://dummy/' . __FILE__;
        $this->assertFileExists($path);
        $this->assertEquals(filesize(__FILE__), filesize($path));
        $this->assertStringEqualsFile(__FILE__, file_get_contents("$path?" . http_build_query(self::defaultOption)));

        error_clear_last();
        @file_get_contents("$path/notfound");
        $this->assertRegExp('#file_get_contents.*failed to open stream#', error_get_last()['message']);
    }

    function test_rewrite()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        $actual = "<?= ARRAYS.key1.key2 | f1 | f2 ?>\n";
        $expected = "<?=html(f2(f1(access(access(ARRAYS,'key1'),'key2')))),\"\\n\"?>\n";
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));
    }

    function test_rewrite_eval()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        /** @noinspection PhpUnusedLocalVariableInspection */
        {
            $key = 'key';
            $array = [
                'hoge' => (object) [
                    'fuga' => [
                        1 => [
                            'key' => ' XYhello worldYX ',
                        ]
                    ]
                ],
            ];
            $filter = function ($v) { return str_replace(' ', '(space)', $v); };
            $getter = function ($p, $k) { return is_arrayable($p) ? $p[$k] : $p->$k; };
        }

        $source = '';
        $source .= '$array.hoge.fuga.1.$key';   // 配列アクセスこみこみ
        $source .= '| trim';                    // 単純修飾子
        $source .= '| trim("X")';               // 暗黙第1引数適用
        $source .= '| trim($_, "Y")';           // 第1引数適用
        $source .= '| sprintf("z%sz", $_)';     // 第2引数適用
        $source .= '| strtoupper("z{$_}z")';    // 埋め込み構文

        $code = $rewrite("<?= $source ?>", [
                'defaultFilter' => '$filter',
                'defaultGetter' => '$getter',
            ] + self::defaultOption);
        $this->assertEquals('ZZHELLO(space)WORLDZZ', eval('ob_start(); ?>' . $code . '<?php return ob_get_clean();'), $code);
    }

    function test_rewrite_compatible_shortTag()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        // token_get_all は short_open_tag の影響を受けるので分岐が必要
        if (ini_get('short_open_tag')) {
            $this->markTestSkipped('short_open_tag is disabled');
        }

        $actual = '
<? foreach($array.key1.key2 as $k => $v): ?>
<? endforeach ?>';
        $expected = "
<?php foreach(access(access(\$array,'key1'),'key2')as\$k=>\$v):?>
<?php endforeach?>";
        $this->assertEquals($expected, $rewrite($actual, ['compatibleShortTag' => true] + self::defaultOption));

        $actual = '
<? foreach($array | array_slice): ?>
<? endforeach ?>';
        $expected = "
<?php foreach(\$array|array_slice):?>
<?php endforeach?>";
        $this->assertEquals($expected, $rewrite($actual, ['compatibleShortTag' => true] + self::defaultOption));
    }

    function test_rewrite_no_shortTag()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        $actual = '
<?php foreach($array.key1.key2 as $k => $v): ?>
<?php endforeach ?>';
        $expected = $actual;
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));

        $actual = '
<?php foreach($array | array_slice): ?>
<?php endforeach ?>';
        $expected = $actual;
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));

        // token_get_all は short_open_tag の影響を受けるので分岐が必要
        if (!ini_get('short_open_tag')) {
            $this->markTestSkipped('short_open_tag is disabled');
        }
        $actual = '
<? foreach($array.key1.key2 as $k => $v): ?>
<? endforeach ?>';
        $expected = "
<?php foreach(access(access(\$array,'key1'),'key2')as\$k=>\$v):?>
<?php endforeach?>";
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));

        $actual = '
<? foreach($array | array_slice): ?>
<? endforeach ?>';
        $expected = "
<?php foreach(\$array|array_slice):?>
<?php endforeach?>";
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));
    }

    function test_rewrite_none()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        $actual = "<?= ARRAYS.key1.key2 >> f1 >> f2 ?>\n";
        $expected = "<?=(ARRAYS.key1.key2>>f1>>f2)?>\n";
        $this->assertEquals($expected, $rewrite($actual, [
                'defaultFilter' => '',
                'defaultCloser' => '',
                'varModifier'   => '',
                'varAccessor'   => '',
            ] + self::defaultOption));
    }

    function test_rewriteAccessKey()
    {
        /** @see RewriteWrapper::rewriteAccessKey() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteAccessKey');

        $source = new Source('<?= $array.key1.key2.3.key + 3.14 ?>');
        $rewrite($source, '.', 'access');
        $this->assertEquals("<?= access(access(access(access(\$array,'key1'),'key2'),'3'),'key') + 3.14 ?>", (string) $source);

        $source = new Source('<?= $array.key1.key2.3 + 3.14 ?>');
        $rewrite($source, '.', '');
        $this->assertEquals("<?= \$array['key1']['key2']['3'] + 3.14 ?>", (string) $source);

        $source = new Source('<?= $array/key/3 + .14 ?>');
        $rewrite($source, '/', '');
        $this->assertEquals("<?= \$array['key']['3'] + .14 ?>", (string) $source);
    }

    function test_rewriteModifier()
    {
        /** @see RewriteWrapper::rewriteModifier() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteModifier');

        $source = new Source('<?= $a | trim | trim($_, "X") | sprintf("z%sz", "$_") | sprintf("Z{$_}Z", "$_") ?>');
        $rewrite($source, '$_', '|', []);
        $this->assertEquals('<?=sprintf("Z".sprintf("z%sz","".trim(trim($a),"X")."")."Z","".sprintf("z%sz","".trim(trim($a),"X")."")."")?>', (string) $source);

        $source = new Source('<?= $a >> trim >> trim($rrr, "X") >> sprintf("z%sz", "$rrr") >> sprintf("Z{$rrr}Z", "$rrr") ?>');
        $rewrite($source, '$rrr', '>>', []);
        $this->assertEquals('<?=sprintf("Z".sprintf("z%sz","".trim(trim($a),"X")."")."Z","".sprintf("z%sz","".trim(trim($a),"X")."")."")?>', (string) $source);
    }

    function test_rewriteModifier_namespace()
    {
        /** @see RewriteWrapper::rewriteModifier() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteModifier');

        $source = new Source('<?= $value | \\globaled | spaced | \\fully\\qualified ?>');
        $rewrite($source, '$_', '|', []);
        $this->assertEquals('<?=\\fully\\qualified(spaced(\\globaled($value)))?>', (string) $source);

        $source = new Source('<?= $value | \\globaled | spaced | \\fully\\qualified ?>');
        $rewrite($source, '$_', '|', ['\\template']);
        $this->assertEquals('<?=\\fully\\qualified(\\template\\spaced(\\globaled($value)))?>', (string) $source);

        $source = new Source('<?= $value | f ?>');
        $rewrite($source, '$_', '|', ['\\hoge', '\\fuga']);
        $this->assertEquals('<?=\hoge\f($value)?>', (string) $source);

        $source = new Source('<?= $value | f ?>');
        $rewrite($source, '$_', '|', ['\\fuga', '\\hoge']);
        $this->assertEquals('<?=\fuga\f($value)?>', (string) $source);
    }

    function test_rewriteModifier_default()
    {
        /** @see RewriteWrapper::rewriteModifier() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteModifier');

        $source = new Source('<?= $value | funcA(1) ?>');
        $rewrite($source, '$_', '|', []);
        $this->assertEquals('<?=funcA($value,1)?>', (string) $source);

        $source = new Source('<?= $value | funcA($_, 1) ?>');
        $rewrite($source, '$_', '|', []);
        $this->assertEquals('<?=funcA($value,1)?>', (string) $source);
    }

    function test_rewriteFilter()
    {
        /** @see RewriteWrapper::rewriteFilter() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteFilter');

        $source = new Source("<?= 'a' ?><?= 'b' ?> <?= 'c' ?>\n");
        $rewrite($source, 'html', "\n");
        $this->assertEquals("<?=html('a')?><?=html('b')?> <?=html('c'),\"\\n\"?>\n", (string) $source);

        $source = new Source("<?= 'a' ?><?= 'b' ?> <?= 'c' ?>\n");
        $rewrite($source, 'json', '');
        $this->assertEquals("<?=json('a')?><?=json('b')?> <?=json('c')?>\n", (string) $source);
    }
}
