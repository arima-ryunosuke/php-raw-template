<?php

require_once __DIR__ . '/../vendor/autoload.php';

// composer 経由で出力されても邪魔なので抑える
if (getenv('COMPOSER_BINARY')) {
    ob_start(function () { });
}

class Modifier
{
    public static function upper($string)
    {
        return strtoupper($string);
    }

    public static function number($value, $decimals = 0)
    {
        return number_format($value, $decimals);
    }

    public static function returns($value)
    {
        return $value;
    }
}

$renderer = new \ryunosuke\NightDragon\Renderer([
    'debug'              => defined('DEBUG') && DEBUG,
    'constFilename'      => __DIR__ . '/compiles/constant.php',
    'compileDir'         => __DIR__ . '/compiles',
    'defaultClass'       => Modifier::class,
    'compatibleShortTag' => true,
]);

$renderer->assign([
    // グローバルにアサインする変数
    'SiteName' => 'サイト名',
]);

echo $renderer->render(__DIR__ . '/action.phtml', [
    // テンプレートにアサインする変数
    'null'      => null,
    'float'     => 12345.6789,
    'string'    => "This's Title",
    'multiline' => "line1\nline2\nline3",
    'array'     => ['hoge' => 'HOGE', 'fuga' => ['x' => 'X', 'y' => 'Y', 'z' => 'Z']],
    'object'    => (object) ['hoge' => 'HOGE', 'fuga' => ['x' => 'X', 'y' => 'Y', 'z' => 'Z']],
    'closure'   => function ($arg) { return 'closure' . $arg; },
]);
