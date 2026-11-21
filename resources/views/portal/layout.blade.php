<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $shop->name }} · Tanda</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f7f2] text-[#193b2b]">
    <header class="border-b border-[#e2e6df] bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
            <div class="flex items-center gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#193b2b] font-bold text-white">T</div><div><div class="text-sm font-bold leading-tight">tanda<span class="text-[#5e9b60]">.</span></div><div class="text-xs text-[#718075]">{{ $shop->name }}</div></div></div>
            @hasSection('portal-nav')@yield('portal-nav')@endif
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-5 py-8 sm:py-12">@yield('content')</main>
</body>
</html>
