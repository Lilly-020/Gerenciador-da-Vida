<div data-project-comment-feed class="project-comment-feed app-scroll" data-project-scroll="comments" aria-live="polite" aria-relevant="additions text">
        <p class="text-xs text-slate-500">Últimos 30 comentários</p>
        @forelse ($projeto->comments as $comment)
            <article data-project-comment="{{ $comment->id }}" class="rounded-xl border border-white/5 bg-slate-950/40 p-4 text-sm text-slate-300">
                <div class="flex items-start justify-between gap-3 text-xs text-slate-400">
                    <span class="flex min-w-0 items-center gap-2"><span aria-hidden="true" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-400/15 font-semibold text-indigo-200">{{ mb_strtoupper(mb_substr($comment->user?->name ?? '?', 0, 2)) }}</span><span class="min-w-0"><strong class="block wrap-break-word text-slate-200">{{ $comment->user?->name ?? 'Usuário removido' }}</strong><time datetime="{{ $comment->created_at->toIso8601String() }}" class="mt-1 block text-slate-500">{{ $comment->created_at->format('d/m/Y H:i') }}</time></span></span>
                    @if ($canManage || ($canEdit && $comment->user_id === auth()->id()))
                        <div class="flex shrink-0 items-center gap-1.5">
                            @if ($canEdit && $comment->user_id === auth()->id())
                                <button type="button" data-project-comment-edit aria-label="Editar comentário" title="Editar comentário" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 text-indigo-200 transition hover:border-indigo-400/40 hover:bg-indigo-400/10 focus-visible:outline-2 focus-visible:outline-indigo-400"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5-12 12-6 1 1-6zM14 5l5 5" /></svg></button>
                            @endif
                            <form data-project-comment-form="delete" method="POST" action="{{ route('projetos.comentarios.destroy', [$projeto, $comment], absolute: false) }}">
                                @csrf @method('DELETE')
                                <button type="submit" aria-label="Excluir comentário" title="Excluir comentário" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 text-rose-300 transition hover:border-rose-400/40 hover:bg-rose-400/10 focus-visible:outline-2 focus-visible:outline-rose-400"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7" /></svg></button>
                            </form>
                        </div>
                    @endif
                </div>
                @if ($canEdit && $comment->user_id === auth()->id())
                    <form data-project-comment-form="edit" hidden method="POST" action="{{ route('projetos.comentarios.update', [$projeto, $comment], absolute: false) }}" class="mt-3 grid gap-2">
                        @csrf @method('PATCH')
                        <label class="grid gap-2 text-xs text-slate-400">Editar comentário<textarea name="body" required maxlength="5000" rows="4" class="w-full">{{ $comment->body }}</textarea></label>
                        <div class="flex gap-3"><button type="submit" class="rounded-lg bg-indigo-500 px-3 py-2 text-xs font-semibold text-white">Salvar edição</button><button type="button" data-project-comment-cancel class="text-xs text-slate-400">Cancelar</button></div>
                        <p data-comment-error hidden class="text-xs text-rose-300" role="alert"></p>
                    </form>
                @endif
                @if ($comment->edited_at)<p class="mt-2 text-xs text-slate-500">Editado em <time datetime="{{ $comment->edited_at->toIso8601String() }}">{{ $comment->edited_at->format('d/m/Y H:i:s') }}</time></p>@endif
                @if ($comment->task)<p class="mt-1 text-xs text-indigo-300">Tarefa: {{ $comment->task->title }}</p>@endif
                <p data-project-comment-body class="mt-2 whitespace-pre-wrap wrap-break-word">{{ $comment->body }}</p>
            </article>
        @empty
            <p class="text-xs text-slate-500">Comece uma conversa sobre o projeto.</p>
        @endforelse
    </div>
