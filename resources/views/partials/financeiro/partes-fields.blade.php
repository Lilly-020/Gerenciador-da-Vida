{{--
    "Dividir com participantes" fields for a lançamento / custo fixo form:
    one amount per participant of the carteira (its owner keeps the rest).
    Posted flat as parte_u{userId} — see ValidatesPartes.
--}}
@if ($membros->count() > 1)
    <div>
        <p class="mb-1.5 block text-sm font-medium text-slate-300">Participantes (opcional)</p>
        <div class="grid grid-cols-2 gap-3">
            @foreach ($membros->skip(1) as $membro)
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
            Quanto do valor {{ $isEntrada ? 'vai para' : 'é pago por' }} cada participante. O restante fica com {{ $membros->first()->handle() }}; deixe em branco para não dividir.
        </p>
    </div>
@endif
