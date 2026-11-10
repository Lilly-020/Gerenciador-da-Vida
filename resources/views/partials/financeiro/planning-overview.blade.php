<div class="mb-6 grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-indigo-400/25 bg-indigo-500/10 p-5">
        <p class="text-xs font-medium text-indigo-200">Saldo disponível</p>
        <p class="mt-2 text-3xl font-semibold text-white">@money($cards['saldo_disponivel'])</p>
        <p class="mt-2 text-xs text-slate-400">{{ \App\Support\Carteira::isOwn() ? 'Saldo acumulado até a referência, com movimentos realizados, aportes e resgates. Com contas cadastradas, lançamentos sem conta são considerados desde o primeiro saldo inicial.' : 'Visão dos seus movimentos nesta carteira. Valores de outras carteiras não estão incluídos.' }}</p>
    </div>
    <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-5"><p class="text-xs text-slate-400">A pagar até o fim do período</p><p class="mt-2 text-2xl font-semibold text-rose-300">@money($cards['a_pagar'])</p><p class="mt-2 text-xs text-slate-400">A receber: @money($cards['a_receber'])</p></div>
    <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-5"><p class="text-xs text-slate-400">Saldo projetado</p><p class="mt-2 text-2xl font-semibold {{ $cards['saldo_projetado'] >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">@money($cards['saldo_projetado'])</p><p class="mt-2 text-xs text-slate-400">Saldo + recebimentos previstos − pagamentos previstos até o fim do período, incluindo atrasos. Previsões não são dinheiro disponível.</p></div>
</div>
<div class="mb-6 grid gap-4 lg:grid-cols-2">
    <section class="min-w-0 rounded-xl border border-white/10 bg-slate-900/50 p-4">
        <h2 class="text-sm font-semibold text-white">Evolução mensal</h2><p class="mt-1 text-xs text-slate-500">Últimos seis meses até o fim do período · valores em R$ · movimentos realizados</p>
        @php($maxValue = max(1, $timeline->max('income'), $timeline->max('expense')))
        <div class="mt-4 grid gap-4">
            @foreach ($timeline as $point)
                <div>
                    <div class="flex flex-wrap justify-between gap-2 text-xs text-slate-400"><span>{{ $point['month'] }}</span><span>Saldo {{ number_format($point['balance'], 2, ',', '.') }}</span></div>
                    <div class="mt-1 grid gap-1"><div class="h-2 rounded-full bg-emerald-400/80" style="width: {{ $point['income'] / $maxValue * 100 }}%" title="Entradas: {{ number_format($point['income'], 2, ',', '.') }}"></div><div class="h-2 rounded-full bg-rose-400/80" style="width: {{ $point['expense'] / $maxValue * 100 }}%" title="Saídas: {{ number_format($point['expense'], 2, ',', '.') }}"></div></div>
                    <p class="mt-1 text-[11px] text-slate-500">Entradas {{ number_format($point['income'], 2, ',', '.') }} · Saídas {{ number_format($point['expense'], 2, ',', '.') }}</p>
                </div>
            @endforeach
        </div>
    </section>
    <section class="min-w-0 rounded-xl border border-white/10 bg-slate-900/50 p-4">
        <h2 class="text-sm font-semibold text-white">Contas a pagar e receber</h2><p class="mt-1 text-xs text-slate-500">Pendências até o fim do período, incluindo atrasadas</p>
        <div class="mt-4 grid gap-3">
            @forelse ($upcoming as $row)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 pb-2 text-xs"><div class="min-w-0"><p class="wrap-anywhere text-slate-200">{{ $row->description }}</p><p class="mt-1 {{ $row->situation === 'Atrasado' ? 'text-rose-300' : 'text-slate-400' }}">{{ $row->date->format('d/m/Y') }} · {{ $row->situation }} · {{ $row->entradaFor(auth()->user()) > 0 ? 'A receber' : 'A pagar' }}</p></div><strong class="text-slate-200">@money($row->entradaFor(auth()->user()) > 0 ? $row->entradaFor(auth()->user()) : $row->saidaFor(auth()->user()))</strong></div>
            @empty
                <p class="text-sm text-slate-400">Sem contas pendentes neste período.</p>
            @endforelse
        </div>
        <p class="mt-4 text-xs text-slate-500">Confirme o pagamento ou recebimento nas telas de entradas e saídas.</p>
    </section>
</div>
