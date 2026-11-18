<x-layout title="Usuários" align="start">
    <div class="app-expand-with-sidebar w-full min-w-0 max-w-6xl space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-white sm:text-3xl">Usuários</h1>
            <a href="{{ route('admin.usuarios.create', absolute: false) }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">
                <span aria-hidden="true">+</span> Criar usuário
            </a>
        </header>

        <section aria-labelledby="usuarios-lista" class="rounded-2xl border border-white/10 bg-slate-900/60">
            <div class="flex flex-wrap items-center justify-between gap-4 p-4 sm:p-5">
                <div>
                    <h2 id="usuarios-lista" class="text-base font-semibold text-white">Contas cadastradas</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ trans_choice(':count conta encontrada|:count contas encontradas', $users->total()) }}</p>
                </div>
                <form method="GET" action="{{ route('admin.usuarios.index', absolute: false) }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                    <label for="buscar-usuarios" class="sr-only">Nome ou e-mail</label>
                    <input id="buscar-usuarios" name="q" type="search" value="{{ $search }}" maxlength="255" placeholder="Nome ou e-mail" class="min-w-0 flex-1 rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white sm:w-56">
                    <button class="min-h-10 rounded-lg border border-white/10 px-3 text-sm font-medium text-slate-200 hover:bg-white/5">Buscar</button>
                    @if ($search !== '')
                        <a href="{{ route('admin.usuarios.index', absolute: false) }}" class="text-sm text-indigo-300 hover:underline">Limpar</a>
                    @endif
                </form>
            </div>

            <div class="divide-y divide-white/10 border-t border-white/10">
                <div aria-hidden="true" class="hidden grid-cols-[minmax(0,2fr)_1fr_1fr_9.5rem] gap-4 px-5 py-3 text-xs font-semibold text-slate-400 md:grid">
                    <span>Usuário</span><span>Permissão</span><span>Cadastro</span><span>Ações</span>
                </div>
                @forelse ($users as $user)
                    <article class="grid gap-3 p-4 md:grid-cols-[minmax(0,2fr)_1fr_1fr_9.5rem] md:items-center md:gap-4 md:px-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span aria-hidden="true" class="grid size-10 shrink-0 place-items-center rounded-full bg-indigo-500/20 text-sm font-semibold text-indigo-200">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <h3 class="wrap-anywhere text-sm font-semibold text-white">{{ $user->name }}</h3>
                                <p class="wrap-anywhere text-sm text-slate-400">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $user->is_admin ? 'bg-indigo-500/20 text-indigo-200' : 'bg-white/5 text-slate-300' }}">{{ $user->is_admin ? 'Administrador' : 'Usuário' }}</span></div>
                        <time datetime="{{ $user->created_at?->toDateString() }}" class="text-xs text-slate-400"><span class="md:hidden">Cadastro </span>{{ $user->created_at?->format('d/m/Y') }}</time>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.usuarios.edit', $user, absolute: false) }}" aria-label="Editar {{ $user->name }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-xs font-medium text-indigo-200 hover:bg-white/5"><x-card-icon name="pencil" /> Editar</a>
                            @if (! auth()->user()->is($user))
                                <a href="{{ route('admin.usuarios.confirm-deletion', $user, absolute: false) }}" aria-label="Excluir {{ $user->name }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-xs font-medium text-rose-300 hover:bg-rose-500/10"><x-card-icon name="trash" /> Excluir</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="p-8 text-center text-sm text-slate-400">Nenhuma conta encontrada.</p>
                @endforelse
            </div>

            @if ($users->hasPages())
                <div class="border-t border-white/10 p-4">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
</x-layout>
