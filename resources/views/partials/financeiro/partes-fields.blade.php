{{--
    Participant fields for a lançamento / custo fixo form, shown when the
    carteira has participants. Posted flat as parte_u{userId} — see
    ValidatesPartes.

    Expense: one part per member, adding up to the form's total (the
    owner's may be left blank to mean "the rest"), plus whose account the
    participants' parts come out of.
    Income: how much of it the owner received from each participant.
--}}
@if ($membros->count() > 1)
    @php
        $dono = $membros->first();
        $campos = $isEntrada ? $membros->skip(1) : $membros;
    @endphp

    <div>
        <p class="mb-1.5 block text-sm font-medium text-slate-300">
            {{ $isEntrada ? 'Recebido de participantes (opcional)' : 'Participantes (opcional)' }}
        </p>
        <div class="grid grid-cols-2 gap-3">
            @foreach ($campos as $membro)
                <div>
                    <label for="{{ $prefix }}-parte-{{ $membro->id }}" class="mb-1 block text-xs text-slate-400">
                        {{ $isEntrada ? 'Veio de' : 'Parte de' }} {{ $membro->handle() }}
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

        @if ($isEntrada)
            <p class="mt-1.5 text-xs text-slate-500">
                Quanto deste valor {{ $dono->handle() }} recebeu de cada participante. O valor inteiro entra na conta de {{ $dono->handle() }}; essa parte sai da conta do participante.
            </p>
        @else
            <label for="{{ $prefix }}-partes-conta" class="mt-3 mb-1 block text-xs text-slate-400">A parte dos participantes sai</label>
            <select
                id="{{ $prefix }}-partes-conta"
                name="partes_conta"
                class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
            >
                <option value="participante">Da conta de cada participante</option>
                <option value="dono">Da conta de {{ $dono->handle() }} (paga o valor todo)</option>
            </select>
            <p class="mt-1.5 text-xs text-slate-500">
                As partes somam o valor total. Deixando a de {{ $dono->handle() }} em branco, ela fica com o restante; deixe tudo em branco para não dividir.
            </p>
        @endif
    </div>
@endif
