<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarefas', function (Blueprint $table): void {
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
        });
        Schema::create('tarefa_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tarefa_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarefa_files');
        Schema::table('tarefas', function (Blueprint $table): void {
            $table->dropColumn(['end_date', 'notes']);
        });
    }
};
