<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('friends', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('friend_id');
            $table->timestamp('created_at')->useCurrent();
            
            // Составной первичный ключ
            $table->primary(['user_id', 'friend_id']);
            
            // Внешние ключи
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('friend_id')->references('user_id')->on('users')->onDelete('cascade');
            
            // Индекс для обратного поиска (кто добавил меня в друзья)
            $table->index('friend_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friends');
    }
};
