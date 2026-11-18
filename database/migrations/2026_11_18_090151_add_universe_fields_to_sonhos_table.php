<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sonhos', function (Blueprint $table): void {
            $table->string('category')->default('other');
        });
    }

    public function down(): void
    {
        Schema::table('sonhos', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
