<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use function Symfony\Component\String\s;
use App\Validator;

$repo = new App\UserRepository();
$container = new Container();
$container->set(
    'renderer',
    function () {
        // Параметром передается базовая директория, в которой будут храниться шаблоны
        return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
    }
);
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});


$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$users = file_get_contents('./upload/users.txt');

$app->get(
    '/users',
    function ($request, $response) use ($users) {
        $messages = $this->get('flash')->getMessages();
        $users = json_decode($users, true);
        $term = $request->getQueryParam('term');
        $users = collect($users)->filter(
            fn($user) => empty($term) ? true : s($user['nickname'])->ignoreCase()->startsWith($term)
        );
        $params = ['users' => $users, 'term' => $term, 'flash' => $messages];
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
    '/users/{nickname}',
    function ($request, $response, $args) use ($users) {
        $users = json_decode($users, true);
        $user = collect($users)->firstWhere('nickname', $args['nickname']);
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
    function ($request, $response) use ($repo, $router, $users) {
        $validator = new Validator();
        $user = $request->getParsedBodyParam('user');
        $errors = $validator->validate($user);
        if (count($errors) === 0) {
            $userId = $repo->save($user);
            $user['id'] = $userId;
            $users = array_merge( json_decode($users, true) ?? [], [$user]);
//            echo '<pre>';
//            print_r($users);
//            echo '</pre>';
            $this->get('flash')->addMessage('success', 'Добавлен пользователь');
            file_put_contents('./upload/users.txt', json_encode($users));
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
    '/',
    function ($request, $response) use ($router) {
        $router->urlFor('users'); // /users
        $router->urlFor('user', ['id' => 4]); // /users/4

        return $response->write('Welcome to Slim!');
    }
);

$app->run();
