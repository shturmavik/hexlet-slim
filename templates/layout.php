<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slim Tutorial</title>
</head>
<body>

<?php if (isset($_SESSION['user'])):?>
<header class="text-gray-100 bg-gray-900 body-font shadow w-full">
    <div class="container mx-auto flex flex-wrap p-5 flex-col md:flex-row items-center">
        <nav class="flex lg:w-2/5 flex-wrap items-center text-base md:ml-auto">
            <a
                class="mr-5 cursor-pointer border-b border-transparent hover:border-indigo-600" href="/users">Пользователи</a>
            <a
                class="mr-5 cursor-pointer border-b border-transparent hover:border-indigo-600" href="/users/new">Добавить пользователя</a>
            <a
                class="mr-5 hover:text-gray-900 cursor-pointer border-b border-transparent hover:border-indigo-600" href="/cart">Корзина</a>
        </nav>
    </div>
</header>
<?php endif ?>

<div class="flex flex-col h-screen bg-gray-100">
    <div class="flex-grow">
        <?= $content ?>
    </div>
    <?= $this->fetch('footer.php', ['year' => date('Y')]) ?>
</div>
</body>
</html>
