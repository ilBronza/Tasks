<?php

use IlBronza\Tasks\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $projects = config('tasks.models.project.table');
        Schema::create($projects, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table($projects)->insert([
            'id' => Project::DEFAULT_ID,
            'name' => config('tasks.defaultProjectName') ?: config('app.name'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(config('tasks.models.project.table'));
    }
};
