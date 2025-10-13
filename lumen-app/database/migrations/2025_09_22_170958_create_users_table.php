<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->unique();
            $table->string('first_name', 100)->nullable();
            $table->string('second_name', 100)->nullable();
            $table->date('birthdate')->nullable();
            $table->text('biography')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('password', 255);
            $table->string('token', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
