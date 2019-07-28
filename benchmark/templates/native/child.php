<?php
// 読み込むだけだと勝負にならないので無駄に変数出力する
// さらに native だと親テンプレートのディスク IO も存在しないので include する
include __DIR__ . '/parent.php';
?>
<html lang="ja">
<head>
    <title><?= $child ?> | <?= $parent ?>   - <?= htmlspecialchars($title, ENT_QUOTES) ?></title>
</head>
<body>
<?= $parentBody ?>

<?= $childBody ?>

this is <?= htmlspecialchars($value, ENT_QUOTES) ?>

<?= htmlspecialchars($ex->getCode(), ENT_QUOTES) ?>

<?= htmlspecialchars($array[3], ENT_QUOTES) ?>

<?php foreach ($array as $key => $value): ?>
    <?php if ($key === 2): ?>
        <?= htmlspecialchars($value, ENT_QUOTES) ?>

    <?php endif ?>
<?php endforeach ?>
</body>
</html>
