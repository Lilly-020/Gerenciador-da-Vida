@if ($cartao->pinnedComentarios->isNotEmpty())
    <h3 class="mb-4!">Arquivos fixados</h3>
    <div class="grid gap-4">
        @foreach ($cartao->pinnedComentarios as $pinned)
            <article class="rounded-xl border border-indigo-400/20 bg-indigo-400/5 p-3">
                <div class="mb-3 flex items-center justify-between gap-2"><p class="text-xs text-slate-400">{{ $pinned->user?->name }} · {{ $pinned->created_at->format('d/m/Y H:i') }}</p>@if ($canEdit)<button type="button" data-quadro-comment-pin data-pinned="1" data-url="{{ route('quadros.comentarios.pin', [$quadro, $cartao, $pinned], absolute: false) }}" class="quadro-tool px-2!" aria-label="Desafixar comentário"><x-card-icon name="pin" /></button>@endif</div>
                @foreach ($pinned->file_ids ?? [] as $fileId)
                    @php($file = collect($cartao->attachments ?? [])->firstWhere('id', $fileId))
                    @if ($file)<a href="{{ route('quadros.arquivos.show', [$quadro, $cartao, $fileId], absolute: false) }}" class="mb-3 flex items-center gap-3 text-xs text-indigo-300">@if (in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif']))<img src="{{ route('quadros.arquivos.show', [$quadro, $cartao, $fileId], absolute: false) }}?preview=1" class="h-16 w-24 rounded-lg object-cover" alt="">@else<x-card-icon name="file" />@endif<span class="wrap-break-word">{{ $file['name'] }}</span></a>@endif
                @endforeach
                <p class="line-clamp-3 whitespace-pre-wrap wrap-break-word text-sm text-slate-300">{{ $pinned->body }}</p><button type="button" data-quadro-comment-jump="{{ $pinned->id }}" class="mt-3 text-xs font-medium text-indigo-300">Ver comentário</button>
            </article>
        @endforeach
    </div>
@endif
