<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tarefa_id')->nullable()->constrained('tarefas')->nullOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('color', 20)->default('slate');
            $table->boolean('pinned')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'archived', 'pinned']);
        });
        Schema::create('nota_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nota_id')->constrained('notas')->cascadeOnDelete();
            $table->string('text', 500);
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_items');
        Schema::dropIfExists('notas');
    }
};
