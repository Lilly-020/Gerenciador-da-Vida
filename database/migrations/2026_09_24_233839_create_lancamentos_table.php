<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single table backs both Entradas and Saídas (distinguished by
     * `type`), since they share almost every field and this lets the
     * dashboard query "income vs. expense" in one pass.
     */
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custo_fixo_id')->nullable()->constrained('custo_fixos')->nullOnDelete();
            $table->string('type'); // entrada | saida
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('realizado'); // previsto | realizado
            $table->boolean('is_recurring_template')->default(false);
            $table->string('recurrence_period')->nullable(); // semanal | mensal | anual
            $table->unsignedTinyInteger('recurrence_day')->nullable();
            $table->foreignId('parent_recurring_id')->nullable()->constrained('lancamentos')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
    }
};
