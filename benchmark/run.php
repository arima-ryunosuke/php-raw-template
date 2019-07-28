<?php

/**
 * 実装の参考にしたり比較したりで丁度いいので dev モードで各種エンジンが入るようにしている
 *
 * ベンチを取ると Twig が妙に速いけど、多分クラス化してるから読み込みが一切発生しないんだと思う。
 * つまり「同じファイルを何度もレンダリング」は Twig が有利。
 *
 * 1度実行しておかないと（compiles を作成しないと）night-dragon と blade が異常に遅い。
 * 暖機的にベンチ外で1度は実行しているはずだけど原因不明。
 */

use function ryunosuke\NightDragon\ansi_colorize;
use function ryunosuke\NightDragon\benchmark;

require_once __DIR__ . '/../vendor/autoload.php';

$templates = __DIR__ . '/templates';
$compiles = __DIR__ . '/compiles';

$native = (function () use ($templates) {
    return function () use ($templates) {
        ob_start();
        extract(func_get_arg(1));
        require "$templates/native/" . func_get_arg(0);
        return ob_get_clean();
    };
})();

$renderer = (function () use ($templates, $compiles) {
    $renderer = new \ryunosuke\NightDragon\Renderer([
        'compileDir' => $compiles,
    ]);
    return function ($file, $vars) use ($renderer, $templates) {
        return $renderer->render("$templates/night-dragon/$file", $vars);
    };
})();

$smarty = (function () use ($templates, $compiles) {
    $smarty = new \Smarty();
    $smarty->escape_html = true;
    $smarty->setCompileDir($compiles);
    $smarty->setTemplateDir("$templates/smarty");
    return function ($file, $vars) use ($smarty) {
        return $smarty->assign($vars)->fetch($file);
    };
})();

$twig = (function () use ($templates, $compiles) {
    $loader = new \Twig\Loader\FilesystemLoader("$templates/twig");
    $cache = new \Twig\Cache\FilesystemCache($compiles);
    $twig = new \Twig\Environment($loader, [
        'cache'            => $cache,
        'strict_variables' => true,
    ]);
    return function ($file, $vars) use ($twig) {
        return $twig->render($file, $vars);
    };
})();

$blade = (function () use ($templates, $compiles) {
    $blade = new \Jenssegers\Blade\Blade("$templates/blade", $compiles);
    return function ($file, $vars) use ($blade) {
        return $blade->render($file, $vars);
    };
})();

$plates = (function () use ($templates) {
    $engine = new \League\Plates\Engine("$templates/plates", 'phtml');
    return function ($file, $vars) use ($engine) {
        return $engine->render($file, $vars);
    };
})();

$vars = [
    'title' => "this's title",
    'value' => "<b>this is bold</b>",
    'array' => [1, 2, 3, '<b>b</b>'],
    'ex'    => new \Exception('<b>b</b>'),
];

echo ansi_colorize("rendering single template.\n", 'yellow');
benchmark([
    'native'       => function ($vars) use ($native) { return $native('plain.php', $vars); },
    'night-dragon' => function ($vars) use ($renderer) { return $renderer('plain.phtml', $vars); },
    'smarty'       => function ($vars) use ($smarty) { return $smarty('plain.tpl', $vars); },
    'twig'         => function ($vars) use ($twig) { return $twig('plain.twig', $vars); },
    'blade'        => function ($vars) use ($blade) { return $blade('plain', $vars); },
    'plates'       => function ($vars) use ($plates) { return $plates('plain', $vars); },
], [$vars]);

echo ansi_colorize("rendering layout template.\n", 'yellow');
benchmark([
    'native'       => function ($vars) use ($native) { return $native('child.php', $vars); },
    'night-dragon' => function ($vars) use ($renderer) { return $renderer('child.phtml', $vars); },
    'smarty'       => function ($vars) use ($smarty) { return $smarty('child.tpl', $vars); },
    'twig'         => function ($vars) use ($twig) { return $twig('child.twig', $vars); },
    'blade'        => function ($vars) use ($blade) { return $blade('child', $vars); },
    'plates'       => function ($vars) use ($plates) { return $plates('child', $vars); },
], [$vars]);
