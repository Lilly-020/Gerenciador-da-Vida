<div data-project-comments data-sync-url="{{ route('projetos.comentarios.index', $projeto, absolute: false) }}" class="project-comments-workspace">
<button type="button" data-project-mobile-back class="shrink-0 self-start px-5 pt-3 text-xs text-indigo-300 lg:hidden" aria-label="Voltar ao projeto">← Projeto</button>
<div class="project-conversation-tabs" role="tablist" aria-label="Comentários e histórico">
    <button type="button" id="project-comments-tab-{{ $projeto->id }}" data-project-tab="comments" role="tab" aria-selected="true" aria-controls="project-comments-{{ $projeto->id }}">Comentários <span data-project-comment-count class="ml-1 rounded-full bg-white/5 px-2 py-0.5 text-xs">{{ $projeto->comments->count() }}</span></button>
    <button type="button" id="project-history-tab-{{ $projeto->id }}" data-project-tab="history" role="tab" aria-selected="false" aria-controls="project-history-{{ $projeto->id }}" tabindex="-1">Histórico</button>
</div>
<section id="project-comments-{{ $projeto->id }}" data-project-tab-panel="comments" role="tabpanel" aria-labelledby="project-comments-tab-{{ $projeto->id }}" class="project-conversation project-comments">
    @include('partials.projeto-comment-feed')
        @if ($canEdit)
            <form data-project-comment-form="create" method="POST" action="{{ route('projetos.comentarios.store', $projeto, absolute: false) }}" class="project-comment-composer grid gap-2">
                @csrf
                <label class="grid gap-1 text-xs text-slate-400">Sobre
                    <select name="projeto_task_id" class="min-w-0 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white">
                        <option value="">Projeto</option>
                        @foreach ($projeto->tasks as $commentTask)<option value="{{ $commentTask->id }}">{{ $commentTask->title }}</option>@endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-xs text-slate-400">Comentário<textarea name="body" required maxlength="5000" rows="3" placeholder="Escrever um comentário..." class="w-full resize-none rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"></textarea></label>
                <button class="justify-self-start rounded-lg bg-indigo-500 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-400">Enviar comentário</button>
                <p data-comment-error hidden class="text-xs text-rose-300" role="alert"></p>
            </form>
        @endif
</section>
<section id="project-history-{{ $projeto->id }}" data-project-tab-panel="history" role="tabpanel" aria-labelledby="project-history-tab-{{ $projeto->id }}" hidden class="project-conversation app-scroll" data-project-scroll="history">
    @include('partials.projeto-history')
</section>

<p data-project-comment-sync class="project-comment-sync" role="status">Atualização automática ativa</p>
</div>
