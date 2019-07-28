<html lang="ja">
<head>
    <title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>
</head>
<body>
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
