<?php

namespace ryunosuke\Test\NightDragon;

use ryunosuke\NightDragon\Token;

class TokenTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_instance()
    {
        $this->assertEquals([
            'id'    => Token::UNKNOWN_ID,
            'token' => 'token',
            'line'  => null,
            'name'  => 'UNKNOWN',
        ], (array) Token::instance('token'));

        $this->assertEquals([
            'id'    => T_OPEN_TAG,
            'token' => '<?',
            'line'  => 2,
            'name'  => 'T_OPEN_TAG',
        ], (array) Token::instance(T_OPEN_TAG, '<?', 2));

        $this->assertEquals([
            'id'    => T_OPEN_TAG,
            'token' => '<?',
            'line'  => 2,
            'name'  => 'T_OPEN_TAG',
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

    function test_isWhitespace()
    {
        $this->assertTrue(Token::instance([T_WHITESPACE, ' ', null])->isWhitespace());
        $this->assertFalse(Token::instance([T_OPEN_TAG, ' ', null])->isWhitespace());
    }

    function test_equals()
    {
        $token = Token::instance([T_OPEN_TAG, '<?']);

        $this->assertTrue($token->equals([T_OPEN_TAG => '<?']));
        $this->assertTrue($token->equals([T_OPEN_TAG => '<?', -1 => 'hoge']));
        $this->assertTrue($token->equals([T_OPEN_TAG, '<?php']));
        $this->assertTrue($token->equals([T_CLOSE_TAG, '<?']));
        $this->assertFalse($token->equals(false));
        $this->assertTrue($token->equals(T_OPEN_TAG));
        $this->assertTrue($token->equals('<?'));
        $this->assertTrue($token->equals($token));
        $this->assertTrue($token->equals(function ($v) { return true; }));
        $this->assertTrue($token->equals(true));

        $this->assertFalse($token->equals([T_OPEN_TAG => '<?php']));
        $this->assertFalse($token->equals([T_OPEN_TAG => '<?php', T_CLOSE_TAG => '<?']));
        $this->assertFalse($token->equals([T_CLOSE_TAG, '<?php']));
        $this->assertFalse($token->equals(false));
        $this->assertFalse($token->equals(T_CLOSE_TAG));
        $this->assertFalse($token->equals('hoge'));
        $this->assertFalse($token->equals(Token::instance([T_OPEN_TAG, '<?php'])));
        $this->assertFalse($token->equals(function ($v) { return false; }));
        $this->assertFalse($token->equals(null));
    }
}
