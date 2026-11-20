<?php

namespace App\Http\Controllers;

use App\Models\Projeto;
use App\Models\Quadro;
use App\Models\Sonho;
use App\Models\Tarefa;
use App\Notifications\QuadroCommentMention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count(), 'html' => view('partials.notifications', ['notifications' => $request->user()->notifications()->latest()->limit(50)->get()])->render()])->header('Cache-Control', 'private, no-store');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $data = $item->data;
        if (str_starts_with($data['kind'] ?? '', 'social_')) {
            return app(SocialController::class)->openNotification($request, $notification);
        }
        if ($item->type !== QuadroCommentMention::class) {
            $item->markAsRead();
        }
        if (str_starts_with($data['kind'] ?? '', 'friend_')) {
            return redirect()->route('perfil', ['aba' => 'amigos']);
        }
        if (isset($data['board_id'])) {
            $board = Quadro::find($data['board_id']);
            if ($board && (! isset($data['card_id']) || $board->cartoes()->whereKey($data['card_id'])->exists())) {
                return redirect()->to(route('quadros.show', $board, absolute: false).(isset($data['card_id']) ? '#card='.$data['card_id'] : '').(isset($data['comment_id']) ? '&comment='.$data['comment_id'] : ''));
            }
        }
        foreach (['projeto' => [Projeto::class, 'projetos'], 'sonho' => [Sonho::class, 'sonhos'], 'tarefa' => [Tarefa::class, 'tarefas']] as $kind => [$model, $route]) {
            if (($data['kind'] ?? '') === $kind && $model::find($data['item_id'])) {
                return redirect()->route($route, $kind === 'tarefa' ? ['date' => Tarefa::find($data['item_id'])->date?->toDateString()] : []);
            }
        }

        return redirect()->route('perfil', ['aba' => 'amigos'])->with('status', 'Este item não está mais disponível.');
    }
}
