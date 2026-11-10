@props(['type', 'categories', 'mes', 'membros', 'accounts' => []])
@php
    $isEntrada = $type === 'entrada';
    $fieldClass = 'w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-indigo-400';
@endphp
<div id="new-lancamento-modal" class="board-modal fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center px-4 py-8"><div class="modal-panel w-full max-w-md rounded-2xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
        <div class="mb-5 flex items-center justify-between gap-4"><h2 class="text-lg font-semibold text-white">Nova {{ $isEntrada ? 'entrada' : 'saída' }}</h2><button type="button" data-modal-close aria-label="Fechar" class="text-slate-400">×</button></div>
        <form id="new-lancamento-form" action="{{ route('financeiro.lancamentos.store', ['mes' => $mes], absolute: false) }}" method="POST" class="grid gap-4">@csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <label class="grid gap-1.5 text-sm text-slate-300">Descrição<input name="description" required maxlength="255" class="{{ $fieldClass }}"></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="grid gap-1.5 text-sm text-slate-300">Valor total (R$)<input name="amount" type="number" step="0.01" min="0.01" data-money required class="{{ $fieldClass }}"></label>
                <label class="grid gap-1.5 text-sm text-slate-300">Data / primeiro vencimento<input name="date" type="date" value="{{ today()->toDateString() }}" required class="{{ $fieldClass }} scheme-dark"></label>
            </div>
            <label class="grid gap-1.5 text-sm text-slate-300">Categoria<select name="category" required data-category-picker data-category-type="{{ $type }}" data-category-url="{{ route('financeiro.categorias.index', absolute: false) }}" class="{{ $fieldClass }}"><option value="">Selecione uma categoria</option>@foreach ($categories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></label>
            <label class="grid gap-1.5 text-sm text-slate-300">Situação<select name="status" class="{{ $fieldClass }}"><option value="previsto">Previsto — {{ $isEntrada ? 'a receber' : 'a pagar' }}</option><option value="realizado">{{ $isEntrada ? 'Recebido' : 'Pago' }}</option></select></label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_recurring_template" value="1" data-recurring-toggle class="h-4 w-4 accent-indigo-500">Repetir este lançamento</label>
            <div data-recurring-fields class="hidden grid-cols-2 gap-3 rounded-xl border border-white/10 p-3">
                <label class="grid gap-1 text-xs text-slate-400">Frequência<select name="recurrence_period" class="{{ $fieldClass }}"><option value="mensal">Mensal</option><option value="semanal">Semanal</option><option value="anual">Anual</option></select></label>
                <label class="grid gap-1 text-xs text-slate-400">Dia de vencimento<input type="number" name="recurrence_day" min="1" max="31" class="{{ $fieldClass }}"></label>
                <label class="grid gap-1 text-xs text-slate-400">Término (opcional)<input type="date" name="recurrence_end" class="{{ $fieldClass }} scheme-dark"></label>
                <label class="grid gap-1 text-xs text-slate-400">Repetições, incluindo a primeira<input type="number" name="recurrence_count" min="1" max="1000" class="{{ $fieldClass }}"></label>
                <label class="grid gap-1 text-xs text-slate-400 col-span-2">Gerar próximas como<select name="recurrence_status" class="{{ $fieldClass }}"><option value="previsto">Previsto (confirmar depois)</option><option value="realizado">Realizado (confirmação automática)</option></select></label>
                <p class="col-span-2 text-xs text-slate-500">Semanal repete a data inicial a cada 7 dias. Nos meses mais curtos, o vencimento usa o último dia.</p>
            </div>
            <details class="rounded-xl border border-white/10 p-3"><summary class="cursor-pointer text-sm text-indigo-300">Mais opções: conta, parcelas e observações</summary><div class="mt-4 grid gap-3">
                <label class="grid gap-1 text-xs text-slate-400">{{ $isEntrada ? 'Conta de recebimento' : 'Conta de pagamento' }}<select name="account_id" class="{{ $fieldClass }}"><option value="">Sem conta definida</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select></label>
                @unless ($isEntrada)
                <label class="grid gap-1 text-xs text-slate-400">Tipo de gasto<select name="expense_kind" class="{{ $fieldClass }}"><option value="variavel">Variável</option><option value="fixo">Fixo</option></select></label>
                @endunless
                <label class="grid gap-1 text-xs text-slate-400">{{ $isEntrada ? 'Forma de recebimento' : 'Forma de pagamento' }}<select name="payment_method" class="{{ $fieldClass }}"><option value="">Não informada</option>@foreach (($isEntrada ? ['Pix', 'Transferência', 'Dinheiro', 'Boleto', 'Cartão'] : ['Pix', 'Débito', 'Crédito', 'Dinheiro', 'Boleto', 'Transferência']) as $method)<option>{{ $method }}</option>@endforeach</select></label>
                <label class="grid gap-1 text-xs text-slate-400">{{ $isEntrada ? 'Receber em quantas parcelas?' : 'Número de parcelas' }}<input name="installments" type="number" min="1" max="120" value="1" required class="{{ $fieldClass }}"></label>
                <p class="text-xs text-slate-500">{{ $isEntrada ? 'Para receber um valor combinado em partes: R$ 900 em 3 parcelas gera recebimentos de R$ 300 por mês. Use 1 para receber de uma vez. Para salário mensal, use recorrência.' : 'O valor total será dividido em parcelas mensais. As próximas ficam previstas. Use parcelas ou recorrência.' }}</p>
                <label class="grid gap-1 text-xs text-slate-400">Subcategoria<input name="subcategory" maxlength="255" class="{{ $fieldClass }}"></label>
                <label class="grid gap-1 text-xs text-slate-400">Observações<textarea name="notes" rows="2" maxlength="2000" class="{{ $fieldClass }}"></textarea></label>
                @include('partials.financeiro.partes-fields', ['prefix' => 'lancamento', 'membros' => $membros, 'isEntrada' => $isEntrada])
                <p class="text-xs text-slate-500">Comprovantes podem ser anexados após salvar o lançamento.</p>
            </div></details>
            <div class="flex justify-end gap-2"><button type="button" data-modal-close class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300">Cancelar</button><button type="submit" class="rounded-full bg-indigo-500 px-5 py-2 text-sm font-semibold text-white">Adicionar {{ $isEntrada ? 'entrada' : 'saída' }}</button></div>
        </form>
    </div></div>
</div>
