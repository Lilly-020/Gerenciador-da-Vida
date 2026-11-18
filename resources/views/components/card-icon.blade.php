@props(['name'])
<svg {{ $attributes->merge(['class' => 'h-4 w-4 shrink-0']) }} aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('filter')<path d="M3 4h18l-7 8v7l-4 2v-9z" />@break
        @case('back')<path d="m15 5-7 7 7 7" />@break
        @case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 10h18c0-2-3-3-3-10M10 22h4" />@break
        @case('upload')<path d="M4 15v6h16v-6M12 16V3m-5 5 5-5 5 5" />@break
        @case('comment')<path d="M21 4H3v13h5l4 4v-4h9z" />@break
        @case('bold')<path stroke-width="2" d="M7 3h6a4 4 0 0 1 0 8H7m0-8v18h7a5 5 0 0 0 0-10H7" />@break
        @case('italic')<path d="M10 3h10M4 21h10M15 3 9 21" />@break
        @case('underline')<path d="M6 3v8a6 6 0 0 0 12 0V3M4 21h16" />@break
        @case('strike')<path d="M17 5c-5-5-13 0-9 5m-1 6c2 5 12 4 10-2M3 12h18" />@break
        @case('list')<path d="M8 5h13M8 12h13M8 19h13M3 5h.01M3 12h.01M3 19h.01" />@break
        @case('list-ordered')<path d="M9 5h12M9 12h12M9 19h12M2 3h2v5M2 8h4M2 13c3-3 5 0 1 3l-1 2h4" />@break
        @case('link')<path d="m10 13 4-4m-6 7-2 2a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0m2 1 2-2a4 4 0 0 1 6 6l-5 5a4 4 0 0 1-6 0" />@break
        @case('code')<path d="m8 6-6 6 6 6m8-12 6 6-6 6M14 3l-4 18" />@break
        @case('pin')<path d="m8 3 8 0-1 6 4 4v2H5v-2l4-4zM12 15v7" />@break
        @case('reply')<path d="m8 5-6 6 6 6M2 11h12a8 8 0 0 1 8 8" />@break
        @case('heart')<path d="M20 4a6 6 0 0 0-8 2 6 6 0 0 0-8-2c-7 5 1 12 8 17 7-5 15-12 8-17z" />@break
        @case('copy')<rect x="8" y="8" width="13" height="13" rx="1" /><path d="M16 8V3H3v13h5" />@break
        @case('move')<path d="M3 12h18m-7-7 7 7-7 7" />@break
        @case('status')<circle cx="12" cy="12" r="8" /><path d="m8 12 3 3 5-6" />@break
        @case('people')<circle cx="9" cy="8" r="4" /><path d="M2 21v-2a7 7 0 0 1 14 0v2m3-14v6m-3-3h6" />@break
        @case('tag')<path d="m3 3 8 .1L21 13l-8 8L3 11z" /><circle cx="7" cy="7" r="1" />@break
        @case('date')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
        @case('checklist')<rect x="3" y="3" width="18" height="18" rx="2" /><path d="m7 12 3 3 7-7" />@break
        @case('image')<rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8" cy="8" r="1.5" /><path d="m3 17 6-6 4 4 3-3 5 5" />@break
        @case('location')<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="3" />@break
        @case('attachment')<path d="m9 12 6-6a3 3 0 0 1 4 4l-9 9a5 5 0 0 1-7-7L13 2m-7 13 9-9" />@break
        @case('pencil')<path d="m16 3 5 5M3 21l5-1L21 7a2 2 0 0 0-4-4L4 16z" />@break
        @case('trash')<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7" />@break
        @case('more')<circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" />@break
        @case('download')<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5" />@break
        @case('play')<rect x="3" y="3" width="18" height="18" rx="3" /><path d="m10 8 6 4-6 4z" />@break
        @case('menu')<path d="M4 6h16M4 12h16M4 18h16" />@break
        @case('moon')<path d="M20 15A9 9 0 0 1 9 4a9 9 0 1 0 11 11Z" />@break
        @case('sun')<circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1" />@break
        @case('grip')<circle cx="8" cy="5" r="1" /><circle cx="16" cy="5" r="1" /><circle cx="8" cy="12" r="1" /><circle cx="16" cy="12" r="1" /><circle cx="8" cy="19" r="1" /><circle cx="16" cy="19" r="1" />@break
        @case('close')<path d="m6 6 12 12M6 18 18 6" />@break
        @case('open')<path d="M14 3h7v7m0-7L10 14m-1-9H3v16h16v-6" />@break
        @case('send')<path d="m3 3 19 9-19 9 4-9zm4 9h15" />@break
        @case('file')<path d="M14 2H4v20h16V8zM14 2v6h6M8 13h8m-8 4h6" />@break
        @case('home')<path d="m3 10 9-7 9 7v10H3z" />@break
        @case('dashboard')<rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />@break
        @case('folder')<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />@break
        @case('calendar')<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M7 3v4m10-4v4M3 10h18" />@break
        @case('wallet')<path d="M21 9V6a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h16v12H5a2 2 0 0 1-2-2V6m18 6h-5a2 2 0 0 0 0 4h5" />@break
        @case('profile')<circle cx="12" cy="8" r="4" /><path d="M4 21v-1a8 6 0 0 1 16 0v1" />@break
        @case('logout')<path d="M10 4H4v16h6m4-12 4 4-4 4m4-4H8" />@break
        @case('chevrons-left')<path d="m11 6-6 6 6 6m7-12-6 6 6 6" />@break
    @endswitch
</svg>
