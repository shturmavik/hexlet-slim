<?php

namespace App;

class UserRepository
{
    private array $users;
    private array $v;

    public function __construct()
    {
        session_start();
        $fileUsers = file_get_contents('./upload/users.txt');
        $this->users = json_decode($fileUsers, true);
        $this->fakerUser = Generator::generate(100);
    }

    public function all()
    {
//        return array_values($_SESSION);
        return array_merge($this->users, $this->fakerUser);
    }

    public function find(string $nickname)
    {
        $user = collect($this->users)->firstWhere('nickname', $nickname);
//        if (!isset($_SESSION[$id])) {
        if (!isset($user)) {
            throw new \Exception("Wrong user nickname: {$nickname}");
        }

//        return $_SESSION[$id];
        return $user;
    }

    public function save(array $item)
    {
        if (empty($item['nickname']) || $item['nickname'] === '') {
            $json = json_encode($item);
            throw new \Exception("Wrong data: {$json}");
        }
        $item['id'] = uniqid();
//        $_SESSION[$item['id']] = $item;
        $users = array_merge($this->all() ?? [], [$item]);
        file_put_contents('./upload/users.txt', json_encode($users));
        return $item['id'];
    }

    public function update(array $item)
    {
        if (empty($item['nickname']) || $item['nickname'] === '') {
            $json = json_encode($item);
            throw new \Exception("Wrong data: {$json}");
        }

        $user = collect($this->users)->map(
            function ($user) use ($item) {
                if ($user['nickname'] === $item['nickname']) {
                    return [
                        'nickname' => $item['nickname'],
                        'email'    => $item['email']
                    ];
                }
                return $user;
            }
        );
        file_put_contents('./upload/users.txt', json_encode($user));
        return $item['id'];
    }

    public function destroy(string $id)
    {
        $user = collect($this->users)->reject(
            function ($user) use ($id) {
                return $user['id'] === $id;
            }
        );
        file_put_contents('./upload/users.txt', json_encode($user));
        return true;
    }
}

