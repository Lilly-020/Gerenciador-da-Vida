@php($fieldClass = 'w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400')
<label class="grid gap-1.5 text-sm text-slate-300">Nome<input name="name" required maxlength="255" class="{{ $fieldClass }}"></label>
<div class="grid grid-cols-2 gap-3">
    <label class="grid gap-1.5 text-sm text-slate-300">Tipo<select name="type" required class="{{ $fieldClass }}">@foreach (\App\Models\Investimento::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
    <label class="grid gap-1.5 text-sm text-slate-300">Instituição<input name="institution" maxlength="255" class="{{ $fieldClass }}"></label>
</div>
@if ($creating)
    <label class="grid gap-1.5 text-sm text-slate-300">Valor inicial (R$)<input name="initial_amount" type="number" data-money min="0" step="0.01" value="0" class="{{ $fieldClass }}"></label>
    <div class="grid grid-cols-2 gap-3">
        <label class="grid gap-1.5 text-xs text-slate-400">Data do aporte<input name="initial_date" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" class="{{ $fieldClass }} scheme-dark"></label>
        <label class="grid gap-1.5 text-xs text-slate-400">Conta de origem<select name="account_id" class="{{ $fieldClass }}"><option value="">Sem conta definida</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></label>
    </div>
@endif
<details class="rounded-xl border border-white/10 p-3"><summary class="cursor-pointer text-sm text-indigo-300">Rentabilidade e detalhes avançados</summary>
    <div class="mt-4 grid gap-3">
        <label class="grid gap-1.5 text-xs text-slate-400">Modalidade da taxa<select name="rate_mode" class="{{ $fieldClass }}"><option value="fixed">Taxa fixa (% ao mês ou ao ano)</option><option value="cdi">Percentual do CDI (ex.: 110)</option><option value="ipca">IPCA + taxa anual (ex.: 6)</option></select></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="grid gap-1.5 text-xs text-slate-400">Taxa / percentual<input name="rate" type="number" min="0" max="1000" step="0.0001" value="0" required class="{{ $fieldClass }}"></label>
            <label class="grid gap-1.5 text-xs text-slate-400">Período (taxa fixa)<select name="rate_period" class="{{ $fieldClass }}"><option value="anual">Ao ano</option><option value="mensal">Ao mês</option></select></label>
        </div>
        <label class="grid gap-1.5 text-xs text-slate-400">CDI ou IPCA anual para a simulação (%)<input name="reference_rate" type="number" min="0" max="100" step="0.0001" class="{{ $fieldClass }}"></label>
        <p class="text-xs text-slate-500">Índice informado por você, mantido constante na estimativa. O CDI usa equivalência de 252 dias úteis; o IPCA é combinado à taxa anual. Não há atualização automática dos índices.</p>
        <label class="grid gap-1.5 text-xs text-slate-400">Referência / observação da taxa<input name="rate_reference" maxlength="255" class="{{ $fieldClass }}"></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="grid gap-1.5 text-xs text-slate-400">Liquidez<input name="liquidity" maxlength="255" placeholder="Ex.: diária" class="{{ $fieldClass }}"></label>
            <label class="grid gap-1.5 text-xs text-slate-400">Vencimento<input name="maturity_date" type="date" class="{{ $fieldClass }} scheme-dark"></label>
        </div>
        <label class="grid gap-1.5 text-xs text-slate-400">IR estimado sobre rendimento (%)<input name="ir_rate" type="number" min="0" max="100" step="0.01" value="0" required class="{{ $fieldClass }}"></label>
        <label class="grid gap-1.5 text-xs text-slate-400">IOF estimado sobre rendimento (%)<input name="iof_rate" type="number" min="0" max="100" step="0.01" value="0" required class="{{ $fieldClass }}"></label>
        <label class="grid gap-1.5 text-xs text-slate-400">Taxas estimadas da instituição (R$)<input name="estimated_fees" type="number" data-money min="0" step="0.01" value="0" required class="{{ $fieldClass }}"></label>
        <p class="text-xs text-slate-500">Informe as alíquotas aplicáveis ao produto e ao prazo. A simulação desconta IOF antes do IR e as taxas informadas; não calcula automaticamente regras tributárias ou isenções.</p>
        <label class="grid gap-1.5 text-xs text-slate-400">Valor real informado no extrato (R$)<input name="reported_value" type="number" data-money min="0" step="0.01" class="{{ $fieldClass }}"></label>
        <label class="grid gap-1.5 text-xs text-slate-400">Data do extrato<input name="reported_at" type="date" max="{{ today()->toDateString() }}" class="{{ $fieldClass }} scheme-dark"></label>
        <label class="grid gap-1.5 text-xs text-slate-400">Observações<textarea name="notes" maxlength="2000" rows="2" class="{{ $fieldClass }}"></textarea></label>
    </div>
</details>
<p class="text-xs text-slate-500">Rendimentos calculados são estimativas. O valor do extrato é registrado separadamente.</p>
