<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quadros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 100)->nullable();
            $table->string('cover', 30)->default('violet');
            $table->string('cover_path')->nullable();
            $table->timestamps();
        });
        Schema::create('quadro_listas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quadro_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('color', 7)->default('#172033');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['quadro_id', 'position']);
        });
        Schema::create('quadro_cartoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quadro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lista_id')->constrained('quadro_listas')->cascadeOnDelete();
            $table->foreignId('legacy_curso_id')->nullable()->unique()->constrained('cursos')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('undefined');
            $table->json('labels')->nullable();
            $table->json('assignee_ids')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->json('legacy_data')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['lista_id', 'position']);
        });
        Schema::create('quadro_membros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quadro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('editor');
            $table->string('status', 10)->default('pending');
            $table->timestamps();
            $table->unique(['quadro_id', 'user_id']);
        });
        Schema::create('quadro_comentarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cartao_id')->constrained('quadro_cartoes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('quadro_comentarios');
        Schema::dropIfExists('quadro_membros');
        Schema::dropIfExists('quadro_cartoes');
        Schema::dropIfExists('quadro_listas');
        Schema::dropIfExists('quadros');
    }
};
