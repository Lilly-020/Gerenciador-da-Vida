<x-layout title="Social" align="start">
    <div
        id="social-app"
        class="w-full min-w-0"
        data-base="{{ route('social.index', absolute: false) }}"
        data-profile-settings="{{ route('perfil', absolute: false) }}#perfil-social"
        data-viewer="{{ auth()->id() }}"
        data-person="{{ $person->id ?? '' }}"
        data-section="{{ request()->routeIs('social.requests') ? 'requests' : (request()->routeIs('social.friends') ? 'friends' : 'feed') }}"
    >
        <p role="status" class="p-6 text-sm text-slate-400">Carregando o Social…</p>
    </div>
</x-layout>
