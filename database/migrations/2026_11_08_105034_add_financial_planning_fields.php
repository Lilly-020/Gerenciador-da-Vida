<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_financeiras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('banco');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_date');
            $table->timestamps();
        });
        Schema::create('transferencias_financeiras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('contas_financeiras')->restrictOnDelete();
            $table->foreignId('destination_id')->constrained('contas_financeiras')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('categorias_financeiras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->timestamps();
            $table->unique(['user_id', 'type', 'name']);
        });
        Schema::table('lancamentos', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->constrained('contas_financeiras')->restrictOnDelete();
            $table->string('expense_kind')->default('variavel');
            $table->date('recurrence_end')->nullable();
            $table->unsignedInteger('recurrence_count')->nullable();
            $table->string('recurrence_status')->default('previsto');
            $table->boolean('recurrence_active')->default(true);
            $table->string('occurrence_key')->nullable()->unique();
            $table->uuid('installment_group')->nullable();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->unsignedSmallInteger('installment_count')->nullable();
        });
        Schema::create('lancamento_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lancamento_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('financeiro_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->timestamps();
        });
        Schema::table('investimento_aportes', function (Blueprint $table): void {
            $table->string('kind')->default('aporte');
            $table->foreignId('account_id')->nullable()->constrained('contas_financeiras')->restrictOnDelete();
        });
        Schema::table('investimentos', function (Blueprint $table): void {
            $table->string('rate_mode')->default('fixed');
            $table->decimal('reference_rate', 8, 4)->nullable();
            $table->decimal('ir_rate', 5, 2)->default(0);
            $table->decimal('iof_rate', 5, 2)->default(0);
            $table->decimal('estimated_fees', 14, 2)->default(0);
            $table->decimal('reported_value', 14, 2)->nullable();
            $table->date('reported_at')->nullable();
        });
        Schema::table('participacoes', function (Blueprint $table): void {
            $table->json('allowed_accounts')->nullable();
            $table->json('allowed_categories')->nullable();
            $table->json('allowed_lancamentos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('participacoes', function (Blueprint $table): void {
            $table->dropColumn(['allowed_accounts', 'allowed_categories', 'allowed_lancamentos']);
        });
        Schema::table('investimentos', function (Blueprint $table): void {
            $table->dropColumn(['rate_mode', 'reference_rate', 'ir_rate', 'iof_rate', 'estimated_fees', 'reported_value', 'reported_at']);
        });
        Schema::table('investimento_aportes', function (Blueprint $table): void {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['kind', 'account_id']);
        });
        Schema::dropIfExists('financeiro_activities');
        Schema::dropIfExists('lancamento_files');
        Schema::table('lancamentos', function (Blueprint $table): void {
            $table->dropForeign(['account_id']);
            $table->dropUnique(['occurrence_key']);
            $table->dropColumn(['account_id', 'expense_kind', 'recurrence_end', 'recurrence_count', 'recurrence_status', 'recurrence_active', 'occurrence_key', 'installment_group', 'installment_number', 'installment_count']);
        });
        Schema::dropIfExists('transferencias_financeiras');
        Schema::dropIfExists('categorias_financeiras');
        Schema::dropIfExists('contas_financeiras');
    }
};
