<x-layout title="Participantes" align="start">
    <div class="w-full max-w-4xl">
        <x-financeiro-subnav active="financeiro.participantes" />

        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-white sm:text-3xl">Participantes</h1>
            <p class="mt-1 text-sm text-slate-400">
                Compartilhe o seu Financeiro com outras pessoas. Elas só passam a ver depois de aceitar o convite.
            </p>
        </div>

        <form method="POST" action="{{ route('participacoes.store', absolute: false) }}" class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6 mb-4 space-y-4">
            @csrf
            <input type="hidden" name="tipo" value="financas">

            <h2 class="text-base font-semibold text-white">Convidar alguém</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="convite-apelido" class="mb-1.5 block text-sm font-medium text-slate-300">Apelido</label>
                    <input
                        id="convite-apelido"
                        type="text"
                        name="apelido"
                        required
                        maxlength="31"
                        autocapitalize="none"
                        autocomplete="off"
                        list="apelido-sugestoes"
                        data-apelido-search
                        value="{{ old('apelido') }}"
                        placeholder="Apelido de quem convidar"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                    <datalist id="apelido-sugestoes"></datalist>
                </div>
                <div>
                    <label for="convite-permission" class="mb-1.5 block text-sm font-medium text-slate-300">Permissão</label>
                    <select id="convite-permission" name="permission" class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20">
                        @foreach (\App\Models\Participacao::PERMISSIONS as $value => $label)
                            <option value="{{ $value }}" @selected(old('permission') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <p class="mb-1.5 text-sm font-medium text-slate-300">O que essa pessoa vai ver</p>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach (\App\Models\Participacao::SECOES as $value => $label)
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-300">
                            <input
                                type="checkbox"
                                name="sections[]"
                                value="{{ $value }}"
                                @checked(in_array($value, old('sections', array_keys(\App\Models\Participacao::SECOES)), true))
                                class="h-4 w-4 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-indigo-500/90 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500">
                    Enviar convite
                </button>
            </div>
        </form>

        <section class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6">
            <h2 class="mb-3 text-base font-semibold text-white">Quem participa</h2>

            @forelse ($participacoes as $participacao)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 py-3 first:border-t-0 first:pt-0 last:pb-0">
                    <div class="min-w-0 text-sm text-slate-300">
                        <p class="wrap-break-word font-semibold text-white">{{ $participacao->participant->handle() }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            @if ($participacao->status === \App\Models\Participacao::STATUS_PENDENTE)
                                <span class="text-amber-300">Aguardando aceitar</span> ·
                            @endif
                            {{ \App\Models\Participacao::PERMISSIONS[$participacao->permission] }}
                            · {{ implode(', ', $participacao->sectionLabels()) }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('participacoes.destroy', $participacao, absolute: false) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-slate-400 transition hover:text-rose-400">Remover</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-slate-400">Você ainda não compartilhou o seu Financeiro com ninguém.</p>
            @endforelse
        </section>
    </div>
</x-layout>
