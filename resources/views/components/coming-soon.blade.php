@props(['title'])

<div class="flex flex-col items-center gap-4 text-center">
    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-semibold tracking-wide text-slate-300 uppercase">
        Em breve
    </span>

    <h1 class="text-3xl font-semibold text-white sm:text-4xl">{{ $title }}</h1>

    <p class="max-w-md text-slate-400">
        Estamos preparando esta área. Em breve você poderá gerenciar {{ Str::lower($title) }} por aqui.
    </p>
</div>
