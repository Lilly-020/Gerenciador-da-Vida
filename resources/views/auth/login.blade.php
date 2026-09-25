<x-shell title="Entrar">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold tracking-wide text-slate-300 uppercase">
                Gerenciador da Vida
            </span>
            <h1 class="mt-4 text-2xl font-semibold text-white">Bem-vindo de volta</h1>
            <p class="mt-1 text-sm text-slate-400">Entre com sua conta para continuar</p>
        </div>

        <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-8 shadow-xl shadow-black/30 backdrop-blur">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">E-mail</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        placeholder="voce@exemplo.com"
                    >
                    @error('email')
                        <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">Senha</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        placeholder="••••••••"
                    >
                    @error('password')
                        <p class="mt-1.5 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input
                        type="checkbox"
                        name="remember"
                        class="rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                    >
                    Lembrar de mim
                </label>

                <button
                    type="submit"
                    class="w-full rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
                >
                    Entrar
                </button>
            </form>
        </div>
    </div>
</x-shell>
