<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projetos', function (Blueprint $table): void {
            $table->text('details')->nullable();
            $table->string('priority')->default('medium');
            $table->boolean('archived')->default(false);
        });
        Schema::table('projeto_tasks', function (Blueprint $table): void {
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('projeto_tasks')->cascadeOnDelete();
            $table->date('due_at')->nullable();
            $table->string('goal_type')->default('checklist');
            $table->decimal('target_value', 14, 2)->nullable();
            $table->decimal('current_value', 14, 2)->default(0);
            $table->string('unit', 30)->nullable();
        });
        Schema::create('projeto_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projeto_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('projeto_task_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('projeto_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projeto_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->timestamps();
        });
        Schema::create('projeto_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projeto_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projeto_files');
        Schema::dropIfExists('projeto_activities');
        Schema::dropIfExists('projeto_comments');
        Schema::table('projeto_tasks', function (Blueprint $table): void {
            $table->dropForeign(['assignee_id']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['assignee_id', 'parent_id', 'due_at', 'goal_type', 'target_value', 'current_value', 'unit']);
        });
        Schema::table('projetos', function (Blueprint $table): void {
            $table->dropColumn(['details', 'priority', 'archived']);
        });
    }
};
