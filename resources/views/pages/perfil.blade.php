@php
    $tabClasses = fn (bool $active): string => 'shrink-0 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition '
        .($active ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:text-white');
    $statusLabels = [
        'pendente' => ['Aguardando resposta', 'text-amber-300'],
        'aceito' => ['Aceito', 'text-emerald-300'],
        'recusado' => ['Recusado', 'text-rose-300'],
    ];
@endphp

<x-layout title="Perfil" align="start">
    <div class="w-full max-w-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-white sm:text-3xl">Perfil</h1>
            <p class="mt-1 text-sm text-slate-400">Seus dados e os convites de participação.</p>
        </div>

        <nav class="mb-6 flex max-w-full items-center gap-1 overflow-x-auto rounded-full border border-white/10 bg-slate-900/70 p-1.5 shadow-xl shadow-black/30 backdrop-blur scrollbar-none">
            <a href="{{ route('perfil', absolute: false) }}" class="{{ $tabClasses($aba === 'perfil') }}">Meus dados</a>
            <a href="{{ route('perfil', ['aba' => 'notificacoes'], absolute: false) }}" class="{{ $tabClasses($aba === 'notificacoes') }}">
                Notificações
                @if ($pendentes->isNotEmpty())
                    <span class="ml-1 rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $pendentes->count() }}</span>
                @endif
            </a>
        </nav>

        @if ($aba === 'perfil')
            <div class="space-y-4">
                <form method="POST" action="{{ route('perfil.update', absolute: false) }}" class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="perfil-name" class="mb-1.5 block text-sm font-medium text-slate-300">Nome</label>
                        <input id="perfil-name" type="text" name="name" required maxlength="255" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20">
                    </div>

                    <div>
                        <label for="perfil-apelido" class="mb-1.5 block text-sm font-medium text-slate-300">Apelido</label>
                        <input
                            id="perfil-apelido"
                            type="text"
                            name="apelido"
                            required
                            minlength="3"
                            maxlength="30"
                            pattern="[A-Za-z0-9_.]+"
                            autocapitalize="none"
                            value="{{ old('apelido', $user->apelido) }}"
                            placeholder="Ex: lilly"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                        <p class="mt-1.5 text-xs text-slate-500">
                            É por ele que outras pessoas encontram você para convidar. Único, sem diferenciar maiúsculas de minúsculas; só letras, números, ponto e sublinhado.
                        </p>
                    </div>

                    <div>
                        <label for="perfil-email" class="mb-1.5 block text-sm font-medium text-slate-300">E-mail</label>
                        <input id="perfil-email" type="email" name="email" required maxlength="255" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-full bg-indigo-500/90 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500">Salvar</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('perfil.senha', absolute: false) }}" class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <h2 class="text-base font-semibold text-white">Alterar senha</h2>

                    @if ($errors->senha->any())
                        <div class="rounded-lg border border-rose-400/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-200">
                            @foreach ($errors->senha->all() as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <label for="perfil-current-password" class="mb-1.5 block text-sm font-medium text-slate-300">Senha atual</label>
                        <input id="perfil-current-password" type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20">
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="perfil-password" class="mb-1.5 block text-sm font-medium text-slate-300">Nova senha</label>
                            <input id="perfil-password" type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20">
                        </div>
                        <div>
                            <label for="perfil-password-confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">Confirmar nova senha</label>
                            <input id="perfil-password-confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-full bg-indigo-500/90 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500">Alterar senha</button>
                    </div>
                </form>
            </div>
        @else
            <div class="space-y-4">
                <section class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6">
                    <h2 class="mb-3 text-base font-semibold text-white">Convites para você</h2>

                    @forelse ($pendentes as $convite)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 py-3 first:border-t-0 first:pt-0 last:pb-0">
                            <div class="min-w-0 text-sm text-slate-300">
                                <p class="wrap-break-word">
                                    <span class="font-semibold text-white">{{ $convite->owner->handle() }}</span>
                                    convidou você para participar d{{ $convite->subject() }}.
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ \App\Models\Participacao::PERMISSIONS[$convite->permission] }}
                                    @if ($convite->sectionLabels())
                                        · {{ implode(', ', $convite->sectionLabels()) }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <form method="POST" action="{{ route('participacoes.recusar', $convite, absolute: false) }}">
                                    @csrf
                                    <button type="submit" class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 transition hover:text-white">Recusar</button>
                                </form>
                                <form method="POST" action="{{ route('participacoes.aceitar', $convite, absolute: false) }}">
                                    @csrf
                                    <button type="submit" class="rounded-full bg-indigo-500/90 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500">Aceitar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Nenhum convite pendente.</p>
                    @endforelse
                </section>

                <section class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6">
                    <h2 class="mb-3 text-base font-semibold text-white">Compartilhado com você</h2>

                    @forelse ($aceitas as $participacao)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 py-3 first:border-t-0 first:pt-0 last:pb-0">
                            <div class="min-w-0 text-sm text-slate-300">
                                <p class="wrap-break-word">
                                    {{ ucfirst($participacao->subject()) }} de
                                    <span class="font-semibold text-white">{{ $participacao->owner->handle() }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ \App\Models\Participacao::PERMISSIONS[$participacao->permission] }}
                                    @if ($participacao->sectionLabels())
                                        · {{ implode(', ', $participacao->sectionLabels()) }}
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('participacoes.destroy', $participacao, absolute: false) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-slate-400 transition hover:text-rose-400">Sair</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Ninguém compartilhou nada com você ainda.</p>
                    @endforelse
                </section>

                <section class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6">
                    <h2 class="mb-3 text-base font-semibold text-white">Convites que você enviou</h2>

                    @forelse ($enviadas as $participacao)
                        @php [$statusLabel, $statusColor] = $statusLabels[$participacao->status]; @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 py-3 first:border-t-0 first:pt-0 last:pb-0">
                            <div class="min-w-0 text-sm text-slate-300">
                                <p class="wrap-break-word">
                                    <span class="font-semibold text-white">{{ $participacao->participant->handle() }}</span>
                                    em {{ $participacao->subject() }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    <span class="{{ $statusColor }}">{{ $statusLabel }}</span>
                                    · {{ \App\Models\Participacao::PERMISSIONS[$participacao->permission] }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('participacoes.destroy', $participacao, absolute: false) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-slate-400 transition hover:text-rose-400">Remover</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Você ainda não convidou ninguém.</p>
                    @endforelse
                </section>
            </div>
        @endif
    </div>
</x-layout>
