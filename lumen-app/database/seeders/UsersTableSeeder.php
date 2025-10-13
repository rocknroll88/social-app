<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ru_RU');

        $batchSize = 15000;
        $total = 1000000;

        for ($i = 0; $i < $total; $i += $batchSize) {
            $data = [];

            for ($j = 0; $j < $batchSize; $j++) {
                $data[] = [
                    'user_id'    => Str::uuid(),
                    'first_name' => $faker->firstName,
                    'second_name'=> $faker->lastName,
                    'birthdate'  => $faker->date('Y-m-d', '2005-01-01'),
                    'biography'  => $faker->text(200),
                    'city'       => $faker->city,
                    'password'   => Hash::make('password'),
                    'token'      => Str::random(60),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            DB::table('users')->insert($data);
        }
    }
}
