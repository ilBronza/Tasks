<?php

use IlBronza\Tasks\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('tasks.models.task.table'), function (Blueprint $table) {
            $table->foreignUuid('project_id')->default(Project::DEFAULT_ID)->index()
                ->constrained(config('tasks.models.project.table'));
        });
    }

    public function down(): void
    {
        Schema::table(config('tasks.models.task.table'), function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
