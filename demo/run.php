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
}

$renderer = new \ryunosuke\NightDragon\Renderer([
    'debug'         => true,
    'constFilename' => __DIR__ . '/compiles/constant.php',
    'compileDir'    => __DIR__ . '/compiles',
    'defaultClass'  => Modifier::class,
    'nofilter'      => '@',
]);

$renderer->assign([
    // グローバルにアサインする変数
    'SiteName' => 'サイト名',
]);

echo $renderer->render(__DIR__ . '/action.phtml', [
    // テンプレートにアサインする変数
    'string'    => "this's title",
    'multiline' => "line1\nline2\nline3",
    'array'     => ['hoge' => 'HOGE', 'fuga' => ['X', 'Y', 'Z']],
    'object'    => (object) ['hoge' => 'HOGE', 'fuga' => ['X', 'Y', 'Z']],
]);
