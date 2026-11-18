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
        Schema::table('quadro_cartoes', function (Blueprint $table) {
            $table->json('checklist')->nullable();
            $table->json('attachments')->nullable();
            $table->json('activity')->nullable();
            $table->string('location', 500)->nullable();
            $table->uuid('cover_file_id')->nullable();
            $table->string('cover_color', 7)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quadro_cartoes', function (Blueprint $table) {
            $table->dropColumn(['checklist', 'attachments', 'activity', 'location', 'cover_file_id', 'cover_color']);
        });
    }
};
