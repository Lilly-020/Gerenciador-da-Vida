<details data-details-key="comments" class="mt-3 border-t border-white/10 pt-3">
    <summary class="cursor-pointer text-sm text-indigo-300">Comentários</summary>
    <div class="mt-3 grid gap-3">
        <p class="text-xs text-slate-500">Últimos 30 comentários</p>
        @forelse ($projeto->comments as $comment)
            <div class="rounded-lg bg-slate-950/40 p-3 text-sm text-slate-300">
                <div class="flex flex-wrap justify-between gap-2 text-xs text-slate-400">
                    <span>{{ $comment->user?->handle() ?? 'Usuário removido' }} · {{ $comment->created_at->format('d/m/Y H:i') }}</span>
                    @if ($canManage || ($canEdit && $comment->user_id === auth()->id()))
                        <form method="POST" action="{{ route('projetos.comentarios.destroy', [$projeto, $comment], absolute: false) }}">@csrf @method('DELETE')<button class="text-rose-300">Excluir comentário</button></form>
                    @endif
                </div>
                @if ($comment->task)<p class="mt-1 text-xs text-indigo-300">Tarefa: {{ $comment->task->title }}</p>@endif
                <p class="mt-2 whitespace-pre-wrap wrap-break-word">{{ $comment->body }}</p>
            </div>
        @empty
            <p class="text-xs text-slate-500">Comece uma conversa sobre o projeto.</p>
        @endforelse
        @if ($canEdit)
            <form method="POST" action="{{ route('projetos.comentarios.store', $projeto, absolute: false) }}" class="grid gap-2">
                @csrf
                <label class="grid gap-1 text-xs text-slate-400">Sobre
                    <select name="projeto_task_id" class="min-w-0 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white">
                        <option value="">Projeto</option>
                        @foreach ($projeto->tasks as $commentTask)<option value="{{ $commentTask->id }}">{{ $commentTask->title }}</option>@endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-xs text-slate-400">Comentário<textarea name="body" required maxlength="5000" rows="3" class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"></textarea></label>
                <button class="justify-self-start rounded-lg bg-indigo-500 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-400">Comentar</button>
            </form>
        @endif
    </div>
</details>
<details data-details-key="files" class="mt-3 border-t border-white/10 pt-3">
    <summary class="cursor-pointer text-sm text-indigo-300">Anexos ({{ $projeto->files->count() }})</summary>
    <ul class="mt-3 grid gap-2">
        @foreach ($projeto->files as $file)
            <li class="flex items-center justify-between gap-2 text-xs">
                <a href="{{ route('projetos.arquivos.download', [$projeto, $file], absolute: false) }}" class="min-w-0 wrap-break-word text-indigo-200 underline">{{ $file->original_name }} · {{ number_format($file->size / 1024, 1, ',', '.') }} KB</a>
                @if ($canEdit)<form method="POST" action="{{ route('projetos.arquivos.destroy', [$projeto, $file], absolute: false) }}">@csrf @method('DELETE')<button class="text-rose-300">Remover</button></form>@endif
            </li>
        @endforeach
    </ul>
    @if ($canEdit)
        <form method="POST" enctype="multipart/form-data" action="{{ route('projetos.arquivos.store', $projeto, absolute: false) }}" class="mt-3 grid gap-2">
            @csrf
            <label class="grid gap-2 text-xs text-slate-400">Documento ou imagem (até 10 MB)
                <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx,.xls,.xlsx" class="min-w-0 text-sm text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-500/20 file:px-3 file:py-2 file:text-indigo-200">
            </label>
            <button class="justify-self-start rounded-lg border border-white/10 px-3 py-2 text-xs text-indigo-200 hover:bg-white/5">Anexar arquivo</button>
        </form>
    @endif
</details>
<details data-details-key="history" class="mt-3 border-t border-white/10 pt-3">
    <summary class="cursor-pointer text-sm text-indigo-300">Histórico de atividades</summary>
    <p class="mt-3 text-xs text-slate-500">Últimas 50 atividades</p>
    <ol class="mt-2 grid gap-3 border-l border-indigo-400/20 pl-3">
        @forelse ($projeto->activities as $activity)
            <li class="text-xs text-slate-400"><span class="text-slate-200">{{ $activity->user?->handle() ?? 'Usuário removido' }}</span> · {{ $activity->description }}<time class="mt-1 block text-slate-500">{{ $activity->created_at->format('d/m/Y H:i') }}</time></li>
        @empty
            <li class="text-xs text-slate-500">As próximas alterações aparecerão aqui.</li>
        @endforelse
    </ol>
</details>
