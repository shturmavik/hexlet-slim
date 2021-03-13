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

        $page = $request->getQueryParam('page') ?? '0';
        $slice = in_array($page, ['0', '1']) ? 0 : $page * 5 - 5;
        $users = array_slice($users, $slice, 5);

        $params = ['users' => $users, 'term' => $term, 'flash' => $messages, 'page' => ['prev'=>$page-1, 'next'=>$page+1]];
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
    function ($request, $response, $args) use ($repo) {
        $user = $repo->find($args['nickname']);
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

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router, $repo) {
    $id = $args['id'];
    $repo->destroy($id);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withRedirect($router->urlFor('users'));
});

$app->get(
    '/',
    function ($request, $response) use ($router) {
        $router->urlFor('users'); // /users
        $router->urlFor('user', ['id' => 4]); // /users/4
        return $response->write('Welcome to Slim!');
    }
);

$app->run();
