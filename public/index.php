<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use App\Validator;
use Slim\Factory\AppFactory;
use function Symfony\Component\String\s;
use Slim\Middleware\MethodOverrideMiddleware;

$repo = new App\UserRepository();
$container = new Container();
$container->set(
    'renderer',
    function () {
        // Параметром передается базовая директория, в которой будут храниться шаблоны
        return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    }
);
$container->set(
    'flash',
    function () {
        return new \Slim\Flash\Messages();
    }
);

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get(
    '/users',
    function ($request, $response) use ($repo) {
        $messages = $this->get('flash')->getMessages();
        $users = $repo->all();

        $term = $request->getQueryParam('term');
        $users = collect($users)->filter(
            fn($user) => empty($term) ? true : s($user['nickname'])->ignoreCase()->startsWith($term)
        )->toArray();

        $page = $request->getQueryParam('page') ?? '1';
        $slice = in_array($page, ['0', '1']) ? 0 : $page * 5 - 5;
        $users = array_slice($users, $slice, 5);

        $params = [
            'users' => $users,
            'term'  => $term,
            'flash' => $messages,
            'page'  => ['prev' => $page - 1, 'next' => $page + 1]
        ];
        $this->get('renderer')->setLayout('layout.php');
        return $this->get('renderer')->render($response, 'users/index.phtml', $params);
    }
)->setName('users');

$app->get(
    '/users/new',
    function ($request, $response) {
        $params = [
            'user'   => ['nickname' => '', 'email' => ''],
            'errors' => []
        ];
        return $this->get('renderer')->render($response, "users/new.phtml", $params);
    }
)->setName('user_new');

$app->get(
    '/users/{id}',
    function ($request, $response, $args) use ($repo) {
        $user = $repo->find($args['id']);
        $params = ['nickname' => ''];
        if (!$user) {
            return $this->get('renderer')->render($response->withStatus(404), 'users/show.phtml', $params);
        }
        $params = ['nickname' => $user['nickname']];
        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
)->setName('user');

$router = $app->getRouteCollector()->getRouteParser();

$app->post(
    '/users',
    function ($request, $response) use ($repo, $router) {
        $validator = new Validator();
        $user = $request->getParsedBodyParam('user');
        $errors = $validator->validate($user);
        if (count($errors) === 0) {
            $userId = $repo->save($user);
            $this->get('flash')->addMessage('success', 'Добавлен пользователь');
            return $response->withRedirect($router->urlFor('users'), 302);
        }
        $params = [
            'user'   => $user,
            'errors' => $errors
        ];
        return $this->get('renderer')->render($response, "users/new.phtml", $params)->withStatus(422);
    }
);

$app->get(
    '/users/{nickname}/edit',
    function ($request, $response, array $args) use ($repo) {
        $nickname = $args['nickname'];
        $user = $repo->find($nickname);
        $params = [
            'user'   => $user,
            'errors' => []
        ];
        return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    }
)->setName('editUser');

$app->patch(
    '/users/{nickname}',
    function ($request, $response, array $args) use ($router, $repo) {
        $nickname = $args['nickname'];
        $user = $repo->find($nickname);
        $data = $request->getParsedBodyParam('user');

        $validator = new Validator();
        $errors = $validator->validate($data);

        if (count($errors) === 0) {
            $user['nickname'] = $data['nickname'];
            $user['email'] = $data['email'];
            $user['id'] = $data['id'];

            $this->get('flash')->addMessage('success', 'User has been updated');
            $repo->update($user);
            $url = $router->urlFor('editUser', ['nickname' => $user['nickname']]);
            return $response->withRedirect($url);
        };

        $params = [
            'user'   => $user,
            'errors' => $errors
        ];

        $response = $response->withStatus(422);
        return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    }
);

$app->delete(
    '/users/{id}',
    function ($request, $response, array $args) use ($router, $repo) {
        $id = $args['id'];
        $repo->destroy($id);
        $this->get('flash')->addMessage('success', 'User has been deleted');
        return $response->withRedirect($router->urlFor('users'));
    }
);


$app->get(
    '/cart',
    function ($request, $response) {
        $cart = json_decode($request->getCookieParam('cart', json_encode([])), true);
        $params = [
            'cart' => $cart
        ];
        return $this->get('renderer')->render($response, 'cart/index.phtml', $params);
    }
);

$app->post(
    '/cart/cart-items',
    function ($request, $response) {
        // Информация о добавляемом товаре
        $product = $request->getParsedBodyParam('item');
        $product['count'] = 1;
        // Данные корзины
        $cart = json_decode($request->getCookieParam('cart', json_encode([])), true);

        $id = $product['id'];
        if (!isset($cart[$id])) {
            $cart[$id] = $product;
        } else {
            ++$cart[$id]['count'];
        }

        // Кодирование корзины
        $encodedCart = json_encode($cart);

        // Установка новой корзины в куку
        return $response->withHeader('Set-Cookie', "cart={$encodedCart}")
            ->withRedirect('/cart');
    }
);

$app->delete(
    '/cart/cart-items',
    function ($request, $response, array $args) {
        return $response->withHeader('Set-Cookie', "cart={}")
            ->withRedirect('/cart');
    }
);

$users = [
    ['name' => 'admin', 'passwordDigest' => hash('sha256', 'secret')],
    ['name' => 'mike', 'passwordDigest' => hash('sha256', 'superpass')],
    ['name' => 'kate', 'passwordDigest' => hash('sha256', 'strongpass')]
];

$app->get('/', function($request, $response) {
    $messages = $this->get('flash')->getMessages();
    $params = [
        'user'=> $_SESSION['user'] ?? ['name'=>''],
        'flash'=> $messages
    ];
    $this->get('renderer')->setLayout('layout.php');
    return $this->get('renderer')->render($response, 'index.phtml', $params);
});

$app->post('/session', function($request, $response) use ($users) {
    $user = $request->getParsedBodyParam('user');
    $findedUser = collect($users)->firstWhere('name', $user['name']);

    if (!$findedUser || $findedUser['passwordDigest'] !== hash('sha256',$user['password'])) {
        $this->get('flash')->addMessage('error', 'Wrong password or name.');
        return $response->withRedirect('/', 302);
    }
    $_SESSION['user'] = $user;
    unset($_SESSION['user']['password']);
    return $response->withRedirect('/');
});

$app->delete(
    '/session',
    function ($request, $response) {
        session_destroy();
        return $response->withRedirect('/');
    }
);
$app->run();
