<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dialog_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('created_at');
            $table->index(['to_user_id', 'from_user_id', 'read_at'], 'dialog_messages_unread_lookup_idx');
        });

        Schema::create('dialog_counters', function (Blueprint $table) {
            $table->uuid('owner_user_id');
            $table->uuid('dialog_user_id');
            $table->unsignedBigInteger('unread_count')->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('owner_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('dialog_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->primary(['owner_user_id', 'dialog_user_id']);
            $table->index(['owner_user_id', 'updated_at'], 'dialog_counters_owner_updated_idx');
        });

        DB::statement("SELECT create_distributed_table('dialog_counters', 'owner_user_id');");

        Schema::create('dialog_counter_sagas', function (Blueprint $table) {
            $table->uuid('owner_user_id');
            $table->uuid('id');
            $table->uuid('dialog_user_id');
            $table->uuid('message_id')->nullable();
            $table->string('event_type', 32);
            $table->integer('delta')->default(0);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('owner_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('dialog_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->primary(['owner_user_id', 'id']);
            $table->index(['status', 'available_at'], 'dialog_counter_sagas_status_available_idx');
        });

        DB::statement("SELECT create_distributed_table('dialog_counter_sagas', 'owner_user_id');");

        DB::statement(
            <<<'SQL'
            INSERT INTO dialog_counters (owner_user_id, dialog_user_id, unread_count, updated_at)
            SELECT
                to_user_id AS owner_user_id,
                from_user_id AS dialog_user_id,
                COUNT(*) AS unread_count,
                NOW() AS updated_at
            FROM dialog_messages
            WHERE read_at IS NULL
            GROUP BY to_user_id, from_user_id
            SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('dialog_counter_sagas');
        Schema::dropIfExists('dialog_counters');

        Schema::table('dialog_messages', function (Blueprint $table) {
            $table->dropIndex('dialog_messages_unread_lookup_idx');
            $table->dropColumn('read_at');
        });
    }
};
