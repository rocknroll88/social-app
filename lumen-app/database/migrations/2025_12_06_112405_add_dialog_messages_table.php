<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dialog_messages', function (Blueprint $table) {

            // Шард-ключ — выбираем from_user_id
            $table->bigInteger('shard_key');

            // Бизнес-поля
            $table->uuid('id');
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->text('text');
            $table->timestamp('created_at')->useCurrent();

            // Внешние ключи — работают, потому что users = reference table
            $table->foreign('from_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('to_user_id')->references('user_id')->on('users')->onDelete('cascade');

            // Индексы
            $table->index(['from_user_id', 'created_at']);
            $table->index(['to_user_id', 'created_at']);

            // Уникальность сообщений обеспечиваем составным PK
            $table->primary(['shard_key', 'id']);
        });

        // Регистрируем как распределённую таблицу
        DB::statement("SELECT create_distributed_table('dialog_messages', 'shard_key');");
    }

    public function down(): void
    {
        Schema::dropIfExists('dialog_messages');
    }
};
