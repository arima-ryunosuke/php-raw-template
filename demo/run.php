<?php

require_once __DIR__ . '/../vendor/autoload.php';

// composer 経由で出力されても邪魔なので抑える
if (getenv('COMPOSER_BINARY')) {
    ob_start(function () { });
}

$renderer = new \ryunosuke\NightDragon\Renderer([
    // デバッグ系
    'debug'          => true,
    'defineFilename' => __DIR__ . '/compiles/constant.php',
    'compileDir'     => __DIR__ . '/compiles',
]);

$renderer->assign([
    // グローバルにアサインする変数
    'SiteName' => 'サイト名',
]);

echo $renderer->render(__DIR__ . '/action.phtml', [
    // テンプレートにアサインする変数
    'string' => "this's title",
    'array'  => ['hoge' => 'HOGE', 'fuga' => ['X', 'Y', 'Z']],
    'object' => (object) ['hoge' => 'HOGE', 'fuga' => ['X', 'Y', 'Z']],
]);
