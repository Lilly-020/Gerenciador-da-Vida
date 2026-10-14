<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `apelido` keeps the spelling the user chose; `apelido_key` is its
     * lowercase form, and is what makes it unique and searchable without
     * regard to case.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('apelido', 30)->nullable();
            $table->string('apelido_key', 30)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['apelido_key']);
            $table->dropColumn(['apelido', 'apelido_key']);
        });
    }
};
