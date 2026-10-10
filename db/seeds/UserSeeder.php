<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

use Faker\Factory;
use Phinx\Seed\AbstractSeed;

class UserSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        $password = password_hash('xx', PASSWORD_DEFAULT);

        $data = [
            [
                'email' => 'kairat.jenishev@gmail.com',
                'first_name' => 'Kairat',
                'last_name' => 'Jenihev',
                'password' => $password,
            ],
        ];

        $faker = Factory::create();

        for ($i = 0; $i < 50; $i++) {
            $data[] = [
                'email' => $faker->safeEmail,
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'password' => $password,
            ];
        }

        $this->execute('delete from user_tokens');
        $this->execute('delete from role_user');
        $this->execute('delete from users');

        $this->table('users')
            ->insert($data)
            ->saveData();
    }
}
