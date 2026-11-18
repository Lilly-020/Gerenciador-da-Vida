@php
    $editing = $managedUser !== null;
    $title = $editing ? 'Editar usuário' : 'Criar usuário';
    $inputClass = 'w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20';
@endphp

<x-layout :title="$title" align="start">
    <div class="w-full max-w-2xl">
        <a href="{{ route('admin.usuarios.index', absolute: false) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-indigo-300 hover:text-indigo-100"><x-card-icon name="back" /> Usuários</a>
        <section class="rounded-2xl border border-white/10 bg-slate-900/60 p-5 sm:p-6">
            <h1 class="mb-5 text-xl font-semibold text-white">{{ $title }}</h1>
            <form method="POST" action="{{ $editing ? route('admin.usuarios.update', $managedUser, absolute: false) : route('admin.usuarios.store', absolute: false) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif
                <label class="grid min-w-0 gap-1.5 text-sm text-slate-300">Nome
                    <input name="name" value="{{ old('name', $managedUser?->name) }}" required maxlength="255" autocomplete="name" class="{{ $inputClass }}">
                    @error('name')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="grid min-w-0 gap-1.5 text-sm text-slate-300">E-mail
                    <input type="email" name="email" value="{{ old('email', $managedUser?->email) }}" required maxlength="255" autocomplete="email" class="{{ $inputClass }}" @error('email') aria-invalid="true" aria-describedby="usuario-email-error" @enderror>
                    @error('email')<span id="usuario-email-error" role="alert" class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="grid min-w-0 gap-1.5 text-sm text-slate-300">{{ $editing ? 'Nova senha (opcional)' : 'Senha' }}
                    <input type="password" name="password" @unless ($editing) required @endunless minlength="8" autocomplete="new-password" class="{{ $inputClass }}">
                    @error('password')<span class="text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="grid min-w-0 gap-1.5 text-sm text-slate-300">Confirmar senha
                    <input type="password" name="password_confirmation" @unless ($editing) required @endunless minlength="8" autocomplete="new-password" class="{{ $inputClass }}">
                </label>
                <div class="mt-2 flex flex-wrap items-center justify-end gap-3 sm:col-span-2">
                    <a href="{{ route('admin.usuarios.index', absolute: false) }}" class="px-2 text-sm font-medium text-slate-300 hover:text-white">Cancelar</a>
                    <button class="min-h-11 rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">{{ $editing ? 'Salvar alterações' : 'Criar usuário' }}</button>
                </div>
            </form>
        </section>
    </div>
</x-layout>
