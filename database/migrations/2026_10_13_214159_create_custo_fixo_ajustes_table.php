<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fixed cost's values for one specific month, when they differ from
     * the cost's own (e.g. a bill that came higher that month).
     */
    public function up(): void
    {
        Schema::create('custo_fixo_ajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custo_fixo_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // first day of the month it applies to
            $table->string('name');
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('due_day');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['custo_fixo_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custo_fixo_ajustes');
    }
};
