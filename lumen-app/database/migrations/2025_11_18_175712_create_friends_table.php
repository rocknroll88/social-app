<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friends', function (Blueprint $table) {
            // Шард-ключ (обязательный для Citus)
            $table->bigInteger('shard_key');

            $table->uuid('user_id');
            $table->uuid('friend_id');
            $table->timestamp('created_at')->useCurrent();

            // Составной первичный ключ (shard_key обязателен)
            $table->primary(['shard_key', 'user_id', 'friend_id']);

            // Внешние ключи
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('friend_id')->references('user_id')->on('users')->onDelete('cascade');

            // Индекс для обратного поиска
            $table->index('friend_id');
        });

        // регистрация распределённой таблицы
        DB::statement("SELECT create_distributed_table('friends', 'shard_key')");
    }

    public function down(): void
    {
        Schema::dropIfExists('friends');
    }
};
