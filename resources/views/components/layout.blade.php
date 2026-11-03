@props(['title' => null, 'align' => 'center'])

<x-shell :title="$title" :align="$align" sidebar>
    <x-slot:header>
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-white/10 bg-slate-950/95 p-5 lg:flex" aria-label="Menu lateral">
            <p class="mb-8 px-3 text-lg font-semibold text-white">Gerenciador da Vida</p>
            <x-main-navigation />
        </aside>
        <header class="flex items-center gap-3 border-b border-white/10 px-4 py-4 lg:hidden">
            <button type="button" id="open-navigation" aria-controls="mobile-navigation" aria-haspopup="dialog" aria-expanded="false" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-white/15 px-3 text-slate-200 focus-visible:ring-2 focus-visible:ring-indigo-400">
                <span aria-hidden="true">☰</span><span class="sr-only">Abrir menu</span>
            </button>
            <span class="text-sm font-semibold">Gerenciador da Vida</span>
        </header>
        <dialog id="mobile-navigation" aria-labelledby="mobile-navigation-title" class="fixed inset-y-0 right-auto left-0 m-0 h-dvh max-h-dvh w-72 max-w-[85vw] border-r border-white/10 bg-slate-950 p-5 text-white backdrop:bg-black/60 lg:hidden">
            <div class="mb-6 flex items-center justify-between gap-3">
                <h2 id="mobile-navigation-title" class="font-semibold">Gerenciador da Vida</h2>
                <button type="button" id="close-navigation" aria-label="Fechar menu" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-white/15">✕</button>
            </div>
            <x-main-navigation />
        </dialog>

        @if (session('status') || $errors->any())
            <div class="mx-auto mt-6 w-full max-w-2xl space-y-2 px-6" data-notice>
                @if (session('status'))
                    <div class="rounded-lg border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="rounded-lg border border-rose-400/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-200">
                        @foreach ($errors->all() as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </x-slot:header>

    {{ $slot }}

    <x-slot:modals>
        {{ $modals ?? '' }}
    </x-slot:modals>
</x-shell>
