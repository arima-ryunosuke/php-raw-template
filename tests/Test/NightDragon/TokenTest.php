<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\Token;

class TokenTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_instance()
    {
        $this->assertEquals([
            'id'   => ord('<'),
            'text' => '<',
            'line' => -1,
            'pos'  => -1,
        ], (array) Token::instance('<'));

        $this->assertEquals([
            'id'   => T_OPEN_TAG,
            'text' => '<?',
            'line' => 2,
            'pos'  => -1,
        ], (array) Token::instance(T_OPEN_TAG, '<?', 2));

        $this->assertEquals([
            'id'   => T_OPEN_TAG,
            'text' => '<?',
            'line' => 2,
            'pos'  => -1,
        ], (array) Token::instance([T_OPEN_TAG, '<?', 2]));

        $token = Token::instance(T_OPEN_TAG, '<?', 2);
        $this->assertSame($token, Token::instance($token));

        $this->assertException(\InvalidArgumentException::class, [Token::class, 'instance'], null);
    }

    function test___toString()
    {
        $token = Token::instance([T_WHITESPACE, '$var', null]);
        $this->assertEquals('$var', $token->__toString());
        $this->assertEquals('$var', "$token");
    }

    function test_equals()
    {
        $token = Token::instance([T_OPEN_TAG, '<?']);

        $this->assertTrue($token->is([T_OPEN_TAG => '<?']));
        $this->assertTrue($token->is([T_OPEN_TAG => '<?', -1 => 'hoge']));
        $this->assertTrue($token->is([T_OPEN_TAG, '<?php']));
        $this->assertTrue($token->is([T_CLOSE_TAG, '<?']));
        $this->assertFalse($token->is(false));
        $this->assertTrue($token->is(T_OPEN_TAG));
        $this->assertTrue($token->is('<?'));
        $this->assertTrue($token->is($token));
        $this->assertTrue($token->is(function ($v) { return true; }));
        $this->assertTrue($token->is(true));

        $this->assertFalse($token->is(false));
        $this->assertFalse($token->is(T_CLOSE_TAG));
        $this->assertFalse($token->is('hoge'));
        $this->assertFalse($token->is(Token::instance([T_OPEN_TAG, '<?php'])));
        $this->assertFalse($token->is(function ($v) { return false; }));
        $this->assertFalse($token->is(null));
    }
}
