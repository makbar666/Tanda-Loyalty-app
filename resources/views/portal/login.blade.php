@extends('portal.layout')
@section('title', 'Masuk member')
@section('content')
<div class="mx-auto max-w-md">
    <p class="text-xs font-bold uppercase tracking-[.15em] text-[#5a8a65]">Portal member</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Lihat poin Anda</h1>
    <p class="mt-2 text-sm leading-6 text-[#718075]">Masukkan nomor HP yang terdaftar di {{ $shop->name }}. Kami akan mengirim kode masuk sekali pakai.</p>
    <form method="post" action="{{ route('portal.code', $shop->id) }}" class="card mt-6 space-y-5 p-6">@csrf
        <div><label class="label" for="phone">Nomor HP / WhatsApp</label><input id="phone" class="field" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="08..." value="{{ old('phone') }}" required autofocus>@error('phone')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        <button class="btn btn-primary w-full" type="submit">Kirim kode masuk</button>
    </form>
    <p class="mt-5 text-center text-xs leading-5 text-[#718075]">Belum terdaftar? Minta kasir menambahkan nomor Anda sebagai member.</p>
</div>
@endsection
