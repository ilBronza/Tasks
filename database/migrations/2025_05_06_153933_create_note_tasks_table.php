<?php

use IlBronza\AccountManager\Models\Role;
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
        Schema::create(config('tasks.models.task.table'), function (Blueprint $table) {
            $table->uuid('id')->primary();

	        $table->string('title')->nullable();
	        $table->text('description')->nullable();

			$table->unsignedBigInteger('user_id')->nullable();
			$table->foreign('user_id')->references('id')->on('users');

	        $table->unsignedBigInteger('assignee_user_id')->nullable();
	        $table->foreign('assignee_user_id')->references('id')->on('users');

	        $table->enum('status', ['open', 'in_progress', 'closed', 'not_manageable'])->nullable();

	        $table->timestamp('start_date')->nullable();
	        $table->timestamp('end_date')->nullable();

	        $table->decimal('minutes')->nullable();
	        $table->string('commit')->nullable();

            $table->timestamps();
			$table->softDeletes();
        });


        $role = Role::gpc()::make();

        $role->name = 'taskAssignee';
        $role->guard_name = 'web';
        $role->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('tasks.conversation.participantsTable'));
        Schema::dropIfExists(config('tasks.models.task.table'));
    }
};
