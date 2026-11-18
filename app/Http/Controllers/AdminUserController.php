<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\CursoFile;
use App\Models\LancamentoFile;
use App\Models\ProjetoFile;
use App\Models\Quadro;
use App\Models\TarefaFile;
use App\Models\TransferenciaFinanceira;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $value = $request->query('q');
        $search = is_string($value) ? trim(mb_substr($value, 0, 255)) : '';

        return view('pages.usuarios', [
            'search' => $search,
            'users' => User::query()
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $pattern = '%'.$search.'%';
                    $query->where(fn (Builder $fields) => $fields->whereLike('name', $pattern)->orWhereLike('email', $pattern));
                })
                ->orderBy('name')->orderBy('id')->paginate(20)->appends(['q' => $search]),
        ]);
    }

    public function create(): View
    {
        return view('pages.usuario-form', ['managedUser' => null]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuário criado com sucesso.');
    }

    public function edit(User $user): View
    {
        return view('pages.usuario-form', ['managedUser' => $user]);
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->fill($data);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if ($user->isDirty('password')) {
            $user->setRememberToken(Str::random(60));
        }
        $user->save();

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuário atualizado com sucesso.');
    }

    public function confirmDeletion(Request $request, User $user): View
    {
        abort_if($request->user()->is($user), 403);

        return view('pages.usuario-excluir', ['managedUser' => $user]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403);
        $actorId = $request->user()->getKey();

        $paths = DB::transaction(function () use ($user, $actorId): array {
            $lockedUsers = User::whereKey([$actorId, $user->getKey()])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($lockedUsers->get($actorId)?->is_admin, 403);
            $target = $lockedUsers->get($user->getKey());
            abort_unless($target, 404);

            $paths = $this->ownedFilePaths($target);
            // Financial accounts restrict deletion while their transactions or transfers still reference them.
            TransferenciaFinanceira::withoutGlobalScope('owner')->where('user_id', $target->getKey())->delete();
            $target->lancamentos()->withoutGlobalScope('owner')->delete();
            $target->investimentos()->withoutGlobalScope('owner')->delete();
            $target->delete();

            return $paths;
        });

        Storage::disk('local')->delete($paths['local']);
        Storage::disk('public')->delete($paths['public']);

        return redirect()->route('admin.usuarios.index')->with('status', 'Usuário excluído com sucesso.');
    }

    /** @return array{local: list<string>, public: list<string>} */
    private function ownedFilePaths(User $user): array
    {
        $boards = Quadro::withoutGlobalScope('access')->where('user_id', $user->getKey())
            ->with('cartoes:id,quadro_id,attachments')->get(['id', 'cover_path']);

        return [
            'local' => [
                ...TarefaFile::whereIn('tarefa_id', $user->tarefas()->withoutGlobalScope('owner')->select('id'))->pluck('path')->all(),
                ...ProjetoFile::whereIn('projeto_id', $user->projetos()->withoutGlobalScope('owner')->select('id'))->pluck('path')->all(),
                ...LancamentoFile::whereIn('lancamento_id', $user->lancamentos()->withoutGlobalScope('owner')->select('id'))->pluck('path')->all(),
                ...$boards->pluck('cover_path')->filter()->values()->all(),
                ...$boards->flatMap(fn (Quadro $board) => $board->cartoes->flatMap(fn ($card) => collect($card->attachments)->pluck('path')))->filter()->values()->all(),
            ],
            'public' => CursoFile::whereIn('curso_id', $user->cursos()->withoutGlobalScope('owner')->select('id'))->pluck('path')->all(),
        ];
    }
}
