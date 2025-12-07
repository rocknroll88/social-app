<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UsersTableSeeder::class);
        $this->call(PostsSeeder::class);
        $this->call(FriendsSeeder::class);
        $this->call(DialogMessagesSeeder::class);
//        $this->call(PostsTableSeeder::class);
    }
}
