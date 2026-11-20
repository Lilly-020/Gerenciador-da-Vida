<?php

namespace App\Http\Controllers;

use App\Models\Amizade;
use App\Models\User;
use App\Support\Friendships;
use App\Support\SocialAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
            SocialAudience::lockPair($user->id, $friend->id);
            if (! SocialAudience::allows($user, $friend->id)) {
                throw ValidationException::withMessages(['apelido' => 'Confira o apelido completo da outra pessoa.']);
            }
            $friendship = Amizade::firstOrCreate(SocialAudience::pair($user->id, $friend->id), ['requester_id' => $user->id, 'status' => 'pending']);
            if ($friendship->status === 'accepted') {
                return 'Vocês já são amigos.';
            }
            if (! $friendship->wasRecentlyCreated && $friendship->status === 'pending') {
                return $friendship->requester_id === $user->id ? 'O pedido de amizade já foi enviado.' : 'Essa pessoa já enviou um pedido. Você pode aceitá-lo nas suas solicitações.';
            }
            $friendship->update(['requester_id' => $user->id, 'status' => 'pending']);
            Friendships::notify($friend, $user, $friendship);

            return 'Pedido de amizade enviado. Aguarde a resposta.';
        });

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }

    public function respond(Request $request, Amizade $amizade): RedirectResponse
    {
        abort_unless($amizade->includes($request->user()->id) && $amizade->requester_id !== $request->user()->id, 404);
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $amizade, $data): void {
            SocialAudience::lockPair($amizade->user_low_id, $amizade->user_high_id);
            $friendship = Amizade::whereKey($amizade->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $friendship);
            $friendship->update(['status' => $data['accept'] ? 'accepted' : 'declined']);
            Friendships::markRequestRead($request->user(), $friendship);
            if ($data['accept']) {
                Friendships::notify(User::findOrFail($friendship->requester_id), $request->user(), $friendship, accepted: true);
            }
        });

        return back()->with('status', $data['accept'] ? 'Amizade aceita. Agora vocês podem participar dos mesmos itens.' : 'Pedido recusado.');
    }

    public function destroy(Request $request, Amizade $amizade): RedirectResponse
    {
        abort_unless($amizade->includes($request->user()->id), 404);
        DB::transaction(function () use ($amizade): void {
            SocialAudience::lockPair($amizade->user_low_id, $amizade->user_high_id);
            Friendships::remove(Amizade::findOrFail($amizade->id));
        });

        return back()->with('status', 'Amizade removida e participações entre vocês encerradas.');
    }
}
