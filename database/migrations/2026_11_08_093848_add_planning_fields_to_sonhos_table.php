<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sonhos', function (Blueprint $table) {
            $table->date('target_date')->nullable();
            $table->decimal('financial_goal', 14, 2)->nullable();
            $table->decimal('saved_amount', 14, 2)->nullable();
            $table->string('priority')->default('medium');
            $table->text('notes')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->boolean('archived')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sonhos', function (Blueprint $table) {
            $table->dropColumn(['target_date', 'financial_goal', 'saved_amount', 'priority', 'notes', 'image_url', 'archived']);
        });
    }
};
