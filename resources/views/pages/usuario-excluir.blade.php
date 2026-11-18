<x-layout title="Excluir usuário" align="start">
    <section class="w-full max-w-lg rounded-2xl border border-rose-400/25 bg-slate-900/70 p-5 sm:p-6">
        <h1 class="text-xl font-semibold text-white">Excluir usuário</h1>
        <div class="mt-4 rounded-lg border border-white/10 bg-slate-950/50 p-3">
            <p class="wrap-anywhere text-sm font-semibold text-white">{{ $managedUser->name }}</p>
            <p class="wrap-anywhere text-sm text-slate-400">{{ $managedUser->email }}</p>
        </div>
        <p class="mt-4 text-sm text-slate-300">A conta e os dados associados serão excluídos permanentemente.</p>
        <form method="POST" action="{{ route('admin.usuarios.destroy', $managedUser, absolute: false) }}" class="mt-6 flex flex-wrap items-center justify-end gap-3">
            @csrf
            @method('DELETE')
            <a href="{{ route('admin.usuarios.index', absolute: false) }}" class="px-2 text-sm font-medium text-slate-300 hover:text-white">Cancelar</a>
            <button class="min-h-11 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">Excluir usuário</button>
        </form>
    </section>
</x-layout>
