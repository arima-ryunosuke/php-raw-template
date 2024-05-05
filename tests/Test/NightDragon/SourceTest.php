<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\Source;
use ryunosuke\NightDragon\Token;

class SourceTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_interface()
    {
        $source = new Source('<?php A + B + C');

        // ArrayAccess#offsetGet
        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?php ', 1, 0), $source[0]);
        $this->assertEquals(Token::instance(T_STRING, 'C', 1, 14), $source[5]);

        // ArrayAccess#offsetSet
        $this->assertFalse(isset($source[6]));
        $source[] = Token::instance(T_STRING, 'hoge', 1);
        $this->assertTrue(isset($source[6]));
        $source[1] = Token::instance(T_STRING, 'hoge', 1);
        $this->assertEquals('hoge', $source[1]);

        // ArrayAccess#offsetUnset
        unset($source[6]);
        $this->assertFalse(isset($source[6]));
        unset($source[1]);
        $this->assertFalse(isset($source[5]));

        // IteratorAggregate
        $this->assertEquals([
            Token::instance(T_OPEN_TAG, "<?php ", 1, 0),
            Token::instance(ord('+'), "+", 1, 8),
            Token::instance(T_STRING, "B", 1, 10),
            Token::instance(ord('+'), "+", 1, 12),
            Token::instance(T_STRING, "C", 1, 14),
        ], iterator_to_array($source));

        // Countable
        $this->assertCount(5, $source);
    }

    function test_short_tag()
    {
        // token_get_all は short_open_tag の影響を受けるので分岐が必要
        if (ini_get('short_open_tag')) {
            $this->markTestSkipped('short_open_tag is disabled');
        }

        $source = new Source('<? $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";', Source::SHORT_TAG_REPLACE);
        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?pHP ', 1, 0), $source[0]);
        $this->assertEquals('<? $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";', (string) $source);

        $source = new Source('<? $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";', Source::SHORT_TAG_REWRITE);
        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?pHP ', 1, 0), $source[0]);
        $this->assertEquals('<?pHP  $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";', (string) $source);
    }

    function test_line()
    {
        $source = new Source('<?php
$var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";
$func = function () {
    return [
        "a" => "A",
        // ws
        3   => (1 + 2),
    ];
};
');

        $this->assertEquals(Token::instance(T_OPEN_TAG, "<?php\n", 1, 0), $source[0]);
        $this->assertEquals(Token::instance(T_VARIABLE, '$var', 2, 6), $source[1]);
        $this->assertEquals(Token::instance(T_CONSTANT_ENCAPSED_STRING, '"-suffix"', 2, 53), $source[9]);
        $this->assertEquals(Token::instance(T_VARIABLE, '$func', 3, 64), $source[11]);
        $this->assertEquals(Token::instance(ord('{'), '{', 3, 84), $source[16]);
        $this->assertEquals(Token::instance(T_RETURN, 'return', 4, 90), $source[17]);
        $this->assertEquals(Token::instance(ord('['), '[', 4, 97), $source[18]);
        $this->assertEquals(Token::instance(T_CONSTANT_ENCAPSED_STRING, '"a"', 5, 107), $source[19]);
        $this->assertEquals(Token::instance(ord(','), ',', 5, 117), $source[22]);
        $this->assertEquals(Token::instance(T_LNUMBER, '3', 7, 141), $source[24]);
        $this->assertEquals(Token::instance(ord(','), ',', 7, 155), $source[31]);
        $this->assertEquals(Token::instance(ord(']'), ']', 8, 161), $source[32]);
        $this->assertEquals(Token::instance(ord(';'), ';', 8, 162), $source[33]);
        $this->assertEquals(Token::instance(ord('}'), '}', 9, 164), $source[34]);
        $this->assertEquals(Token::instance(ord(';'), ';', 9, 165), $source[35]);
    }

    function test_filter()
    {
        $source = new Source('<?php A + B - C');

        $this->assertEquals([
            Token::instance(ord('+'), '+', 1, 8),
            Token::instance(ord('-'), '-', 1, 12),
        ], $source->filter(['+', '-']));
    }

    function test_shift_pop_shrink()
    {
        $source = new Source('<?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";');

        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?php ', 1, 0), $source->shift());
        $this->assertEquals(Token::instance(T_VARIABLE, '$var', 1, 6), $source->shift());

        $this->assertEquals(Token::instance(ord(';'), ';', 1, 62), $source->pop());
        $this->assertEquals(Token::instance(T_CONSTANT_ENCAPSED_STRING, '"-suffix"', 1, 53), $source->pop());

        $this->assertEquals([
            Token::instance(ord('='), '=', 1, 11),
            Token::instance(ord('.'), ".", 1, 51),
        ], $source->shrink());

        $this->assertEquals(' "prefix-" . "-middle-" . __FUNCTION__ ', (string) $source);

        $this->assertEquals([
            Token::instance(T_CONSTANT_ENCAPSED_STRING, '"prefix-"', 1, 13),
            Token::instance(T_FUNC_C, "__FUNCTION__", 1, 38),
        ], $source->shrink());

        $this->assertEquals(' . "-middle-" . ', (string) $source);

        $this->assertEquals([
            Token::instance(ord('.'), ".", 1, 23),
            Token::instance(ord('.'), ".", 1, 36),
        ], $source->shrink());

        $this->assertEquals(' "-middle-" ', (string) $source);

        $this->assertEquals([
            Token::instance(T_CONSTANT_ENCAPSED_STRING, '"-middle-"', 1, 25),
            null,
        ], $source->shrink());

        $this->assertEquals('', (string) $source);

        $this->assertNull($source->shift());
        $this->assertNull($source->pop());

        $this->assertEquals('', (string) $source);
    }

    function test_strip()
    {
        $source = new Source(' <?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix"; ');
        $this->assertEquals(' <?php $var="prefix-"."-middle-".__FUNCTION__."-suffix";', (string) $source->strip());
    }

    function test_trim()
    {
        $source = new Source(' <?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix"; ');
        $this->assertEquals(' <?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";', (string) $source->trim());
    }

    function test_split()
    {
        $source = new Source('<?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";');
        $sources = $source->split('.');

        $this->assertCount(4, $sources);
        $this->assertEquals('<?php $var = "prefix-" ', (string) $sources[0]);
        $this->assertEquals(' "-middle-" ', (string) $sources[1]);
        $this->assertEquals(' __FUNCTION__ ', (string) $sources[2]);
        $this->assertEquals(' "-suffix";', (string) $sources[3]);
    }

    function test_namespace()
    {
        $source = new Source('<?php statement;');
        $this->assertEquals('', $source->namespace());
        $source = new Source('<?php namespace space;');
        $this->assertEquals('\\space', $source->namespace());
        $source = new Source('<?php namespace name\\space;');
        $this->assertEquals('\\name\\space', $source->namespace());
    }

    function test_match()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $matched = $source->match([T_CONSTANT_ENCAPSED_STRING]);
        $this->assertEquals('"prefix-"', (string) $matched[0]);
        $this->assertEquals('"-suffix"', (string) $matched[1]);

        $matched = $source->match([T_CONSTANT_ENCAPSED_STRING, '.', T_FUNC_C]);
        $this->assertEquals('"prefix-" . __FUNCTION__', (string) $matched[0]);
    }

    function test_match_multi()
    {
        $source = new Source('<?php $var = A . A . A . A . A;');
        $matched = $source->match(['A', '.', 'A']);
        $this->assertCount(4, $matched);
        $this->assertEquals('A . A', (string) $matched[0]);
        $this->assertEquals('A . A', (string) $matched[1]);
        $this->assertEquals('A . A', (string) $matched[2]);
        $this->assertEquals('A . A', (string) $matched[3]);
    }

    function test_match_whitespace()
    {
        $source = new Source('<?php $var = "prefix-".__FUNCTION__."-suffix"; ');
        $matched = $source->match(['$var', true]);
        $this->assertEquals('$var =', (string) $matched[0]);
        $matched = $source->match(['$var', Source::MATCH_MANY]);
        $this->assertEquals('$var = "prefix-".__FUNCTION__."-suffix";', (string) $matched[0]);
    }

    function test_match_any()
    {
        $source = new Source('<?php $var = "prefix-" . "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_ANY, '"-suffix"']);
        $this->assertEquals('"prefix-" . "-suffix"', (string) $matched[0]);

        $source = new Source('<?php $var = "prefix-" , "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_ANY, '"-suffix"']);
        $this->assertEquals('"prefix-" , "-suffix"', (string) $matched[0]);

        $source = new Source('<?php $var = "prefix-" "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_ANY, '"-suffix"']);
        $this->assertEmpty($matched);
    }

    function test_match_many()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_MANY, '"-suffix"']);
        $this->assertEquals('"prefix-" . __FUNCTION__ . "-suffix"', (string) $matched[0]);

        $source = new Source('<?= $this->begin() + $this->hoge() + $this->end()');
        $matched = $source->match(['$this', '->', 'begin', Source::MATCH_MANY, '->', 'end']);
        $this->assertEquals('$this->begin() + $this->hoge() + $this->end', (string) $matched[0]);

        $source = new Source('<?php $var = "prefix-" "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_MANY, '"-suffix"']);
        $this->assertEquals('"prefix-" "-suffix"', (string) $matched[0]);
    }

    function test_match_many_only()
    {
        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match([Source::MATCH_MANY]);
        $this->assertEquals('<?php $var="prefix-".__FUNCTION__."-suffix";', (string) $matched[0]);
    }

    function test_match_many_token()
    {
        $source = new Source('<?php $var = 123 + 1.23 - - "123";');
        $matched = $source->match(['=', [Source::MATCH_MANY => true, T_LNUMBER, T_DNUMBER, '+']]);
        $this->assertEquals('= 123 + 1.23', (string) $matched[0]);
        $matched = $source->match(['=', [Source::MATCH_MANY => true, T_LNUMBER, T_DNUMBER, '+'], [Source::MATCH_MANY => true, '-']]);
        $this->assertEquals('= 123 + 1.23 - -', (string) $matched[0]);
        $matched = $source->match(['=', [Source::MATCH_MANY => true, T_LNUMBER, T_DNUMBER, '+'], '-']);
        $this->assertEquals('= 123 + 1.23 -', (string) $matched[0]);
        $matched = $source->match(['=', [Source::MATCH_MANY => true, T_LNUMBER, T_DNUMBER, '+'], '*']);
        $this->assertEmpty($matched);
    }

    function test_match_many_se()
    {
        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match(['$var', Source::MATCH_MANY]);
        $this->assertEquals('$var="prefix-".__FUNCTION__."-suffix";', (string) $matched[0]);
        $matched = $source->match([Source::MATCH_MANY, T_FUNC_C]);
        $this->assertEquals('<?php $var="prefix-".__FUNCTION__', (string) $matched[0]);
    }

    function test_match_not()
    {
        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match(['=', [Source::MATCH_NOT => true, T_FUNC_C]]);
        $this->assertEquals('="prefix-"', (string) $matched[0]);
        $matched = $source->match(['=', [Source::MATCH_NOT => true, T_FUNC_C, T_CONSTANT_ENCAPSED_STRING]]);
        $this->assertEmpty($matched);
    }

    function test_match_nocapture()
    {
        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match(['=', T_CONSTANT_ENCAPSED_STRING, [Source::MATCH_NOCAPTURE => true, Source::MATCH_MANY => true], T_CONSTANT_ENCAPSED_STRING]);
        $this->assertEquals('="prefix-""-suffix"', (string) $matched[0]);
        $matched = $source->match(['=', [Source::MATCH_NOCAPTURE => true, Source::MATCH_NOT => true, T_FUNC_C]]);
        $this->assertEquals('=', (string) $matched[0]);
    }

    function test_replace_string()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $source->replace([T_CONSTANT_ENCAPSED_STRING], function ($tokens) {
            return 'A . ' . $tokens[0] . ' . Z';
        });
        $this->assertEquals('<?php $var = A . "prefix-" . Z . __FUNCTION__ . A . "-suffix" . Z;', (string) $source);
    }

    function test_replace_string_array()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $source->replace([T_CONSTANT_ENCAPSED_STRING], function ($tokens) {
            return ['A . ', $tokens[0], ' . Z'];
        });
        $this->assertEquals('<?php $var = A . "prefix-" . Z . __FUNCTION__ . A . "-suffix" . Z;', (string) $source);
    }

    function test_replace_arrayarray()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $source->replace([T_CONSTANT_ENCAPSED_STRING], function ($tokens) {
            return ['A . ', [-1, $tokens[0]->text], ' . Z'];
        });
        $this->assertEquals('<?php $var = A . "prefix-" . Z . __FUNCTION__ . A . "-suffix" . Z;', (string) $source);
    }

    function test_replace_token_array()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $source->replace([T_CONSTANT_ENCAPSED_STRING], function ($tokens) {
            return [-1, 'HOGE'];
        });
        $this->assertEquals('<?php $var = HOGE . __FUNCTION__ . HOGE;', (string) $source);
    }

    function test_replace_source()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $source->replace([T_CONSTANT_ENCAPSED_STRING], function ($tokens) {
            return $tokens;
        });
        $this->assertEquals('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";', (string) $source);
    }

    function test_replace_sourcearray()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $source->replace([T_CONSTANT_ENCAPSED_STRING], function ($tokens) {
            return ['S', '/*', $tokens, '*/'];
        });
        $this->assertEquals('<?php $var = S/*"prefix-"*/ . __FUNCTION__ . S/*"-suffix"*/;', (string) $source);
    }

    function test_replace_overlap()
    {
        $source = new Source('<?php ;;;');
        $source->replace([';'], function ($tokens) {
            return 'X';
        });
        $this->assertEquals('<?php XXX', (string) $source);

        $source = new Source('<?php ;;;;;');
        $source->replace([';', ';'], function ($tokens) {
            return 'X';
        });
        $this->assertEquals('<?php XX;', (string) $source);

        $source = new Source('<?php ;;');
        $source->replace([';'], function ($tokens) {
            return ['X', ';'];
        });
        $this->assertEquals('<?php X;X;', (string) $source);
    }

    function test_replace_false()
    {
        $source = new Source('<?php A + B + C');
        $source->replace([Source::MATCH_MANY], function ($tokens) {
            return false;
        });
        $this->assertEquals('<?php A + B + C', (string) $source);
    }

    function test_replace_skip()
    {
        $source = new Source('<?php A');
        $source->replace(['A'], function ($tokens, &$skip) {
            static $count = 0;
            if (++$count === 5) {
                $skip = 999;
            }
            return ['A', 'A'];
        }, 0);
        $this->assertEquals('<?php AAAAAA', (string) $source);
    }
}
