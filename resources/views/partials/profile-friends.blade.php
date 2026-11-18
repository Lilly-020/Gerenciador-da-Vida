<section class="grid gap-5 rounded-2xl border border-white/10 bg-slate-900/70 p-5">
    <header><h2 class="text-lg font-semibold">Amigos</h2><p class="mt-2 text-sm text-slate-400">Compartilhe seu apelido. Ninguém pesquisa uma lista de usuários: é preciso saber o apelido completo.</p></header>
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-white/10 p-3"><strong>{{ $user->handle() }}</strong>@if ($user->apelido)<button type="button" data-copy-handle="{{ $user->handle() }}" class="quadro-tool"><x-card-icon name="copy" />Copiar meu apelido</button>@else<a href="{{ route('perfil', absolute: false) }}" class="text-indigo-300">Definir meu apelido</a>@endif</div>
    <form data-friend-request action="{{ route('amigos.store', absolute: false) }}" method="POST" class="grid gap-3 sm:grid-cols-[1fr_auto]">@csrf<label class="grid gap-2 text-sm">Adicionar pelo apelido<input name="apelido" required maxlength="31" autocomplete="off" autocapitalize="none" placeholder="@apelido completo"></label><button class="quadro-primary self-end">Enviar pedido</button><p data-friend-request-status role="status" class="text-sm text-indigo-300 sm:col-span-2"></p></form>
    <h3 class="text-sm font-semibold">Pedidos recebidos</h3>
    @forelse ($friendships->where('status', 'pending')->where('requester_id', '!=', $user->id) as $friendship)
        @php($person = $friendship->user_low_id === $user->id ? $friendship->highUser : $friendship->lowUser)
        <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 p-3"><span>{{ $person->name }} · {{ $person->handle() }}</span><form method="POST" action="{{ route('amigos.respond', $friendship, absolute: false) }}" class="flex gap-3">@csrf<button name="accept" value="1" class="quadro-primary">Aceitar amizade</button><button name="accept" value="0" class="quadro-tool">Recusar</button></form></article>
    @empty<p class="text-sm text-slate-500">Nenhum pedido recebido.</p>@endforelse
    <h3 class="text-sm font-semibold">Seus amigos</h3>
    @forelse ($friendships->where('status', 'accepted') as $friendship)
        @php($person = $friendship->user_low_id === $user->id ? $friendship->highUser : $friendship->lowUser)
        <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 p-3"><span>{{ $person->name }} · {{ $person->handle() }}</span><form method="POST" action="{{ route('amigos.destroy', $friendship, absolute: false) }}">@csrf @method('DELETE')<button class="quadro-tool text-rose-300">Remover amizade</button></form></article>
    @empty<p class="text-sm text-slate-500">Você ainda não adicionou amigos.</p>@endforelse
    @foreach ($friendships->where('status', 'pending')->where('requester_id', $user->id) as $friendship)
        @php($person = $friendship->user_low_id === $user->id ? $friendship->highUser : $friendship->lowUser)
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-3 text-sm text-slate-400"><span>Pedido enviado para {{ $person->handle() }}</span><form method="POST" action="{{ route('amigos.destroy', $friendship, absolute: false) }}">@csrf @method('DELETE')<button class="text-rose-300">Cancelar pedido</button></form></div>
    @endforeach
</section>
