<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The old Notes migration may already have created this table.
        if (Schema::hasTable(config('tasks.conversation.participantsTable')))
            return;

		Schema::create(config('tasks.conversation.participantsTable'), function (Blueprint $table) {
			$table->uuid('task_id');
			$table->unsignedBigInteger('user_id');
			$table->timestamps();
			$table->primary(['task_id', 'user_id']);
			$table->foreign('task_id')->references('id')->on(config('tasks.models.task.table'))->cascadeOnDelete();
			$table->foreign('user_id')->references('id')->on((new (config('auth.providers.users.model')))->getTable())->cascadeOnDelete();
		});
    }

    public function down(): void
    {
        // This table may predate Tasks. Rolling back the extraction must not erase
        // existing participation; it is removed with the task-table migration.
    }
};
