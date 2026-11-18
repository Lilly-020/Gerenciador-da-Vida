<?php

use App\Models\User;
use App\Notifications\SocialNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amizades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['user_low_id', 'user_high_id']);
            $table->index(['user_low_id', 'status']);
            $table->index(['user_high_id', 'status']);
        });
        $links = DB::table('participacoes')->whereIn('status', ['aceito', 'pendente'])->get()->map(fn ($row): array => [$row->owner_id, $row->participant_id, $row->status === 'aceito']);
        $links = $links->concat(DB::table('quadro_membros')->join('quadros', 'quadros.id', '=', 'quadro_membros.quadro_id')->whereIn('quadro_membros.status', ['accepted', 'pending'])->get(['quadros.user_id as owner_id', 'quadro_membros.user_id as friend_id', 'quadro_membros.status'])->map(fn ($row): array => [$row->owner_id, $row->friend_id, $row->status === 'accepted']));
        foreach ($links as [$owner, $friend, $accepted]) {
            if ($owner === $friend) {
                continue;
            }
            $pair = ['user_low_id' => min($owner, $friend), 'user_high_id' => max($owner, $friend)];
            $existing = DB::table('amizades')->where($pair)->first();
            if (! $existing) {
                DB::table('amizades')->insert([...$pair, 'requester_id' => $owner, 'status' => $accepted ? 'accepted' : 'pending', 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($accepted) {
                DB::table('amizades')->where('id', $existing->id)->update(['status' => 'accepted']);
            }
        }
        foreach (DB::table('amizades')->where('status', 'pending')->get() as $friendship) {
            $recipient = $friendship->requester_id === $friendship->user_low_id ? $friendship->user_high_id : $friendship->user_low_id;
            DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => SocialNotification::class, 'notifiable_type' => User::class, 'notifiable_id' => $recipient, 'data' => json_encode(['kind' => 'friend_request', 'friendship_id' => $friendship->id, 'title' => 'Pedido de amizade', 'actor' => DB::table('users')->where('id', $friendship->requester_id)->value('name')]), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('participacoes')->where('tipo', 'financas')->update(['status' => 'recusado']);
        DB::table('participacoes')->where('tipo', 'projeto')->where('status', 'aceito')->update(['permission' => 'editar']);
        DB::table('quadro_membros')->where('status', 'accepted')->update(['role' => 'editor']);
    }

    public function down(): void
    {
        Schema::dropIfExists('amizades');
    }
};
