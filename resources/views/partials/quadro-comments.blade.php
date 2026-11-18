<div class="grid gap-4">
@forelse ($cartao->comentarios as $comment)
    <article data-quadro-comment="{{ $comment->id }}" class="quadro-comment">
        <div class="flex items-start justify-between gap-2"><div class="flex min-w-0 items-start gap-3"><span class="quadro-avatar mt-0.5">{{ mb_strtoupper(mb_substr($comment->user?->name ?? "?", 0, 2)) }}</span><div class="min-w-0"><strong class="block wrap-break-word text-xs text-slate-200">{{ $comment->user?->name ?? 'Usuário removido' }}</strong><time class="mt-1 block text-[10px] text-slate-500">{{ $comment->created_at->format('d/m/Y H:i') }}</time></div></div><div class="flex shrink-0 gap-1">@if ($canEdit && $comment->user_id === auth()->id())<button type="button" data-quadro-comment-edit class="quadro-tool px-2! py-1!" aria-label="Editar comentário"><x-card-icon name="pencil" /></button>@endif @if ($canManage || ($canEdit && $comment->user_id === auth()->id()))<button type="button" data-quadro-comment-delete data-url="{{ route('quadros.comentarios.destroy', [$quadro, $cartao, $comment], absolute: false) }}" class="quadro-tool px-2! py-1! text-rose-300!" aria-label="Excluir comentário"><x-card-icon name="trash" /></button>@endif</div></div>
        <p class="quadro-comment-bubble">{{ $comment->body }}</p>@if ($comment->edited_at)<p class="mt-2 text-[10px] text-slate-500">Editado em {{ $comment->edited_at->format('d/m/Y H:i:s') }}</p>@endif
        @if ($canEdit && $comment->user_id === auth()->id())<form data-quadro-comment-edit-form action="{{ route('quadros.comentarios.update', [$quadro, $cartao, $comment], absolute: false) }}" hidden class="mt-3 grid gap-2"><textarea name="body" required maxlength="5000" rows="3" class="w-full">{{ $comment->body }}</textarea><p data-quadro-error hidden class="text-xs text-rose-300" role="alert"></p><div class="flex gap-2"><button type="submit" class="quadro-primary text-xs!">Salvar</button><button type="button" data-quadro-comment-cancel class="text-xs text-slate-400">Cancelar</button></div></form>@endif
    </article>
@empty
    <p class="text-xs leading-relaxed text-slate-500">Comece uma conversa sobre este cartão.</p>
@endforelse
</div>
