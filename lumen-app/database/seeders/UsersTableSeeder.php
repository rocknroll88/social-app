<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->truncate();

        foreach (range(1, 5) as $i) {
            $uuid = Str::uuid()->toString();

            DB::table('users')->insert([
                'user_id' => $uuid,
                'first_name' => "User {$i}",
                'created_at' => Carbon::now(),
                'password'   => Hash::make('password'),
            ]);

            dump("Created user {$i} with UUID: {$uuid}");
        }
    }

//    public function run(): void
//    {
//        $faker = Faker::create('ru_RU');
//
//        $batchSize = 15000;
//        $total = 1000000;
//
//        for ($i = 0; $i < $total; $i += $batchSize) {
//            $data = [];
//
//            for ($j = 0; $j < $batchSize; $j++) {
//                $data[] = [
//                    'user_id'    => Str::uuid(),
//                    'first_name' => $faker->firstName,
//                    'second_name'=> $faker->lastName,
//                    'birthdate'  => $faker->date('Y-m-d', '2005-01-01'),
//                    'biography'  => $faker->text(200),
//                    'city'       => $faker->city,
//                    'password'   => Hash::make('password'),
//                    'token'      => Str::random(60),
//                    'created_at' => Carbon::now(),
//                    'updated_at' => Carbon::now(),
//                ];
//            }
//
//            DB::table('users')->insert($data);
//        }
//    }
}
