<?php

namespace App\Http\Controllers;

use App\Models\Amizade;
use App\Models\Participacao;
use App\Models\Quadro;
use App\Models\QuadroMembro;
use App\Models\User;
use App\Notifications\SocialNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmizadeController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['apelido' => ['required', 'string', 'max:31']]);
        $friend = User::findByApelido($data['apelido']);
        if (! $friend || $friend->is($request->user())) {
            throw ValidationException::withMessages(['apelido' => 'Confira o apelido completo da outra pessoa.']);
        }
        $message = DB::transaction(function () use ($request, $friend): string {
            $user = $request->user();
            User::whereIn('id', [$user->id, $friend->id])->orderBy('id')->lockForUpdate()->get();
            $friendship = Amizade::firstOrCreate(['user_low_id' => min($user->id, $friend->id), 'user_high_id' => max($user->id, $friend->id)], ['requester_id' => $user->id, 'status' => 'pending']);
            if ($friendship->status === 'accepted') {
                return 'Vocês já são amigos.';
            }
            if (! $friendship->wasRecentlyCreated && $friendship->status === 'pending') {
                return $friendship->requester_id === $user->id ? 'O pedido de amizade já foi enviado.' : 'Essa pessoa já enviou um pedido. Aceite em Perfil → Amigos.';
            }
            $friendship->update(['requester_id' => $user->id, 'status' => 'pending']);
            $friend->notify(new SocialNotification(['kind' => 'friend_request', 'friendship_id' => $friendship->id, 'actor' => $user->name, 'title' => 'Pedido de amizade']));

            return 'Pedido de amizade enviado. A pessoa precisa aceitar no perfil.';
        });

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }

    public function respond(Request $request, Amizade $amizade): RedirectResponse
    {
        abort_unless($amizade->includes($request->user()->id) && $amizade->requester_id !== $request->user()->id, 404);
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $amizade, $data): void {
            $friendship = Amizade::whereKey($amizade->id)->lockForUpdate()->firstOrFail();
            abort_unless($friendship->status === 'pending', 409, 'Este pedido já foi respondido.');
            $friendship->update(['status' => $data['accept'] ? 'accepted' : 'declined']);
            $request->user()->unreadNotifications()->where('type', SocialNotification::class)->where('data->friendship_id', $friendship->id)->update(['read_at' => now()]);
            if ($data['accept']) {
                User::findOrFail($friendship->requester_id)->notify(new SocialNotification(['kind' => 'friend_accepted', 'friendship_id' => $friendship->id, 'actor' => $request->user()->name, 'title' => 'Pedido de amizade aceito']));
            }
        });

        return back()->with('status', $data['accept'] ? 'Amizade aceita. Agora vocês podem participar dos mesmos itens.' : 'Pedido recusado.');
    }

    public function destroy(Request $request, Amizade $amizade): RedirectResponse
    {
        abort_unless($amizade->includes($request->user()->id), 404);
        DB::transaction(function () use ($amizade): void {
            foreach ([[$amizade->user_low_id, $amizade->user_high_id], [$amizade->user_high_id, $amizade->user_low_id]] as [$owner, $friend]) {
                Participacao::where('owner_id', $owner)->where('participant_id', $friend)->whereIn('tipo', ['projeto', 'sonho', 'tarefa'])->delete();
                QuadroMembro::where('user_id', $friend)->whereIn('quadro_id', Quadro::withoutGlobalScopes()->where('user_id', $owner)->select('id'))->delete();
            }
            $amizade->delete();
        });

        return back()->with('status', 'Amizade removida e participações entre vocês encerradas.');
    }
}
