<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table): void {
            $table->string('category')->nullable();
            $table->string('progress_unit')->default('lessons');
            $table->unsignedInteger('total_lessons')->nullable();
            $table->unsignedInteger('completed_lessons')->default(0);
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->decimal('studied_hours', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->date('next_review_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
        DB::table('cursos')->whereIn('status', ['completed', 'in_review'])
            ->update(['completed_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);
        Schema::table('curso_files', function (Blueprint $table): void {
            $table->string('kind')->default('material');
        });
    }

    public function down(): void
    {
        Schema::table('curso_files', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
        Schema::table('cursos', function (Blueprint $table): void {
            $table->dropColumn(['category', 'progress_unit', 'total_lessons', 'completed_lessons', 'duration_hours', 'studied_hours', 'notes', 'rating', 'next_review_at', 'completed_at']);
        });
    }
};
