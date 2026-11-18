<?php

namespace App\Http\Controllers;

use App\Models\Quadro;
use App\Models\QuadroMembro;
use App\Models\User;
use App\Notifications\SocialNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuadroMembroController extends Controller
{
    public function index(Request $request, Quadro $quadro): JsonResponse
    {
        $quadro->load(['user', 'membros.user']);

        return response()->json(['html' => view('partials.quadro-members', ['quadro' => $quadro, 'canManage' => $quadro->canManage($request->user()), 'friends' => $request->user()->friends()->get()])->render()]);
    }

    public function store(Request $request, Quadro $quadro): JsonResponse
    {
        abort_unless($quadro->canManage($request->user()), 403);
        $data = $request->validate(['friend_id' => ['required', 'integer', Rule::in($request->user()->friendIds())]]);
        $member = $quadro->membros()->firstOrNew(['user_id' => $data['friend_id']]);
        $notify = ! $member->exists || $member->status !== 'accepted';
        $member->role = 'editor';
        $member->status = 'accepted';
        $member->save();
        if ($notify) {
            User::findOrFail($data['friend_id'])->notify(new SocialNotification(['kind' => 'planner', 'board_id' => $quadro->id, 'title' => $quadro->title, 'actor' => $request->user()->name]));
        }

        return $this->index($request, $quadro);
    }

    public function destroy(Request $request, Quadro $quadro, QuadroMembro $membro): JsonResponse
    {
        abort_unless($membro->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canManage($request->user()) && $membro->user_id !== $request->user()->id, 403);
        $membro->delete();

        return $this->index($request, $quadro);
    }
}
