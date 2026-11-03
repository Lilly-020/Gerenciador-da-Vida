<x-layout title="Usuários" align="start">
    <div class="w-full max-w-4xl space-y-6">
        <header>
            <h1 class="text-2xl font-semibold text-white">Usuários</h1>
            <p class="mt-1 text-sm text-slate-400">Crie contas para acessar o Gerenciador da Vida. Apenas administradores podem cadastrar usuários.</p>
        </header>
        <section class="rounded-xl border border-white/10 bg-slate-900/70 p-5 sm:p-6">
            <h2 class="mb-4 text-lg font-semibold">Criar usuário</h2>
            <form method="POST" action="{{ route('admin.usuarios.store', absolute: false) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <label class="flex flex-col gap-2 text-sm text-slate-300">Nome
                    <input name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" class="rounded-lg border border-white/10 bg-slate-950 px-3 py-3 text-white focus:border-indigo-400 focus:outline-none">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">E-mail
                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" class="rounded-lg border border-white/10 bg-slate-950 px-3 py-3 text-white focus:border-indigo-400 focus:outline-none">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">Senha (mínimo de 8 caracteres)
                    <input type="password" name="password" required minlength="8" autocomplete="new-password" class="rounded-lg border border-white/10 bg-slate-950 px-3 py-3 text-white focus:border-indigo-400 focus:outline-none">
                </label>
                <label class="flex flex-col gap-2 text-sm text-slate-300">Confirmar senha
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="rounded-lg border border-white/10 bg-slate-950 px-3 py-3 text-white focus:border-indigo-400 focus:outline-none">
                </label>
                <p class="text-sm text-slate-400 sm:col-span-2">A nova conta será de usuário comum. A pessoa poderá alterar a senha no próprio perfil.</p>
                <button class="min-h-11 rounded-full bg-indigo-500 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-400 sm:justify-self-start">Criar usuário</button>
            </form>
        </section>
        <section class="space-y-4">
            <h2 class="text-lg font-semibold">Usuários cadastrados</h2>
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900"><tr><th class="p-4">Nome</th><th class="p-4">E-mail</th><th class="p-4">Permissão</th><th class="p-4">Cadastro</th></tr></thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-t border-white/10"><td class="p-4">{{ $user->name }}</td><td class="p-4">{{ $user->email }}</td><td class="p-4">{{ $user->is_admin ? 'Administrador' : 'Usuário' }}</td><td class="p-4">{{ $user->created_at?->format('d/m/Y') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        </section>
    </div>
</x-layout>
