@props(['title' => null, 'align' => 'center', 'fullBleed' => false])

<x-shell :title="$title" :align="$align" :full-bleed="$fullBleed" sidebar>
    <x-slot:header>
        <aside id="app-sidebar" class="app-sidebar fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-white/10 bg-slate-950/95 p-5 lg:flex" aria-label="Menu lateral">
            <p class="mb-6 shrink-0 px-3 text-lg font-semibold text-white">Gerenciador da Vida</p>
            <x-main-navigation />
        </aside>
        <x-app-toolbar />
        <dialog id="mobile-navigation" aria-labelledby="mobile-navigation-title" class="fixed inset-y-0 right-auto left-0 m-0 h-dvh max-h-dvh w-72 max-w-[85vw] border-r border-white/10 bg-slate-950 p-5 text-white backdrop:bg-black/60 lg:hidden">
            <div class="mb-6 flex items-center justify-between gap-3">
                <h2 id="mobile-navigation-title" class="font-semibold">Gerenciador da Vida</h2>
                <button type="button" id="close-navigation" aria-label="Fechar menu" class="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-white/15"><x-card-icon name="close" /></button>
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
        <x-nota-modals />
    </x-slot:modals>
</x-shell>
