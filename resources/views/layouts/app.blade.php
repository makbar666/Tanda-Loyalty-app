<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title', 'Ringkasan') · Tanda</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f7f7f2]">
<div x-data="{menu:false}" class="min-h-screen lg:flex">
<aside :class="menu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-[#193b2b] text-white transition-transform lg:sticky lg:top-0 lg:h-screen">
  <div class="px-7 pb-9 pt-7"><div class="flex items-center gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#d7ecae] text-lg font-bold text-[#193b2b]">T</div><div><div class="text-xl font-bold tracking-tight">tanda<span class="text-[#b5d88d]">.</span></div><div class="text-[10px] uppercase tracking-[.2em] text-[#a9bfae]">Loyalty workspace</div></div></div></div>
  <div class="px-4 text-[10px] font-bold uppercase tracking-[.18em] text-[#93ad9b]">Menu utama</div>
  <nav class="mt-3 space-y-1 px-3 text-sm">
    @foreach([['dashboard','Ringkasan','◫'],['checkout','Kasir','▣'],['members','Member','♧'],['transactions','Transaksi','≡']] as [$route,$label,$icon])
      <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 {{ request()->routeIs($route.'*') ? 'bg-[#335b41] text-white' : 'text-[#bdd1c2] hover:bg-[#284c37]' }}"><span class="w-5 text-center text-lg leading-none">{{ $icon }}</span>{{ $label }}</a>
    @endforeach
  </nav>
  @if(auth()->user()->role === 'owner')<div class="mt-10 px-4 text-[10px] font-bold uppercase tracking-[.18em] text-[#93ad9b]">Pengelolaan</div><nav class="mt-3 space-y-1 px-3 text-sm">
    @foreach([['rules','Program poin','✧'],['team','Tim & outlet','♙'],['pos-connections','Koneksi POS','⇄'],['audit','Log aktivitas','⌁']] as [$route,$label,$icon])<a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 {{ request()->routeIs($route.'*') ? 'bg-[#335b41] text-white' : 'text-[#bdd1c2] hover:bg-[#284c37]' }}"><span class="w-5 text-center text-lg leading-none">{{ $icon }}</span>{{ $label }}</a>@endforeach
  </nav>@endif
  <div class="mt-auto border-t border-white/10 p-5"><div class="mb-4 text-sm font-semibold">{{ auth()->user()->name }}</div><div class="mb-4 text-xs capitalize text-[#a9bfae]">{{ auth()->user()->role }}</div><form method="post" action="{{ route('logout') }}">@csrf<button class="text-xs text-[#d3e1d4] hover:text-white">Keluar akun →</button></form></div>
</aside>
<div class="min-w-0 flex-1"><header class="flex h-18 items-center justify-between border-b border-[#e2e6df] bg-white px-5 lg:px-10"><div class="flex items-center gap-3"><button class="text-2xl lg:hidden" @click="menu=!menu">☰</button><span class="text-sm text-[#76847a]">Workspace <span class="mx-2">/</span> <strong class="font-semibold text-[#233d2e]">@yield('title', 'Ringkasan')</strong></span></div><div class="hidden items-center gap-3 text-xs text-[#69786e] sm:flex"><span class="h-2 w-2 rounded-full bg-[#63a875]"></span> Sistem aktif <span class="ml-4 rounded-full bg-[#eef4e9] px-3 py-2 font-semibold text-[#315b3f]">{{ auth()->user()->name }}</span></div></header>
<main class="mx-auto max-w-7xl px-5 py-8 lg:px-10 lg:py-10">@if(session('success'))<div class="mb-6 rounded-xl border border-[#c7e2c8] bg-[#edf8ed] px-5 py-3 text-sm text-[#28613a]">{{ session('success') }}</div>@endif @if($errors->any())<div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-3 text-sm text-red-800"><strong>Periksa kembali:</strong><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @yield('content')</main>
</div></div></body></html>
