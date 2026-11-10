<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarefa_categorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
        Schema::table('tarefas', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->constrained('tarefa_categorias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tarefas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('tarefa_categorias');
    }
};
