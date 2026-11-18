<?php

namespace App\Http\Controllers;

use App\Models\Quadro;
use App\Models\QuadroMembro;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuadroMembroController extends Controller
{
    public function index(Request $request, Quadro $quadro): JsonResponse
    {
        $quadro->load(['user', 'membros.user']);

        return response()->json(['html' => view('partials.quadro-members', ['quadro' => $quadro, 'canManage' => $quadro->canManage($request->user())])->render()]);
    }

    public function store(Request $request, Quadro $quadro): JsonResponse
    {
        abort_unless($quadro->canManage($request->user()), 403);
        $request->merge(['handle' => mb_strtolower(ltrim(trim((string) $request->input('handle')), '@'))]);
        $data = $request->validate(['handle' => ['required', 'string', Rule::exists('users', 'apelido')->whereNot('id', $quadro->user_id)->whereNot('id', $request->user()->id)], 'role' => ['required', 'in:admin,editor,viewer']]);
        $user = User::where('apelido', $data['handle'])->firstOrFail();
        $member = $quadro->membros()->firstOrNew(['user_id' => $user->id]);
        $member->role = $data['role'];
        if ($member->status !== 'accepted') {
            $member->status = 'pending';
        }
        $member->save();

        return $this->index($request, $quadro);
    }

    public function update(Request $request, Quadro $quadro, QuadroMembro $membro): JsonResponse
    {
        abort_unless($membro->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canManage($request->user()) && $membro->user_id !== $request->user()->id, 403);
        $membro->update($request->validate(['role' => ['required', 'in:admin,editor,viewer']]));

        return $this->index($request, $quadro);
    }

    public function destroy(Request $request, Quadro $quadro, QuadroMembro $membro): JsonResponse
    {
        abort_unless($membro->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canManage($request->user()) && $membro->user_id !== $request->user()->id, 403);
        $membro->delete();

        return $this->index($request, $quadro);
    }

    public function respond(Request $request, QuadroMembro $membro): JsonResponse
    {
        abort_unless($membro->user_id === $request->user()->id, 404);
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        abort_unless($membro->status === 'pending', 409, 'Este convite já foi respondido.');
        $membro->update(['status' => $data['accept'] ? 'accepted' : 'declined']);

        return response()->json(['url' => $data['accept'] ? route('quadros.show', $membro->quadro_id, absolute: false) : route('cursos', absolute: false)]);
    }
}
