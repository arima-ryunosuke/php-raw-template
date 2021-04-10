<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\HtmlObject;
use ryunosuke\NightDragon\RewriteWrapper;
use ryunosuke\NightDragon\Source;
use function ryunosuke\NightDragon\array_sprintf;
use function ryunosuke\NightDragon\is_arrayable;

class RewriteWrapperTest extends \ryunosuke\Test\AbstractTestCase
{
    const defaultOption = [
        'customTagHandler'   => [''],
        'compatibleShortTag' => false,
        'defaultNamespace'   => ['\\'],
        'defaultClass'       => [''],
        'defaultFilter'      => 'html',
        'defaultGetter'      => 'access',
        'defaultCloser'      => "\n",
        'nofilter'           => '',
        'varReceiver'        => '$_',
        'varModifier'        => ['|', '&'],
        'varAccessor'        => '.',
        'varExpander'        => '`',
    ];

    function test_getLineMapping()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        $actual = '
<?php "simple" ?>
<delete>
dummy
</delete>
<?php [
    "multi",
    "line",
] ?>
<delete>
dummy
</delete>
<?php
    // comment
    $v = [
        // comment
        "multi",
        // comment
        "line",
    ]
?>
<delete>
dummy
</delete>
<?php "simple" ?>
';
        $expected = '
<?php "simple" ?>

<?php [
    "multi",
    "line",
] ?>

<?php
    // comment
    $v = [
        // comment
        "multi",
        // comment
        "line",
    ]
?>

<?php "simple" ?>
';
        $this->assertEquals($expected, $rewrite($actual, [
                'customTagHandler' => [
                    'delete' => function ($contents, $attrs) { return ''; },
                ]
            ] + self::defaultOption));
        $this->assertEquals(1, RewriteWrapper::getLineMapping("undefined", 1));
        $this->assertEquals(2, RewriteWrapper::getLineMapping("undefined", 2));
        $this->assertEquals(3, RewriteWrapper::getLineMapping("undefined", 3));
        $this->assertEquals(6, RewriteWrapper::getLineMapping("undefined", 4));
        $this->assertEquals(7, RewriteWrapper::getLineMapping("undefined", 5));
        $this->assertEquals(8, RewriteWrapper::getLineMapping("undefined", 6));
        $this->assertEquals(9, RewriteWrapper::getLineMapping("undefined", 7));
        $this->assertEquals(10, RewriteWrapper::getLineMapping("undefined", 8));
        $this->assertEquals(13, RewriteWrapper::getLineMapping("undefined", 9));
        $this->assertEquals(14, RewriteWrapper::getLineMapping("undefined", 10));
        $this->assertEquals(15, RewriteWrapper::getLineMapping("undefined", 11));
        $this->assertEquals(16, RewriteWrapper::getLineMapping("undefined", 12));
        $this->assertEquals(17, RewriteWrapper::getLineMapping("undefined", 13));
        $this->assertEquals(18, RewriteWrapper::getLineMapping("undefined", 14));
        $this->assertEquals(19, RewriteWrapper::getLineMapping("undefined", 15));
        $this->assertEquals(20, RewriteWrapper::getLineMapping("undefined", 16));
        $this->assertEquals(21, RewriteWrapper::getLineMapping("undefined", 17));
        $this->assertEquals(22, RewriteWrapper::getLineMapping("undefined", 18));
        $this->assertEquals(25, RewriteWrapper::getLineMapping("undefined", 19));
        $this->assertEquals(20, RewriteWrapper::getLineMapping("undefined", 20));
    }

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
        $expected = "<?=html(f2(f1(access(ARRAYS,'key1','key2')))),\"\\n\"?>\n";
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));

        $actual = "<?= @ARRAYS.key1.key2 | f1 | f2 ?>\n";
        $expected = "<?=f2(f1(access(ARRAYS,'key1','key2'))),\"\\n\"?>\n";
        $this->assertEquals($expected, $rewrite($actual, ['nofilter' => '@'] + self::defaultOption));
    }

    function test_rewrite_mix()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        $this->assertEquals(
            "<?=html(f2(f1(@access(\$array,'key1','key2','3','key') ?? 123)))?>",
            (string) $rewrite('<?= $array.key1.key2.3.key ?? 123 | f1 | f2 ?>', self::defaultOption)
        );
        $this->assertEquals(
            "<?=html(f2(f1(access(\$array,'key1','key2','3','key'))) ?? 456)?>",
            (string) $rewrite('<?= $array.key1.key2.3.key | f1 | f2 ?? 456 ?>', self::defaultOption)
        );
        $this->assertEquals(
            "<?=html(f2(f1(@access(\$array,'key1','key2','3','key') ?? 123)) ?? 456)?>",
            (string) $rewrite('<?= $array.key1.key2.3.key ?? 123 | f1 | f2 ?? 456 ?>', self::defaultOption)
        );
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
            $getter = function ($p, ...$k) {
                foreach ($k as $key) {
                    $p = is_arrayable($p) ? $p[$key] : $p->$key;
                }
                return $p;
            };
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

    function test_rewriteCustomTag()
    {
        /** @see RewriteWrapper::rewriteCustomTag() */
        $rewriteCustomTag = $this->publishMethod(new RewriteWrapper(), 'rewriteCustomTag');

        $actual = '
<hoge>simple tag</hoge>
<hoge attr1="hoge" attr2=\'あああ\'	attr3="hoge">attr tag1</hoge>
<hoge attr1 = "" attr2 = "a b c" data-attr3="hoge">space attr tag2</hoge>
<hoge attr1="A<?= "$inquote" ?>Z">attr tag1</hoge>';
        $expected = '
[]simple tag
{"attr1":"hoge","attr2":"あああ","attr3":"hoge"}attr tag1
{"attr1":"","attr2":"a b c","data-attr3":"hoge"}space attr tag2
{"attr1":"A<?= \"$inquote\" ?>Z"}attr tag1';
        $this->assertEquals($expected, $rewriteCustomTag($actual, [
            'hoge' => function ($contents, $attrs) {
                return json_encode($attrs, JSON_UNESCAPED_UNICODE) . $contents;
            },
        ]));

        $actual = '<hoge enable attr0="hoge" attr1="fuga" attr2=\'あああ\'	attr3="hoge" attr4="A<?= "$inquote" ?>Z">attr tag1</hoge>';
        $expected = '<fuga enable attr1="FUGA" attr2="あああ" attr3="hoge" attr4="A<?= "$inquote" ?>Z">attr tag1</fuga>';
        $this->assertEquals($expected, $rewriteCustomTag($actual, [
            'hoge' => function (HtmlObject $html) {
                $this->assertTrue(isset($html['attr1']));
                $this->assertFalse(isset($html['attr9']));
                unset($html['attr0']);
                $html['attr1'] = 'FUGA';
                $html->tagname('fuga');
                return $html;
            },
        ]));
    }

    function test_rewriteCustomTag_script()
    {
        /** @see RewriteWrapper::rewriteCustomTag() */
        $rewriteCustomTag = $this->publishMethod(new RewriteWrapper(), 'rewriteCustomTag');

        $actual = '
<script type="text/javascript" src="<?= $path ?>">alert(1);</script>
<script type="text/ecmascript" src="<?= $path ?>">alert(2);</script>
';
        $expected = '
<script type="text/javascript" src="<?= $path ?>">alert(1);</script>
<script type="text/javascript" src="<?= $path ?>">alert(2); convertedES</script>
';
        $this->assertEquals($expected, $rewriteCustomTag($actual, [
            'script' => function ($contents, $attrs) {
                if ($attrs['type'] === 'text/ecmascript') {
                    $attrs['type'] = 'text/javascript';
                    $attrs = array_sprintf($attrs, function ($v, $k) { return "$k=\"$v\""; }, ' ');
                    return "<script $attrs>$contents convertedES</script>";
                }
            },
        ]));
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
<?php foreach(access(\$array,'key1','key2') as \$k => \$v): ?>
<?php endforeach ?>";
        $this->assertEquals($expected, $rewrite($actual, ['compatibleShortTag' => true] + self::defaultOption));

        $actual = '
<? foreach($array | array_slice): ?>
<? endforeach ?>';
        $expected = "
<?php foreach(\$array | array_slice): ?>
<?php endforeach ?>";
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
<?php foreach(access(\$array,'key1','key2') as \$k => \$v): ?>
<?php endforeach ?>";
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));

        $actual = '
<? foreach($array | array_slice): ?>
<? endforeach ?>';
        $expected = "
<?php foreach(\$array | array_slice): ?>
<?php endforeach ?>";
        $this->assertEquals($expected, $rewrite($actual, self::defaultOption));
    }

    function test_rewrite_none()
    {
        /** @see RewriteWrapper::rewrite() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewrite');

        $actual = "<?= ARRAYS.key1.key2 >> f1 >> f2 ?>\n";
        $expected = "<?=ARRAYS.key1.key2 >> f1 >> f2?>\n";
        $this->assertEquals($expected, $rewrite($actual, [
                'defaultFilter' => '',
                'defaultCloser' => '',
                'varModifier'   => [],
                'varAccessor'   => '',
            ] + self::defaultOption));
    }

    function test_rewriteExpand()
    {
        /** @see RewriteWrapper::rewriteExpand() */
        $rewrite = $this->publishMethod(new RewriteWrapper(self::defaultOption), 'rewriteExpand');

        $source = new Source('<?= `a-${$n + 1}{$x}-z` ?>');
        $rewrite($source, '`');
        $this->assertEquals('<?= "a-".($n + 1)."{$x}-z" ?>', (string) $source);

        $source = new Source('<?= `a-${implode(",", $arr)}{$x}-z` ?>');
        $rewrite($source, '`');
        $this->assertEquals('<?= "a-".(implode(",", $arr))."{$x}-z" ?>', (string) $source);

        $source = new Source('<?= `a-${$arr | implode(",", $_)}{$x}-z` ?>');
        $rewrite($source, '`');
        $this->assertEquals('<?= "a-".(implode(",",$arr))."{$x}-z" ?>', (string) $source);

        $source = new Source('<?= `a-${[1,2,3] | json_encode}{$x}-z` ?>');
        $rewrite($source, '`');
        $this->assertEquals('<?= "a-".(json_encode([1,2,3]))."{$x}-z" ?>', (string) $source);

        $source = new Source('<?= "a-${$n + 1}-z" ?>');
        $rewrite($source, '"');
        $this->assertEquals('<?= "a-".($n + 1)."-z" ?>', (string) $source);
    }

    function test_rewriteAccessKey()
    {
        /** @see RewriteWrapper::rewriteAccessKey() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteAccessKey');

        $source = new Source('<?= $array.key1.key2.3.key + 3.14 ?>');
        $rewrite($source, '.', 'access');
        $this->assertEquals("<?= access(\$array,'key1','key2','3','key') + 3.14 ?>", (string) $source);

        $source = new Source('<?= $object->field ?><?= $object->method() ?><?= $object->method($object->field) ?>');
        $rewrite($source, '->', 'access');
        $this->assertEquals("<?= access(\$object,'field') ?><?= \$object->method() ?><?= \$object->method(access(\$object,'field')) ?>", (string) $source);

        $source = new Source('<?= "A-{$array.key1.key2.3.key}-Z" ?>');
        $rewrite($source, '.', 'access');
        $this->assertEquals("<?= \"A-\".access(\$array,'key1','key2','3','key').\"-Z\" ?>", (string) $source);

        $source = new Source('<?= "A-{$array->key1->key2->3->key}-Z" ?>');
        $rewrite($source, '->', 'access');
        $this->assertEquals("<?= \"A-\".access(\$array,'key1','key2','3','key').\"-Z\" ?>", (string) $source);

        $source = new Source('<?= $array.key1.key2.3 + 3.14 ?>');
        $rewrite($source, '.', '');
        $this->assertEquals("<?= \$array['key1']['key2']['3'] + 3.14 ?>", (string) $source);

        $source = new Source('<?= $array/key/3 + .14 ?>');
        $rewrite($source, '/', '');
        $this->assertEquals("<?= \$array['key']['3'] + .14 ?>", (string) $source);
    }

    function test_rewriteAccessKey_isset()
    {
        /** @see RewriteWrapper::rewriteAccessKey() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteAccessKey');

        $source = new Source('<?= isset($array.key1.key2.3.key + 3.14) ?>');
        $rewrite($source, '.', 'access');
        $this->assertEquals("<?= !@is_null(access(\$array,'key1','key2','3','key') + 3.14) ?>", (string) $source);

        $source = new Source('<?= empty($array.key1.key2.3.key + 3.14) ?>');
        $rewrite($source, '.', 'access');
        $this->assertEquals("<?= !@boolval(access(\$array,'key1','key2','3','key') + 3.14) ?>", (string) $source);
    }

    function test_rewriteAccessKey_default()
    {
        /** @see RewriteWrapper::rewriteAccessKey() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteAccessKey');

        $source = new Source('<?= $array.key1.key2.3.key ?? "default" ?>');
        $rewrite($source, '.', 'access');
        $this->assertEquals("<?= @access(\$array,'key1','key2','3','key') ?? \"default\" ?>", (string) $source);
    }

    function test_rewriteModifier()
    {
        /** @see RewriteWrapper::rewriteModifier() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteModifier');

        $source = new Source('<?= $a | trim | trim("X") | sprintf("z%sz", "$_") | sprintf("Z{$_}Z", "$_") ?>');
        $rewrite($source, '$_', ['|', '&'], [], []);
        $this->assertEquals('<?=sprintf("Z".sprintf("z%sz","".trim(trim($a),"X")."")."Z","".sprintf("z%sz","".trim(trim($a),"X")."")."")?>', (string) $source);
        $this->assertEquals('ZzYZzZ', eval("\$a=' XYZ ';ob_start();?>$source<?php return ob_get_clean() ?>"));

        $source = new Source('<?= $a & trim & trim("X") & sprintf("z%sz", "$_") & sprintf("Z{$_}Z", "$_") ?>');
        $rewrite($source, '$_', ['|', '&'], [], []);
        $this->assertEquals('<?=((${"\0"}=((${"\0"}=((${"\0"}=((${"\0"}=$a) === null ? ${"\0"} : trim(${"\0"}))) === null ? ${"\0"} : trim(${"\0"},"X"))) === null ? ${"\0"} : sprintf("z%sz","".${"\0"}.""))) === null ? ${"\0"} : sprintf("Z".${"\0"}."Z","".${"\0"}.""))?>', (string) $source);
        $this->assertEquals('ZzYZzZ', eval("\$a=' XYZ ';ob_start();?>$source<?php return ob_get_clean() ?>"));

        $source = new Source('<?= $a | trim & trim("X") & sprintf("z%sz", "$_") | sprintf("Z{$_}Z", "$_") ?>');
        $rewrite($source, '$_', ['|', '&'], [], []);
        $this->assertEquals('<?=sprintf("Z".((${"\0"}=((${"\0"}=trim($a)) === null ? ${"\0"} : trim(${"\0"},"X"))) === null ? ${"\0"} : sprintf("z%sz","".${"\0"}.""))."Z","".((${"\0"}=((${"\0"}=trim($a)) === null ? ${"\0"} : trim(${"\0"},"X"))) === null ? ${"\0"} : sprintf("z%sz","".${"\0"}.""))."")?>', (string) $source);
        $this->assertEquals('ZzYZzZ', eval("\$a=' XYZ ';ob_start();?>$source<?php return ob_get_clean() ?>"));

        $fname = '\\' . __NAMESPACE__ . '\\increment';
        function increment($v)
        {
            $v++;
            return $v;
        }

        $source = new Source('<?= $a & ' . $fname . ' & ' . $fname . ' & ' . $fname . ' | ' . $fname . ' ?>');
        $rewrite($source, '$_', ['|', '&'], [], []);
        $this->assertEquals('1', eval("\$a=null;ob_start();?>$source<?php return ob_get_clean() ?>"));

        $source = new Source('<?= $a >> trim >> trim($rrr, "X") >> sprintf("z%sz", "$rrr") >> sprintf("Z{$rrr}Z", "$rrr") ?>');
        $rewrite($source, '$rrr', ['>>', null], [], []);
        $this->assertEquals('<?=sprintf("Z".sprintf("z%sz","".trim(trim($a),"X")."")."Z","".sprintf("z%sz","".trim(trim($a),"X")."")."")?>', (string) $source);
    }

    function test_rewriteModifier_namespace()
    {
        /** @see RewriteWrapper::rewriteModifier() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteModifier');

        $source = new Source('<?= $value | \\globaled | spaced | \\fully\\qualified ?>');
        $rewrite($source, '$_', ['|', null], [], []);
        $this->assertEquals('<?=\\fully\\qualified(spaced(\\globaled($value)))?>', (string) $source);

        $source = new Source('<?= $value | \\globaled | spaced | \\fully\\qualified ?>');
        $rewrite($source, '$_', ['|', null], ['\\template'], []);
        $this->assertEquals('<?=\\fully\\qualified(\\template\\spaced(\\globaled($value)))?>', (string) $source);

        $source = new Source('<?= $value | f ?>');
        $rewrite($source, '$_', ['|', null], ['\\hoge', '\\fuga'], []);
        $this->assertEquals('<?=\hoge\f($value)?>', (string) $source);

        $source = new Source('<?= $value | f ?>');
        $rewrite($source, '$_', ['|', null], ['\\fuga', '\\hoge'], []);
        $this->assertEquals('<?=\fuga\f($value)?>', (string) $source);
    }

    function test_rewriteModifier_class()
    {
        /** @see RewriteWrapper::rewriteModifier() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteModifier');

        $source = new Source('<?= $value | method ?>');
        $rewrite($source, '$_', ['|', null], [], []);
        $this->assertEquals('<?=method($value)?>', (string) $source);

        $source = new Source('<?= $value | method ?>');
        $rewrite($source, '$_', ['|', null], [], ['hoge\\T', 'fuga\\T']);
        $this->assertEquals('<?=\\hoge\\T::method($value)?>', (string) $source);

        $source = new Source('<?= $value | method ?>');
        $rewrite($source, '$_', ['|', null], [], ['fuga\\T', 'hoge\\T']);
        $this->assertEquals('<?=\\fuga\\T::method($value)?>', (string) $source);
    }

    function test_rewriteModifier_default()
    {
        /** @see RewriteWrapper::rewriteModifier() */
        $rewrite = $this->publishMethod(new RewriteWrapper(), 'rewriteModifier');

        $source = new Source('<?= $value | funcA(1) ?>');
        $rewrite($source, '$_', ['|', null], [], []);
        $this->assertEquals('<?=funcA($value,1)?>', (string) $source);

        $source = new Source('<?= $value | funcA($_, 1) ?>');
        $rewrite($source, '$_', ['|', null], [], []);
        $this->assertEquals('<?=funcA($value,1)?>', (string) $source);

        $source = new Source('<?= $value | funcA($_, 1) ?? "default1" | funcB ?? "default2" ?>');
        $rewrite($source, '$_', ['|', null], [], []);
        $this->assertEquals('<?=funcB(funcA($value,1) ?? "default1") ?? "default2"?>', (string) $source);
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

        $source = new Source("<?= 'a' ?><?= 'b' ?> <?= 'c' ?>\n");
        $rewrite($source, '', '');
        $this->assertEquals("<?='a'?><?='b'?> <?='c'?>\n", (string) $source);
    }
}
