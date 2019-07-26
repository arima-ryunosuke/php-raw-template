<?php

namespace ryunosuke\NightDragon;

require_once __DIR__ . '/../vendor/autoload.php';

$src = __DIR__ . "/../src";
$tst = __DIR__ . "/../tests";

// 使用されているものだけ吐き出す（「プレーン・テキストとしてマーク」するとよい）
file_put_contents("$src/functions.php", \ryunosuke\Functions\Transporter::exportNamespace(__NAMESPACE__, false, "$src/NightDragon"));
// ただし、開発中に不便なので tests にすべて吐き出す
file_put_contents("$tst/functions.php", \ryunosuke\Functions\Transporter::exportNamespace(__NAMESPACE__));
