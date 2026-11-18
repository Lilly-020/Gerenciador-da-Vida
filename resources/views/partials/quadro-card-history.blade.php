@php
    $events = collect($cartao->activity ?? []);
    if ($events->isEmpty()) {
        $events->push(['author' => null, 'message' => 'Cartão criado.', 'at' => $cartao->created_at->toIso8601String()]);
    }
    foreach ($cartao->comentarios as $comment) {
        $events->push(['author' => $comment->user?->name, 'message' => 'Adicionou um comentário.', 'at' => $comment->created_at->toIso8601String()]);
        if ($comment->edited_at) {
            $events->push(['author' => $comment->user?->name, 'message' => 'Editou um comentário.', 'at' => $comment->edited_at->toIso8601String()]);
        }
    }
@endphp
<ol class="grid gap-5">
    @foreach ($events->sortByDesc('at') as $event)
        <li class="border-l-2 border-indigo-400/40 pl-4 text-xs"><strong class="text-slate-200">{{ $event['author'] }}</strong><p class="mt-1 leading-relaxed text-slate-400">{{ $event['message'] }}</p><time class="mt-2 block text-[10px] text-slate-500">{{ \Illuminate\Support\Carbon::parse($event['at'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</time></li>
    @endforeach
</ol>
