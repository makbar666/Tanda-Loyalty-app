<?php

namespace App\Http\Controllers;

use App\Services\MemberOtpSender;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberPortalController extends Controller
{
    private function business(int $business): object
    {
        return DB::table('businesses')->find($business) ?? abort(404);
    }

    private function phone(string $raw): string
    {
        $phone = preg_replace('/\D+/', '', $raw);
        if (Str::startsWith($phone, '0')) $phone = '62'.substr($phone, 1);
        if (!preg_match('/^62\d{8,13}$/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'Masukkan nomor HP Indonesia yang valid.']);
        }
        return $phone;
    }

    private function member(Request $request, int $business): ?object
    {
        if ((int) $request->session()->get('member_portal.business') !== $business) return null;
        return DB::table('members')->where('business_id', $business)
            ->where('id', $request->session()->get('member_portal.member'))->first();
    }

    public function login(Request $request, int $business)
    {
        $shop = $this->business($business);
        if ($this->member($request, $business)) return redirect()->route('portal.home', $business);
        return view('portal.login', compact('shop'));
    }

    public function sendCode(Request $request, int $business, MemberOtpSender $sender)
    {
        $this->business($business);
        $request->validate(['phone' => 'required|string|max:25']);
        $phone = $this->phone($request->input('phone'));
        $key = 'portal-send:'.hash('sha256', $business.'|'.$phone);
        $ipKey = 'portal-send-ip:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            throw ValidationException::withMessages(['phone' => 'Terlalu banyak permintaan. Coba lagi beberapa menit.']);
        }
        RateLimiter::hit($key, 600);
        RateLimiter::hit($ipKey, 600);
        $request->session()->put('member_portal.pending', ['business' => $business, 'phone' => $phone]);

        $member = DB::table('members')->where('business_id', $business)->where('phone', $phone)->first();
        if ($member) {
            $existing = DB::table('member_login_codes')->where('member_id', $member->id)->first();
            if (!$existing || \Carbon\Carbon::parse($existing->created_at)->lessThanOrEqualTo(now()->subMinute())) {
                $code = (string) random_int(100000, 999999);
                DB::table('member_login_codes')->updateOrInsert(
                    ['member_id' => $member->id],
                    ['business_id' => $business, 'code_hash' => Hash::make($code), 'attempts' => 0,
                        'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]
                );
                try {
                    $sender->send($phone, $code);
                } catch (\Throwable $e) {
                    DB::table('member_login_codes')->where('member_id', $member->id)->delete();
                    report($e);
                }
            }
        }

        return redirect()->route('portal.verify', $business)->with('status', 'Jika nomor terdaftar dan layanan tersedia, kode masuk akan dikirim. Kode berlaku 5 menit.');
    }

    public function verify(Request $request, int $business)
    {
        $shop = $this->business($business);
        $pending = $request->session()->get('member_portal.pending');
        if (!$pending || $pending['business'] !== $business) return redirect()->route('portal.login', $business);
        $maskedPhone = substr($pending['phone'], 0, 4).'••••'.substr($pending['phone'], -3);
        return view('portal.verify', compact('shop', 'maskedPhone'));
    }

    public function confirm(Request $request, int $business)
    {
        $this->business($business);
        $request->validate(['code' => 'required|digits:6']);
        $pending = $request->session()->get('member_portal.pending');
        if (!$pending || $pending['business'] !== $business) return redirect()->route('portal.login', $business);
        $key = 'portal-verify:'.hash('sha256', $business.'|'.$pending['phone']);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan. Coba lagi beberapa menit.']);
        }
        RateLimiter::hit($key, 600);

        $memberId = DB::transaction(function () use ($business, $pending, $request) {
            $member = DB::table('members')->where('business_id', $business)->where('phone', $pending['phone'])->first();
            $challenge = $member ? DB::table('member_login_codes')->where('member_id', $member->id)->lockForUpdate()->first() : null;
            if (!$challenge) return null;
            if (now()->greaterThanOrEqualTo($challenge->expires_at) || $challenge->attempts >= 5 || !Hash::check($request->input('code'), $challenge->code_hash)) {
                DB::table('member_login_codes')->where('id', $challenge->id)->increment('attempts');
                return null;
            }
            DB::table('member_login_codes')->where('id', $challenge->id)->delete();
            return $member->id;
        });
        if (!$memberId) {
            throw ValidationException::withMessages(['code' => 'Kode salah atau sudah kedaluwarsa. Minta kode baru jika perlu.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('member_portal.pending');
        $request->session()->put('member_portal.business', $business);
        $request->session()->put('member_portal.member', $memberId);
        RateLimiter::clear($key);
        return redirect()->route('portal.home', $business);
    }

    public function home(Request $request, int $business, PointService $points)
    {
        $shop = $this->business($business);
        $member = $this->member($request, $business);
        if (!$member) return redirect()->route('portal.login', $business);
        DB::transaction(function () use ($business, $member, $points) {
            $locked = DB::table('members')->where('business_id', $business)->where('id', $member->id)->lockForUpdate()->first();
            $points->expireMember($locked);
        });
        $member = $this->member($request, $business);
        $nextExpiry = DB::table('point_lots')->where('business_id', $business)->where('member_id', $member->id)
            ->where('remaining_points', '>', 0)->where('expires_at', '>', now())->orderBy('expires_at')->first();
        $ledger = DB::table('point_ledger_entries as l')->leftJoin('transactions as t', 't.id', '=', 'l.transaction_id')
            ->where('l.business_id', $business)->where('l.member_id', $member->id)
            ->select('l.*', 't.reference')->orderByDesc('l.id')->paginate(10);
        return view('portal.home', compact('shop', 'member', 'nextExpiry', 'ledger'));
    }

    public function logout(Request $request, int $business)
    {
        $request->session()->forget('member_portal');
        $request->session()->regenerateToken();
        return redirect()->route('portal.login', $business);
    }
}
