<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks__sync_installations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->char('token_hash', 64);
            $table->unsignedBigInteger('revision')->default(0);
        });

        Schema::create('tasks__sync_changes', function (Blueprint $table) {
            $table->uuid('installation_id');
            $table->unsignedBigInteger('revision');
            $table->primary(['installation_id', 'revision']);
            $table->uuid('task_id');
            $table->string('type');
            $table->json('payload');
        });

        Schema::create('tasks__sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id')->index();
            $table->uuid('task_id');
            $table->uuid('event_id');
            $table->string('type');
            $table->json('payload');
        });

        Schema::create('tasks__sync_cursors', function (Blueprint $table) {
            $table->uuid('installation_id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });

        Schema::table(config('tasks.models.task.table'), function (Blueprint $table) {
            $table->uuid('sync_installation_id')->nullable();
            $table->uuid('sync_origin_id')->nullable();
            $table->unique(['sync_installation_id', 'sync_origin_id'], 'tasks_sync_origin_unique');
            $table->string('sync_requester')->nullable();
            $table->string('sync_assignee')->nullable();
            $table->uuid('sync_assignee_key')->nullable();
        });

        Schema::table(config('notes.models.note.table'), function (Blueprint $table) {
            $table->string('task_sync_author')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('notes.models.note.table'), function (Blueprint $table) {
            $table->dropColumn('task_sync_author');
        });
        Schema::table(config('tasks.models.task.table'), function (Blueprint $table) {
            $table->dropUnique('tasks_sync_origin_unique');
            $table->dropColumn([
                'sync_installation_id',
                'sync_origin_id',
                'sync_requester',
                'sync_assignee',
                'sync_assignee_key',
            ]);
        });
        Schema::dropIfExists('tasks__sync_cursors');
        Schema::dropIfExists('tasks__sync_outbox');
        Schema::dropIfExists('tasks__sync_changes');
        Schema::dropIfExists('tasks__sync_installations');
    }
};
