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
        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?php ', 1), $source[0]);
        $this->assertEquals(Token::instance(T_STRING, 'C', 1), $source[9]);

        // ArrayAccess#offsetSet
        $this->assertFalse(isset($source[10]));
        $source[] = 'hoge';
        $this->assertTrue(isset($source[10]));
        $source[1] = 'hoge';
        $this->assertEquals('hoge', $source[1]);

        // ArrayAccess#offsetUnset
        unset($source[10]);
        $this->assertFalse(isset($source[10]));
        unset($source[1]);
        $this->assertFalse(isset($source[9]));

        // IteratorAggregate
        $this->assertEquals([
            Token::instance(T_OPEN_TAG, "<?php ", 1),
            Token::instance(T_WHITESPACE, " ", 1),
            Token::instance(-1, "+", 1),
            Token::instance(T_WHITESPACE, " ", 1),
            Token::instance(T_STRING, "B", 1),
            Token::instance(T_WHITESPACE, " ", 1),
            Token::instance(-1, "+", 1),
            Token::instance(T_WHITESPACE, " ", 1),
            Token::instance(T_STRING, "C", 1),
        ], iterator_to_array($source));

        // Countable
        $this->assertCount(9, $source);
    }

    function test_first_last_both()
    {
        $source = new Source('<?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";');

        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?php ', 1), $source->first());
        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?php ', 1), $source->first());

        $this->assertEquals(Token::instance(-1, ';', null), $source->last());
        $this->assertEquals(Token::instance(-1, ';', null), $source->last());

        $this->assertEquals([
            Token::instance(T_OPEN_TAG, '<?php ', 1),
            Token::instance(-1, ';', null),
        ], $source->both());

        $this->assertEquals('<?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";', (string) $source);

        $source = new Source('');

        $this->assertEquals(null, $source->first());
        $this->assertEquals(null, $source->first());

        $this->assertEquals(null, $source->last());
        $this->assertEquals(null, $source->last());

        $this->assertEquals([
            null,
            null,
        ], $source->both());

        $this->assertEquals('', (string) $source);
    }

    function test_shift_pop_shrink()
    {
        $source = new Source('<?php $var = "prefix-" . "-middle-" . __FUNCTION__ . "-suffix";');

        $this->assertEquals(Token::instance(T_OPEN_TAG, '<?php ', 1), $source->shift());
        $this->assertEquals(Token::instance(T_VARIABLE, '$var', 1), $source->shift());

        $this->assertEquals(Token::instance(-1, ';', null), $source->pop());
        $this->assertEquals(Token::instance(T_CONSTANT_ENCAPSED_STRING, '"-suffix"', 1), $source->pop());

        $this->assertEquals([
            Token::instance(-1, '=', 1),
            Token::instance(-1, ".", 1),
        ], $source->shrink());

        $this->assertEquals(' "prefix-" . "-middle-" . __FUNCTION__ ', (string) $source);

        $this->assertEquals([
            Token::instance(T_CONSTANT_ENCAPSED_STRING, '"prefix-"', 1),
            Token::instance(T_FUNC_C, "__FUNCTION__", 1),
        ], $source->shrink());

        $this->assertEquals(' . "-middle-" . ', (string) $source);

        $this->assertEquals([
            Token::instance(-1, ".", 1),
            Token::instance(-1, ".", 1),
        ], $source->shrink());

        $this->assertEquals(' "-middle-" ', (string) $source);

        $this->assertEquals([
            Token::instance(T_CONSTANT_ENCAPSED_STRING, '"-middle-"', 1),
            null,
        ], $source->shrink());

        $this->assertEquals('', (string) $source);

        $this->assertNull($source->shift());
        $this->assertNull($source->pop());

        $this->assertEquals('', (string) $source);
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
        $this->assertEquals('"prefix-".__FUNCTION__', (string) $matched[0]);
    }

    function test_match_multi()
    {
        $source = new Source('<?php $var = A . A . A . A . A;');
        $matched = $source->match(['A', '.', 'A']);
        $this->assertCount(4, $matched);
        $this->assertEquals('A.A', (string) $matched[0]);
        $this->assertEquals('A.A', (string) $matched[1]);
        $this->assertEquals('A.A', (string) $matched[2]);
        $this->assertEquals('A.A', (string) $matched[3]);
    }

    function test_match_any()
    {
        $source = new Source('<?php $var = "prefix-" . "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_ANY, '"-suffix"']);
        $this->assertEquals('"prefix-"."-suffix"', (string) $matched[0]);

        $source = new Source('<?php $var = "prefix-" , "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_ANY, '"-suffix"']);
        $this->assertEquals('"prefix-","-suffix"', (string) $matched[0]);

        $source = new Source('<?php $var = "prefix-" "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_ANY, '"-suffix"']);
        $this->assertEmpty($matched);
    }

    function test_match_many0()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_MANY0, '"-suffix"']);
        $this->assertEquals('"prefix-".__FUNCTION__."-suffix"', (string) $matched[0]);

        $source = new Source('<?= $this->begin() + $this->hoge() + $this->end()');
        $matched = $source->match(['$this', '->', 'begin', Source::MATCH_MANY0, '->', 'end']);
        $this->assertEquals('$this->begin()+$this->hoge()+$this->end', (string) $matched[0]);

        $source = new Source('<?php $var = "prefix-" "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_MANY0, '"-suffix"']);
        $this->assertEquals('"prefix-""-suffix"', (string) $matched[0]);
    }

    function test_match_many1()
    {
        $source = new Source('<?php $var = "prefix-" . __FUNCTION__ . "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_MANY1, '"-suffix"']);
        $this->assertEquals('"prefix-".__FUNCTION__."-suffix"', (string) $matched[0]);

        $source = new Source('<?= $this->begin() + $this->hoge() + $this->end()');
        $matched = $source->match(['$this', '->', 'begin', Source::MATCH_MANY1, '->', 'end']);
        $this->assertEquals('$this->begin()+$this->hoge()+$this->end', (string) $matched[0]);

        $source = new Source('<?php $var = "prefix-" "-suffix";');
        $matched = $source->match(['"prefix-"', Source::MATCH_MANY1, '"-suffix"']);
        $this->assertEmpty($matched);
    }

    function test_match_many_only()
    {
        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match([Source::MATCH_MANY0]);
        $this->assertEquals('<?php $var="prefix-".__FUNCTION__."-suffix";', (string) $matched[0]);

        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match([Source::MATCH_MANY1]);
        $this->assertEquals('<?php $var="prefix-".__FUNCTION__."-suffix";', (string) $matched[0]);
    }

    function test_match_many_se()
    {
        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match(['$var', Source::MATCH_MANY0]);
        $this->assertEquals('$var="prefix-".__FUNCTION__."-suffix";', (string) $matched[0]);
        $matched = $source->match([Source::MATCH_MANY0, T_FUNC_C]);
        $this->assertEquals('<?php $var="prefix-".__FUNCTION__', (string) $matched[0]);

        $source = new Source('<?php $var="prefix-".__FUNCTION__."-suffix";');
        $matched = $source->match(['$var', Source::MATCH_MANY1]);
        $this->assertEquals('$var="prefix-".__FUNCTION__."-suffix";', (string) $matched[0]);
        $matched = $source->match([Source::MATCH_MANY1, T_FUNC_C]);
        $this->assertEquals('<?php $var="prefix-".__FUNCTION__', (string) $matched[0]);
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
            return ['A . ', [-1, $tokens[0]->token], ' . Z'];
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
        $source->replace([Source::MATCH_MANY1], function ($tokens) {
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
