<?php

/**
 * 実装の参考にしたり比較したりで丁度いいので dev モードで各種エンジンが入るようにしている
 *
 * ベンチを取ると Twig が妙に速いけど、多分クラス化してるから読み込みが一切発生しないんだと思う。
 * つまり「同じファイルを何度もレンダリング」は Twig が有利。
 *
 * 本当の意味では web サーバを立ち上げてリクエストで検証するのが最も良いと思うのでそれらしい小細工もしてある。
 *
 * cli:
 * ```
 * php benchmark/run.php
 * ```
 *
 * web:
 * ```
 * php -S 127.0.0.1:3000
 *
 * for engine in native night-dragon twig smarty blade
 * do
 *   printf "%-20s" $engine
 *   ab -c 10 -t 3 http://localhost:3000/benchmark/run.php?engine=$engine 2>/dev/null | grep "Requests per second:"
 * done
 * ```
 */

use function ryunosuke\NightDragon\ansi_colorize;
use function ryunosuke\NightDragon\array_lookup;
use function ryunosuke\NightDragon\benchmark;

require_once __DIR__ . '/../vendor/autoload.php';

define('ENGINES', [
    'native'       => [],
    'night-dragon' => [],
    'smarty'       => [],
    'twig'         => [],
    'blade'        => [],
    'plates'       => [],
]);

if (php_sapi_name() === 'cli') {
    $targets = array_slice($argv, 1) ?: array_keys(ENGINES);
}
else {
    $targets = (array) ($_GET['engine'] ?? array_keys(ENGINES));
}

$templates = __DIR__ . '/templates';
$compiles = __DIR__ . '/compiles';

$engines = [];
foreach ($targets as $target) {
    switch ($target) {
        default:
            throw new \Exception('engine not specified.');
        case 'native':
            $native = (function () use ($templates) {
                return function () use ($templates) {
                    ob_start();
                    extract(func_get_arg(1));
                    require "$templates/native/" . func_get_arg(0);
                    return ob_get_clean();
                };
            })();
            $engines[$target] = [
                'single' => function ($vars) use ($native) { return $native('plain.php', $vars); },
                'layout' => function ($vars) use ($native) { return $native('child.php', $vars); },
            ];
            break;
        case 'night-dragon':
            $renderer = (function () use ($templates, $compiles) {
                $renderer = new \ryunosuke\NightDragon\Renderer([
                    'compileDir' => $compiles,
                ]);
                return function ($file, $vars) use ($renderer, $templates) {
                    return $renderer->render("$templates/night-dragon/$file", $vars);
                };
            })();
            $engines[$target] = [
                'single' => function ($vars) use ($renderer) { return $renderer('plain.phtml', $vars); },
                'layout' => function ($vars) use ($renderer) { return $renderer('child.phtml', $vars); },
            ];
            break;
        case 'smarty':
            $smarty = (function () use ($templates, $compiles) {
                $smarty = new \Smarty();
                $smarty->escape_html = true;
                $smarty->setCompileDir($compiles);
                $smarty->setTemplateDir("$templates/smarty");
                return function ($file, $vars) use ($smarty) {
                    return $smarty->assign($vars)->fetch($file);
                };
            })();
            $engines[$target] = [
                'single' => function ($vars) use ($smarty) { return $smarty('plain.tpl', $vars); },
                'layout' => function ($vars) use ($smarty) { return $smarty('child.tpl', $vars); },
            ];
            break;
        case 'twig':
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
            $engines[$target] = [
                'single' => function ($vars) use ($twig) { return $twig('plain.twig', $vars); },
                'layout' => function ($vars) use ($twig) { return $twig('child.twig', $vars); },
            ];
            break;
        case 'blade':
            $blade = (function () use ($templates, $compiles) {
                $blade = new \Jenssegers\Blade\Blade("$templates/blade", $compiles);
                return function ($file, $vars) use ($blade) {
                    return $blade->render($file, $vars);
                };
            })();
            $engines[$target] = [
                'single' => function ($vars) use ($blade) { return $blade('plain', $vars); },
                'layout' => function ($vars) use ($blade) { return $blade('child', $vars); },
            ];
            break;
        case 'plates':
            $plates = (function () use ($templates) {
                $engine = new \League\Plates\Engine("$templates/plates", 'phtml');
                return function ($file, $vars) use ($engine) {
                    return $engine->render($file, $vars);
                };
            })();
            $engines[$target] = [
                'single' => function ($vars) use ($plates) { return $plates('plain', $vars); },
                'layout' => function ($vars) use ($plates) { return $plates('child', $vars); },
            ];
            break;
    }
}

$vars = [
    'title' => "this's title",
    'value' => "<b>this is bold</b>",
    'array' => [1, 2, 3, '<b>b</b>'],
    'ex'    => new \Exception('<b>b</b>'),
];

if (php_sapi_name() === 'cli') {
    foreach (['single', 'layout'] as $case) {
        echo ansi_colorize("rendering $case template.\n", 'yellow');
        benchmark(array_lookup($engines, $case), [$vars]);
    }
}
else {
    $result = [];
    foreach ($engines as $name => $engine) {
        $name = sprintf("%-14s", $name);
        foreach (['single', 'layout'] as $case) {
            $t = microtime(true);
            $engine[$case]($vars);
            $result[$case][$name] = number_format(microtime(true) - $t, 6);
        }
    }
    asort($result['single']);
    asort($result['layout']);
    header('Content-Type: text/plain');
    echo json_encode($result, JSON_PRETTY_PRINT);
}
