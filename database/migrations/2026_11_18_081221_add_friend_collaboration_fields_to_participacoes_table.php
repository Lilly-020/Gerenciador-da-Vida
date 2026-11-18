<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participacoes', function (Blueprint $table): void {
            $table->foreignId('sonho_id')->nullable()->constrained('sonhos')->cascadeOnDelete();
            $table->foreignId('tarefa_id')->nullable()->constrained('tarefas')->cascadeOnDelete();
            $table->index(['participant_id', 'tipo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('participacoes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sonho_id');
            $table->dropConstrainedForeignId('tarefa_id');
            $table->dropIndex(['participant_id', 'tipo', 'status']);
        });
    }
};
