<?php

namespace App;

class Generator
{
    public static function generate($count)
    {
        $numbers = range(1, $count);
        shuffle($numbers);

        $faker = \Faker\Factory::create();
        $faker->seed(1);
        $posts = [];
        for ($i = 0; $i < $count; $i++) {
            $posts[] = [
                'id' => $faker->uuid,
                'nickname' => $faker->name,
                'email' => $faker->email,
            ];
        }

        return $posts;
    }
}

