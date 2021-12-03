<?php

namespace ryunosuke\Test;

use PHPUnit\Framework\TestCase;

class AbstractTestCase extends TestCase
{
    const TEMPLATE_DIR = __DIR__ . '/files/template';
    const COMPILE_DIR  = __DIR__ . '/files/compile';

    public function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/files/template/function.php';
    }

    /**
     * 例外が投げられたかアサーション
     *
     * @param string|\Exception $e
     * @param callable $callback
     */
    public static function assertException($e, $callback)
    {
        if (is_string($e)) {
            if (class_exists($e)) {
                $ref = new \ReflectionClass($e);
                $e = $ref->newInstanceWithoutConstructor();
            }
            else {
                $e = new \Exception($e);
            }
        }

        $args = array_slice(func_get_args(), 2);
        $message = json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        try {
            $callback(...$args);
        }
        catch (\Throwable $ex) {
            // 型は常に判定
            self::assertInstanceOf(get_class($e), $ex, $message);
            // コードは指定されていたときのみ
            if ($e->getCode() > 0) {
                self::assertEquals($e->getCode(), $ex->getCode(), $message);
            }
            // メッセージも指定されていたときのみ
            if (strlen($e->getMessage()) > 0) {
                self::assertStringContainsString($e->getMessage(), $ex->getMessage(), $message);
            }
            return;
        }
        self::fail(get_class($e) . ' is not thrown.' . $message);
    }

    public static function publishProperty($class, $property)
    {
        $ref = new \ReflectionProperty($class, $property);
        $ref->setAccessible(true);
        return static function () use ($class, $ref) {
            if (func_num_args()) {
                return $ref->isStatic() ? $ref->setValue(func_get_arg(0)) : $ref->setValue($class, func_get_arg(0));
            }
            else {
                return $ref->isStatic() ? $ref->getValue() : $ref->getValue($class);
            }
        };
    }

    public static function publishMethod($class, $method)
    {
        $ref = new \ReflectionMethod($class, $method);
        $ref->setAccessible(true);
        if ($ref->isStatic()) {
            return $ref->getClosure();
        }
        else {
            return $ref->getClosure($class);
        }
    }
}
