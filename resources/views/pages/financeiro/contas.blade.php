<x-layout title="Contas e categorias" align="start">
    <div class="w-full min-w-0 max-w-5xl">
        <x-financeiro-subnav active="financeiro.contas" />
        <h1 class="text-2xl font-semibold text-white">Contas e carteiras</h1>
        <p class="mt-2 text-sm text-slate-400">Organize onde seu dinheiro está. Transferências entre contas não são receitas nem despesas.</p>
        @if (\App\Support\Carteira::hasRestrictions())<p class="mt-2 text-xs text-amber-300">Visão parcial: o saldo mostra apenas os lançamentos compartilhados, sem o saldo inicial e transferências.</p>@endif
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @forelse ($accounts as $account)
                <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
                    <h2 class="text-sm font-medium text-slate-300">{{ $account->name }} · {{ $account->kind }}</h2>
                    <p class="mt-2 text-2xl font-semibold text-white">@money($account->balanceAsOf())</p>
                    @if (\App\Support\Carteira::canManage() && ! \App\Support\Carteira::hasRestrictions())
                        <details class="mt-3 text-xs text-slate-400"><summary class="cursor-pointer text-indigo-300">Dados da conta</summary>
                            <p class="mt-2">Saldo inicial @money($account->opening_balance) em {{ $account->opening_date->format('d/m/Y') }}</p>
                            <form method="POST" action="{{ route('financeiro.contas.update', $account, absolute: false) }}" class="mt-3 flex flex-wrap gap-2">@csrf @method('PUT')<input name="name" required maxlength="255" value="{{ $account->name }}" aria-label="Nome da conta" class="min-w-0 flex-1 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white"><button class="text-indigo-300">Renomear</button></form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-400">Cadastre suas contas para acompanhar o saldo acumulado. Os lançamentos antigos permanecem sem conta até serem editados.</p>
            @endforelse
        </div>
        @if (\App\Support\Carteira::canManage() && ! \App\Support\Carteira::hasRestrictions())
            <details class="mt-6 rounded-xl border border-white/10 bg-slate-900/50 p-4"><summary class="cursor-pointer text-sm text-indigo-300">+ Nova conta</summary>
                <form method="POST" action="{{ route('financeiro.contas.store', absolute: false) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                    <label class="grid gap-1 text-xs text-slate-400">Nome<input name="name" required maxlength="255" class="rounded-lg border border-white/10 bg-slate-950 p-2 text-white"></label>
                    <label class="grid gap-1 text-xs text-slate-400">Tipo<select name="kind" class="rounded-lg border border-white/10 bg-slate-950 p-2 text-white"><option value="banco">Banco</option><option value="dinheiro">Dinheiro</option><option value="outro">Outro</option></select></label>
                    <label class="grid gap-1 text-xs text-slate-400">Saldo inicial (R$)<input type="number" name="opening_balance" step="0.01" value="0" required class="rounded-lg border border-white/10 bg-slate-950 p-2 text-white"></label>
                    <label class="grid gap-1 text-xs text-slate-400">Data do saldo inicial<input type="date" name="opening_date" value="{{ today()->toDateString() }}" required class="rounded-lg border border-white/10 bg-slate-950 p-2 text-white scheme-dark"></label>
                    <p class="text-xs text-slate-500 sm:col-span-2">Informe o saldo imediatamente antes dos lançamentos desta data. Movimentos anteriores não serão somados novamente a esta conta.</p>
                    <button class="justify-self-start rounded-lg bg-indigo-500 px-4 py-2 text-sm text-white">Criar conta</button>
                </form>
            </details>
        @endif
        @if (\App\Support\Carteira::canEdit() && ! \App\Support\Carteira::hasRestrictions())
            <details class="mt-4 rounded-xl border border-white/10 p-4"><summary class="cursor-pointer text-sm text-indigo-300">Transferir entre contas</summary>
                <form method="POST" action="{{ route('financeiro.transferencias.store', absolute: false) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                    @foreach (['source_id' => 'Origem', 'destination_id' => 'Destino'] as $field => $label)<label class="grid gap-1 text-xs text-slate-400">{{ $label }}<select name="{{ $field }}" required class="rounded-lg border border-white/10 bg-slate-950 p-2 text-white">@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></label>@endforeach
                    <label class="grid gap-1 text-xs text-slate-400">Valor (R$)<input name="amount" type="number" min="0.01" step="0.01" required class="rounded-lg border border-white/10 bg-slate-950 p-2 text-white"></label>
                    <label class="grid gap-1 text-xs text-slate-400">Data<input name="date" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required class="rounded-lg border border-white/10 bg-slate-950 p-2 text-white scheme-dark"></label>
                    <button class="justify-self-start rounded-lg bg-indigo-500 px-4 py-2 text-sm text-white">Transferir</button>
                </form>
                <ul class="mt-4 grid gap-2 text-xs text-slate-400">@foreach ($transfers as $transfer)<li>{{ $transfer->date->format('d/m/Y') }} · {{ $accounts->firstWhere('id', $transfer->source_id)?->name }} → {{ $accounts->firstWhere('id', $transfer->destination_id)?->name }} · @money($transfer->amount)</li>@endforeach</ul>
            </details>
        @endif
        @if (\App\Support\Carteira::canManage() && ! \App\Support\Carteira::hasRestrictions())
            <details class="mt-4 rounded-xl border border-white/10 p-4"><summary class="cursor-pointer text-sm text-indigo-300">Categorias personalizadas</summary>
                <form method="POST" action="{{ route('financeiro.categorias.store', absolute: false) }}" class="mt-4 flex flex-wrap gap-2">@csrf
                    <input name="name" required maxlength="255" placeholder="Nome da categoria" aria-label="Nome da categoria" class="min-w-0 flex-1 rounded-lg border border-white/10 bg-slate-950 p-2 text-sm text-white">
                    <select name="type" aria-label="Tipo de categoria" class="rounded-lg border border-white/10 bg-slate-950 p-2 text-sm text-white"><option value="saida">Saída</option><option value="entrada">Entrada</option></select><button class="text-sm text-indigo-300">Criar categoria</button>
                </form>
                <div class="mt-4 grid gap-3">@foreach ($categories as $category)<form method="POST" action="{{ route('financeiro.categorias.update', $category, absolute: false) }}" class="flex flex-wrap items-center gap-2 text-xs">@csrf @method('PUT')<span class="text-slate-400">{{ $category->type }}</span><input name="name" value="{{ $category->name }}" aria-label="Categoria" maxlength="255" required class="min-w-0 flex-1 rounded-lg border border-white/10 bg-slate-950 p-2 text-white"><button class="text-indigo-300">Renomear</button></form>@endforeach</div>
            </details>
            @include('partials.financeiro.activity-history')
        @endif
    </div>
</x-layout>
