<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            // Citus shard key
            $table->bigInteger('shard_key');

            // Business fields
            $table->uuid('id');
            $table->uuid('author_user_id');
            $table->text('text');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('author_user_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->index('author_user_id');
            $table->index('created_at');

            $table->primary(['shard_key', 'id']);
        });

        // register distributed table
        DB::statement("SELECT create_distributed_table('posts', 'shard_key');");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
