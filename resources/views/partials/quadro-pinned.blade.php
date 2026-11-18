@if ($cartao->pinnedComentarios->isNotEmpty())
    <h3 class="mb-4!">Comentários fixados</h3>
    <div class="grid gap-4">
        @foreach ($cartao->pinnedComentarios as $pinned)
            <article class="rounded-xl border border-indigo-400/20 bg-indigo-400/5 p-3">
                <div class="mb-3 flex items-center justify-between gap-2"><p class="text-xs text-slate-400">{{ $pinned->user?->name }} · {{ $pinned->created_at->format('d/m/Y H:i') }}</p>@if ($canEdit)<button type="button" data-quadro-comment-pin data-pinned="1" data-url="{{ route('quadros.comentarios.pin', [$quadro, $cartao, $pinned], absolute: false) }}" class="quadro-tool px-2!" aria-label="Desafixar comentário"><x-card-icon name="pin" /></button>@endif</div>
                <p class="line-clamp-3 whitespace-pre-wrap wrap-break-word text-sm text-slate-300">{{ $pinned->body }}</p><button type="button" data-quadro-comment-jump="{{ $pinned->id }}" class="mt-3 text-xs font-medium text-indigo-300">Ver comentário</button>
            </article>
        @endforeach
    </div>
@endif
