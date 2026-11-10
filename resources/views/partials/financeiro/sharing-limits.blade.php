<details class="rounded-xl border border-white/10 p-3">
    <summary class="cursor-pointer text-sm text-indigo-300">Limitar contas, categorias ou lançamentos</summary>
    <p class="mt-2 text-xs text-slate-400">Sem seleção, todas as informações das seções marcadas são compartilhadas. Com vários limites, o lançamento precisa atender a todos. Investimentos têm uma seção própria.</p>
    <div class="mt-3 grid gap-3">
        <label class="grid gap-1 text-xs text-slate-400">Contas<select name="allowed_accounts[]" multiple class="rounded-lg border border-white/10 bg-slate-950 p-2 text-sm text-white">@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected(in_array((string) $account->id, array_map('strval', $invitation?->allowed_accounts ?? []), true))>{{ $account->name }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-xs text-slate-400">Categorias<select name="allowed_categories[]" multiple class="rounded-lg border border-white/10 bg-slate-950 p-2 text-sm text-white">@foreach ($categories as $category)<option @selected(in_array($category, $invitation?->allowed_categories ?? [], true))>{{ $category }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-xs text-slate-400">Lançamentos específicos (últimos 200)<select name="allowed_lancamentos[]" multiple class="rounded-lg border border-white/10 bg-slate-950 p-2 text-sm text-white">@foreach ($lancamentos as $row)<option value="{{ $row->id }}" @selected(in_array((string) $row->id, array_map('strval', $invitation?->allowed_lancamentos ?? []), true))>{{ $row->date->format('d/m/Y') }} · {{ $row->description }}</option>@endforeach</select></label>
    </div>
</details>
