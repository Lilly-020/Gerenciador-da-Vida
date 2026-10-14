{{--
    "Dividir com participantes" fields for a lançamento / custo fixo form:
    one amount per member of the carteira, adding up to the form's total
    (the owner's may be left blank to mean "the rest").
    Posted flat as parte_u{userId} — see ValidatesPartes.
--}}
@if ($membros->count() > 1)
    <div>
        <p class="mb-1.5 block text-sm font-medium text-slate-300">Participantes (opcional)</p>
        <div class="grid grid-cols-2 gap-3">
            @foreach ($membros as $membro)
                <div>
                    <label for="{{ $prefix }}-parte-{{ $membro->id }}" class="mb-1 block text-xs text-slate-400">
                        Parte de {{ $membro->handle() }}
                    </label>
                    <input
                        id="{{ $prefix }}-parte-{{ $membro->id }}"
                        type="number"
                        step="0.01"
                        min="0"
                        name="parte_u{{ $membro->id }}"
                        data-money
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>
            @endforeach
        </div>
        <p class="mt-1.5 text-xs text-slate-500">
            Quanto do valor acima {{ $isEntrada ? 'vai para' : 'é pago por' }} cada um — as partes somam o valor total.
            Deixando a de {{ $membros->first()->handle() }} em branco, ela fica com o restante; deixe tudo em branco para não dividir.
        </p>
    </div>
@endif
