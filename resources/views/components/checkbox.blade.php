@props(['checked' => false])
<span class="relative inline-flex h-5 w-5 shrink-0 items-center justify-center">
    <input type="checkbox" @checked($checked) {{ $attributes->merge(['class' => 'peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-500 bg-slate-950/60 checked:border-indigo-400 checked:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 disabled:opacity-50']) }}>
    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="pointer-events-none absolute h-3.5 w-3.5 text-white opacity-0 peer-checked:opacity-100"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
</span>
