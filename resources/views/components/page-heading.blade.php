@props(['eyebrow', 'title', 'description'])

<div {{ $attributes }}>
    <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-indigo-300">{{ $eyebrow }}</p>
    <h1 class="text-3xl font-semibold text-white">{{ $title }}</h1>
    <p class="mt-2 text-sm text-slate-400">{{ $description }}</p>
    {{ $slot }}
</div>
