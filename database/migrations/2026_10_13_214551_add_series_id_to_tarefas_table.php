<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tasks created together as one repetition ("toda segunda e quarta
     * até...") share a series_id, so they can later be removed together.
     */
    public function up(): void
    {
        Schema::table('tarefas', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('tarefas', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn('series_id');
        });
    }
};
