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
        Schema::table('projeto_comments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projeto_comments', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
