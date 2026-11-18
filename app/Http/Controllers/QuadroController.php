<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuadroRequest;
use App\Http\Requests\UpdateQuadroRequest;
use App\Models\Quadro;
use App\Models\QuadroMembro;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuadroController extends Controller
{
    public function index(Request $request): View
    {
        $boards = Quadro::with(['user', 'membros'])->withCount(['cartoes', 'membros' => fn (Builder $q): Builder => $q->where('status', 'accepted')])->latest()->get();
        $invites = QuadroMembro::with('quadro.user')->where('user_id', $request->user()->id)->where('status', 'pending')->get();

        return view('pages.quadros', ['quadros' => $boards, 'invites' => $invites]);
    }

    public function store(StoreQuadroRequest $request): JsonResponse|RedirectResponse
    {
        $path = $request->file('cover_image')?->store('quadro-covers', 'local');
        try {
            $board = DB::transaction(function () use ($request, $path): Quadro {
                $board = new Quadro($request->safe()->only(['title', 'category', 'cover']));
                $board->user_id = $request->user()->id;
                $board->cover_path = $path;
                $board->save();
                $board->listas()->createMany([
                    ['title' => 'A fazer', 'color' => '#334155', 'position' => 0],
                    ['title' => 'Em andamento', 'color' => '#854d0e', 'position' => 1],
                    ['title' => 'Concluído', 'color' => '#065f46', 'position' => 2],
                ]);
                if ($request->filled('invite_handle')) {
                    $board->membros()->create(['user_id' => User::where('apelido', $request->validated('invite_handle'))->firstOrFail()->id, 'role' => 'editor', 'status' => 'pending']);
                }

                return $board;
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
        $url = route('quadros.show', $board, absolute: false);

        return $request->wantsJson() ? response()->json(['url' => $url]) : redirect($url);
    }

    public function show(Request $request, Quadro $quadro): View|JsonResponse
    {
        $quadro->load(['user', 'membros.user', 'listas.cartoes' => function (HasMany $cards): void {
            $cards->withCount('comentarios')->with(['comentarios' => function (HasMany $comments): void {
                $comments->with('user')->limit(1);
            }]);
        }]);
        $data = ['quadro' => $quadro, 'canEdit' => $quadro->canEdit($request->user()), 'canManage' => $quadro->canManage($request->user())];

        return $request->wantsJson() ? response()->json(['html' => view('partials.quadro-content', $data)->render()]) : view('pages.quadro', $data);
    }

    public function edit(Request $request, Quadro $quadro): JsonResponse
    {
        abort_unless($quadro->canManage($request->user()), 403);

        return response()->json(['html' => view('components.quadro-board-form', [
            'quadro' => $quadro,
            'categories' => Quadro::whereNotNull('category')->distinct()->pluck('category')->all(),
            'dialogId' => 'quadro-edit-dialog',
        ])->render()]);
    }

    public function update(UpdateQuadroRequest $request, Quadro $quadro): JsonResponse
    {
        $oldPath = $quadro->cover_path;
        $attributes = $request->safe()->only(['title', 'category', 'cover']);
        if ($request->hasFile('cover_image')) {
            $attributes['cover_path'] = $request->file('cover_image')->store('quadro-covers', 'local');
        } elseif ($request->boolean('remove_cover')) {
            $attributes['cover_path'] = null;
        }
        $quadro->update($attributes);
        if ($oldPath && $oldPath !== $quadro->cover_path) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['url' => route('quadros.show', $quadro, absolute: false)]);
    }

    public function destroy(Request $request, Quadro $quadro): JsonResponse
    {
        abort_unless($quadro->user_id === $request->user()->id, 403);
        $path = $quadro->cover_path;
        $attachmentPaths = $quadro->cartoes()->get(['attachments'])->flatMap(fn ($card) => collect($card->attachments ?? [])->pluck('path'))->all();
        $quadro->delete();
        Storage::disk('local')->delete($attachmentPaths);
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['url' => route('cursos', absolute: false)]);
    }

    public function cover(Quadro $quadro): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($quadro->cover_path && $disk->exists($quadro->cover_path), 404);
        abort_unless(in_array($disk->mimeType($quadro->cover_path), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true), 415);

        return response()->file($disk->path($quadro->cover_path), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])->setPrivate();
    }
}
