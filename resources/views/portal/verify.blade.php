@extends('portal.layout')
@section('title', 'Verifikasi kode')
@section('content')
<div class="mx-auto max-w-md">
    <p class="text-xs font-bold uppercase tracking-[.15em] text-[#5a8a65]">Portal member</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">Masukkan kode</h1>
    <p class="mt-2 text-sm leading-6 text-[#718075]">Kode dikirim ke {{ $maskedPhone }} dan berlaku 5 menit.</p>
    @if(session('status'))<p class="mt-5 rounded-lg border border-[#c9dfc7] bg-[#f0f7ed] p-3 text-sm text-[#245c43]">{{ session('status') }}</p>@endif
    <form method="post" action="{{ route('portal.confirm', $shop->id) }}" class="card mt-6 space-y-5 p-6">@csrf
        <div><label class="label" for="code">Kode 6 angka</label><input id="code" class="field text-center text-xl tracking-[.3em]" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>@error('code')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        <button class="btn btn-primary w-full" type="submit">Masuk ke portal</button>
    </form>
    <div class="mt-5 flex items-center justify-between gap-3 text-sm"><a class="font-semibold text-[#245c43]" href="{{ route('portal.login', $shop->id) }}">Ganti nomor</a><form method="post" action="{{ route('portal.code', $shop->id) }}">@csrf<input type="hidden" name="phone" value="{{ session('member_portal.pending.phone') }}"><button class="font-semibold text-[#245c43]" type="submit">Kirim ulang kode</button></form></div>
</div>
@endsection
