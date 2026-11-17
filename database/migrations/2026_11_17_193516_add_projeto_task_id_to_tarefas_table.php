<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarefas', function (Blueprint $table): void {
            $table->foreignId('projeto_task_id')->nullable()->constrained('projeto_tasks')->nullOnDelete();
            $table->unique(['user_id', 'projeto_task_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tarefas', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'projeto_task_id']);
            $table->dropConstrainedForeignId('projeto_task_id');
        });
    }
};
