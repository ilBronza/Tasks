<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('tasks.assignments.table'), function (Blueprint $table) {
            $table->id();
            $table->uuid('task_id');
            $table->foreign('task_id')->references('id')->on(config('tasks.models.task.table'))->cascadeOnDelete();
            // Keep identifiers and names even if a user is subsequently removed.
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_name')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('conversation_order');
            $table->timestamp('created_at', 6);
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('tasks.assignments.table'));
    }
};
