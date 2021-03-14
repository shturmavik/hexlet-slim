<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slim Tutorial</title>
</head>
<body>
<?= $content ?>
<?= $this->fetch('footer.php', ['year' => date('Y')]) ?>
</body>
</html>
