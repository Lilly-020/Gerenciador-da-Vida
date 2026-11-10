@php
    $canManage = \App\Support\Carteira::canManage() && ! \App\Support\Carteira::hasRestrictions();
    $canTransfer = \App\Support\Carteira::canEdit() && ! \App\Support\Carteira::hasRestrictions();
    $inputClass = 'w-full min-w-0 rounded-lg border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-400/25';
    $typeLabels = ['banco' => 'Conta bancária', 'dinheiro' => 'Dinheiro em espécie', 'outro' => 'Outra conta'];
@endphp
<x-layout title="Contas e categorias" align="start">
    <div class="w-full min-w-0 max-w-6xl">
        <x-financeiro-subnav active="financeiro.contas" />
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Contas e categorias</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-400">Contas mostram onde o dinheiro está. Categorias mostram com o que você ganha ou gasta.</p>
            </div>
            @if ($canManage)<a href="#nova-conta" class="rounded-full bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">+ Nova conta</a>@endif
        </div>
        <nav aria-label="Nesta página" class="mb-6 flex flex-wrap gap-2 text-xs text-slate-300">
            <a href="#minhas-contas" class="rounded-full border border-white/10 px-3 py-2 hover:bg-white/5">Minhas contas</a>
            @if ($canTransfer)<a href="#transferencias" class="rounded-full border border-white/10 px-3 py-2 hover:bg-white/5">Transferências</a>@endif
            @if ($canManage)<a href="#categorias" class="rounded-full border border-white/10 px-3 py-2 hover:bg-white/5">Categorias</a>@endif
        </nav>
        @if (\App\Support\Carteira::hasRestrictions())<p class="mb-4 rounded-lg border border-amber-400/20 bg-amber-400/5 p-3 text-xs text-amber-300">Visão parcial: os saldos incluem apenas os lançamentos compartilhados, sem saldo inicial e transferências.</p>@endif

        <section id="minhas-contas" class="scroll-mt-6">
            <div class="mb-3 flex items-center gap-2"><h2 class="text-base font-semibold text-white">Minhas contas</h2><span class="rounded-full bg-white/5 px-2 py-0.5 text-xs text-slate-400">{{ $accounts->count() }}</span></div>
            <p class="mb-4 text-xs text-slate-400">Ao cadastrar uma entrada ou saída, escolha a conta para atualizar o saldo dela.</p>
            <div class="grid items-start gap-4 sm:grid-cols-2">
                @forelse ($accounts as $account)
                    <article class="min-w-0 rounded-xl border border-white/10 bg-slate-900/70 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="wrap-anywhere font-semibold text-white">{{ $account->name }}</h3><span class="text-xs text-slate-400">{{ $typeLabels[$account->kind] ?? 'Outra conta' }}</span></div>
                        <p class="mt-4 text-xs text-slate-400">{{ \App\Support\Carteira::hasRestrictions() ? 'Saldo compartilhado' : 'Saldo atual' }}</p>
                        <p class="mt-1 text-2xl font-semibold text-white">@money($account->balanceAsOf())</p>
                        @if ($canManage)
                            <p class="mt-3 border-t border-white/5 pt-3 text-xs text-slate-500">Começou com @money($account->opening_balance) em {{ $account->opening_date->format('d/m/Y') }}.</p>
                            <details class="mt-3 text-xs"><summary class="cursor-pointer text-indigo-300">Renomear conta</summary>
                                <form method="POST" action="{{ route('financeiro.contas.update', $account, absolute: false) }}" class="mt-3 flex flex-wrap items-center gap-2">@csrf @method('PUT')<input name="name" required maxlength="255" value="{{ $account->name }}" aria-label="Nome da conta" class="{{ $inputClass }} flex-1"><button class="shrink-0 rounded-lg border border-white/10 px-3 py-2 text-indigo-200">Salvar nome</button></form>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-white/15 p-6 text-sm text-slate-400 sm:col-span-2">Você ainda não cadastrou contas. Comece pelo banco que usa no dia a dia ou pelo dinheiro em espécie.</div>
                @endforelse
            </div>
        </section>

        @if ($canManage)
            <section id="nova-conta" class="mt-6 scroll-mt-6 rounded-xl border border-white/10 bg-slate-900/50 p-5">
                <h2 class="text-base font-semibold text-white">Adicionar conta</h2>
                <p class="mt-1 text-xs text-slate-400">Exemplos: Sicredi, Nubank ou dinheiro na carteira.</p>
                <form method="POST" action="{{ route('financeiro.contas.store', absolute: false) }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf
                    <label class="grid gap-1.5 text-xs text-slate-300">Nome da conta<input name="name" required maxlength="255" placeholder="Ex.: Sicredi" class="{{ $inputClass }}"></label>
                    <label class="grid gap-1.5 text-xs text-slate-300">Tipo<select name="kind" class="{{ $inputClass }}">@foreach ($typeLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label class="grid gap-1.5 text-xs text-slate-300">Quanto havia na conta? (R$)<input type="number" name="opening_balance" step="0.01" value="0" required class="{{ $inputClass }}"></label>
                    <label class="grid gap-1.5 text-xs text-slate-300">Data desse saldo<input type="date" name="opening_date" value="{{ today()->toDateString() }}" required class="{{ $inputClass }} scheme-dark"></label>
                    <p class="text-xs leading-relaxed text-slate-500 sm:col-span-2">Use o saldo antes dos movimentos da data escolhida. Por exemplo: se começou o dia com R$ 500, informe 500 e registre os pagamentos e recebimentos desse dia em Entradas e Saídas.</p>
                    <button class="justify-self-start rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Adicionar conta</button>
                </form>
            </section>
        @endif

        @if ($canTransfer)
            <section id="transferencias" class="mt-6 scroll-mt-6 rounded-xl border border-white/10 bg-slate-900/50 p-5">
                <h2 class="text-base font-semibold text-white">Transferir entre contas</h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-400">Mova dinheiro que já é seu. Exemplo: sacar R$ 100 do banco e colocar em Dinheiro em espécie. O total permanece igual.</p>
                @if ($accounts->count() < 2)
                    <div class="mt-4 rounded-lg border border-indigo-400/20 bg-indigo-400/5 p-4 text-sm text-slate-300">Você precisa de pelo menos duas contas para transferir dinheiro.
                        @if ($canManage)<a href="#nova-conta" class="mt-2 block text-xs font-semibold text-indigo-300 underline">Adicionar outra conta</a>@endif
                    </div>
                @else
                    <form method="POST" action="{{ route('financeiro.transferencias.store', absolute: false) }}" data-account-transfer class="mt-4 grid gap-4 sm:grid-cols-2">@csrf
                        @foreach (['source_id' => 'De qual conta?', 'destination_id' => 'Para qual conta?'] as $field => $label)
                            <label class="grid gap-1.5 text-xs text-slate-300">{{ $label }}
                                <select name="{{ $field }}" required class="{{ $inputClass }}" @error($field) aria-invalid="true" @enderror>
                                    <option value="">Selecione uma conta</option>
                                    @foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((string) old($field) === (string) $account->id)>{{ $account->name }}</option>@endforeach
                                </select>
                                @error($field)<span class="text-rose-300">{{ $message }}</span>@enderror
                            </label>
                        @endforeach
                        <label class="grid gap-1.5 text-xs text-slate-300">Valor a transferir (R$)<input name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount') }}" class="{{ $inputClass }}">@error('amount')<span class="text-rose-300">{{ $message }}</span>@enderror</label>
                        <label class="grid gap-1.5 text-xs text-slate-300">Data da transferência<input name="date" type="date" max="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required class="{{ $inputClass }} scheme-dark">@error('date')<span class="text-rose-300">{{ $message }}</span>@enderror</label>
                        <p class="text-xs text-slate-500 sm:col-span-2">A origem e o destino devem ser diferentes. Para pagar uma compra, use Saídas; para registrar dinheiro recebido, use Entradas.</p>
                        <button type="submit" class="justify-self-start rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Confirmar transferência</button>
                    </form>
                @endif
                @if ($transfers->isNotEmpty())
                    <details class="mt-5 border-t border-white/10 pt-4"><summary class="cursor-pointer text-xs text-indigo-300">Transferências recentes ({{ $transfers->count() }})</summary><ul class="mt-3 grid gap-3 text-xs text-slate-400">@foreach ($transfers as $transfer)<li class="flex flex-wrap justify-between gap-2"><span>{{ $transfer->date->format('d/m/Y') }} · {{ $accounts->firstWhere('id', $transfer->source_id)?->name }} → {{ $accounts->firstWhere('id', $transfer->destination_id)?->name }}</span><strong class="text-slate-200">@money($transfer->amount)</strong></li>@endforeach</ul></details>
                @endif
            </section>
        @endif

        @if ($canManage)
            <section id="categorias" class="mt-6 scroll-mt-6 rounded-xl border border-white/10 bg-slate-900/50 p-5">
                <h2 class="text-base font-semibold text-white">Categorias personalizadas</h2>
                <p class="mt-1 text-xs text-slate-400">Categorias organizam seus lançamentos. Exemplos: Moradia e Alimentação para saídas, Salário e Freelance para entradas.</p>
                <form method="POST" action="{{ route('financeiro.categorias.store', absolute: false) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
                    <label class="grid min-w-0 flex-1 gap-1.5 text-xs text-slate-300">Nome da categoria<input name="name" required maxlength="255" placeholder="Ex.: Cuidados com a casa" class="{{ $inputClass }}"></label>
                    <label class="grid gap-1.5 text-xs text-slate-300">Usar em<select name="type" class="{{ $inputClass }}"><option value="saida">Saídas</option><option value="entrada">Entradas</option></select></label>
                    <button class="rounded-lg bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400">Criar categoria</button>
                </form>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @forelse ($categories as $category)
                        <details class="min-w-0 rounded-lg border border-white/10 p-3"><summary class="cursor-pointer wrap-anywhere text-sm text-slate-300">{{ $category->name }} <span class="text-xs text-slate-500">· {{ $category->type === 'saida' ? 'Saídas' : 'Entradas' }}</span></summary><form method="POST" action="{{ route('financeiro.categorias.update', $category, absolute: false) }}" class="mt-3 flex flex-wrap items-center gap-2">@csrf @method('PUT')<input name="name" value="{{ $category->name }}" aria-label="Nome da categoria" maxlength="255" required class="{{ $inputClass }} flex-1"><button class="shrink-0 text-xs text-indigo-300">Salvar nome</button></form></details>
                    @empty
                        <p class="text-xs text-slate-500 sm:col-span-2">As categorias padrão continuam disponíveis nos lançamentos. Crie aqui apenas as categorias extras que precisar.</p>
                    @endforelse
                </div>
            </section>
            @include('partials.financeiro.activity-history')
        @endif
    </div>
</x-layout>
