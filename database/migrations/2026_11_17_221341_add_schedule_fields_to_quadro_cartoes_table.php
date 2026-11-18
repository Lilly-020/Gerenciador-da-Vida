<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quadro_cartoes', function (Blueprint $table): void {
            $table->date('starts_on')->nullable();
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->dateTime('reminder_at')->nullable()->index();
            $table->dateTime('reminder_notified_at')->nullable();
            $table->dateTime('reminder_mailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::table('quadro_cartoes')->orderBy('id')->each(function (object $card): void {
            $owner = DB::table('quadros')->where('id', $card->quadro_id)->value('user_id');
            DB::table('quadro_cartoes')->where('id', $card->id)->update(['created_by' => $owner]);
        });
    }

    public function down(): void
    {
        Schema::table('quadro_cartoes', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['reminder_at']);
            $table->dropColumn(['starts_on', 'reminder_minutes', 'reminder_at', 'reminder_notified_at', 'reminder_mailed_at', 'created_by']);
        });
    }
};
