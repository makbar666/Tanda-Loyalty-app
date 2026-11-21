<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoyaltyController extends Controller
{
    public function __construct(private PointService $points) {}
    private function owner(Request $r): void { abort_unless($r->user()->role === 'owner', 403); }
    private function scoped($q, Request $r, string $column = 'outlet_id') { return $r->user()->role === 'owner' ? $q : $q->where($column, $r->user()->outlet_id); }

    public function login() { return Auth::check() ? redirect()->route('dashboard') : view('login'); }
    public function authenticate(Request $r) { $data = $r->validate(['email' => 'required|email', 'password' => 'required']); if (!Auth::attempt($data)) throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak cocok.']); $r->session()->regenerate(); $this->points->audit($r->user(), 'login', 'user', $r->user()->id, null, null); return redirect()->intended(route('dashboard')); }
    public function logout(Request $r) { Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect()->route('login'); }

    public function dashboard(Request $r)
    {
        $b = $r->user()->business_id;
        $base = $this->scoped(DB::table('transactions')->where('business_id', $b)->where('status', 'paid'), $r);
        $revenue = (clone $base)->sum('total'); $transactions = (clone $base)->count(); $memberTx = (clone $base)->whereNotNull('member_id')->count();
        $members = DB::table('members')->where('business_id', $b)->count(); $balance = DB::table('members')->where('business_id', $b)->sum('point_balance');
        $earned = (clone $base)->sum('earned_points'); $redeemed = (clone $base)->sum('redeem_points'); $rule = $this->points->rule($b);
        $expired = -(int) DB::table('point_ledger_entries')->where('business_id', $b)->where('type', 'expire')->sum('points_delta');
        $refunded = $this->scoped(DB::table('transactions')->where('business_id', $b)->where('status', 'refunded'), $r)->count();
        $recent = $this->scoped(DB::table('transactions as t')->join('outlets as o', 'o.id', '=', 't.outlet_id')->leftJoin('members as m', 'm.id', '=', 't.member_id')->where('t.business_id', $b)->select('t.*', 'o.name as outlet_name', 'm.name as member_name')->orderByDesc('t.id'), $r, 't.outlet_id')->limit(6)->get();
        return view('dashboard', compact('revenue', 'transactions', 'memberTx', 'members', 'balance', 'earned', 'redeemed', 'expired', 'refunded', 'rule', 'recent'));
    }
    public function members(Request $r) { $q = trim((string) $r->query('q')); $members = DB::table('members')->where('business_id', $r->user()->business_id)->when($q, fn ($x) => $x->where(fn ($y) => $y->where('name', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")->orWhere('code', 'like', "%$q%")))->orderByDesc('id')->paginate(12)->withQueryString(); return view('members.index', compact('members', 'q')); }
    public function storeMember(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100', 'phone' => 'required|string|max:25', 'promo_consent' => 'nullable|boolean']);
        $phone = preg_replace('/\D+/', '', $data['phone']); if (Str::startsWith($phone, '0')) $phone = '62'.substr($phone, 1);
        if (!Str::startsWith($phone, '62') || strlen($phone) < 10 || strlen($phone) > 15) throw ValidationException::withMessages(['phone' => 'Gunakan nomor Indonesia yang valid.']);
        $existing = DB::table('members')->where('business_id', $r->user()->business_id)->where('phone', $phone)->first();
        if ($existing) return redirect()->route('members.show', $existing->id)->with('success', 'Member sudah terdaftar.');
        $id = DB::table('members')->insertGetId(['business_id' => $r->user()->business_id, 'code' => 'MBR-'.strtoupper(Str::random(7)), 'name' => $data['name'], 'phone' => $phone, 'promo_consent' => (bool) ($data['promo_consent'] ?? false), 'consented_at' => !empty($data['promo_consent']) ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
        $this->points->audit($r->user(), 'member_created', 'member', $id, null, ['name' => $data['name']]); return redirect()->route('members.show', $id)->with('success', 'Member berhasil ditambahkan.');
    }
    public function member(Request $r, int $id) { $member = DB::table('members')->where('business_id', $r->user()->business_id)->where('id', $id)->first(); abort_unless($member, 404); $ledger = DB::table('point_ledger_entries')->where('member_id', $id)->orderByDesc('id')->paginate(15); $nextExpiry = DB::table('point_lots')->where('member_id', $id)->where('remaining_points', '>', 0)->where('expires_at', '>', now())->orderBy('expires_at')->first(); return view('members.show', compact('member', 'ledger', 'nextExpiry')); }
    public function adjustMember(Request $r, int $id) { $this->owner($r); $data = $r->validate(['points' => 'required|integer|not_in:0|between:-1000000,1000000', 'reason' => 'required|string|min:5|max:500']); $this->points->adjust($r->user(), $id, $data['points'], $data['reason']); return back()->with('success', 'Koreksi poin berhasil dicatat.'); }
    public function checkout(Request $r) { $members = DB::table('members')->where('business_id', $r->user()->business_id)->orderBy('name')->get(); $outlets = $this->scoped(DB::table('outlets')->where('business_id', $r->user()->business_id), $r, 'id')->get(); $rule = $this->points->rule($r->user()->business_id); return view('checkout', compact('members', 'outlets', 'rule')); }
    public function storeCheckout(Request $r)
    {
        $data = $r->validate(['idempotency_key' => 'required|uuid', 'outlet_id' => 'required|integer', 'member_id' => 'nullable|integer', 'discount' => 'required|integer|min:0', 'redeem_points' => 'required|integer|min:0', 'payment_method' => ['required', Rule::in(['cash','transfer','qris','card'])], 'items' => 'required|array|min:1', 'items.*.name' => 'required|string|max:120', 'items.*.quantity' => 'required|integer|min:1|max:1000', 'items.*.unit_price' => 'required|integer|min:0|max:1000000000', 'items.*.eligible' => 'nullable|boolean']);
        $tx = $this->points->checkout($r->user(), $data); return redirect()->route('transactions.show', $tx->id)->with('success', 'Pembayaran dan poin berhasil dicatat.');
    }
    public function transactions(Request $r) { $transactions = $this->scoped(DB::table('transactions as t')->leftJoin('members as m', 'm.id', '=', 't.member_id')->join('outlets as o', 'o.id', '=', 't.outlet_id')->where('t.business_id', $r->user()->business_id)->select('t.*', 'm.name as member_name', 'o.name as outlet_name')->orderByDesc('t.id'), $r, 't.outlet_id')->paginate(15); return view('transactions.index', compact('transactions')); }
    public function transaction(Request $r, int $id) { $tx = $this->scoped(DB::table('transactions as t')->leftJoin('members as m', 'm.id', '=', 't.member_id')->join('outlets as o', 'o.id', '=', 't.outlet_id')->where('t.business_id', $r->user()->business_id)->where('t.id', $id)->select('t.*', 'm.name as member_name', 'm.code as member_code', 'o.name as outlet_name'), $r, 't.outlet_id')->first(); abort_unless($tx, 404); $items = DB::table('transaction_items')->where('transaction_id', $id)->get(); return view('transactions.show', compact('tx', 'items')); }
    public function refund(Request $r, int $id) { $data = $r->validate(['reason' => 'required|string|min:5|max:500']); $this->points->refund($r->user(), $id, $data['reason']); return back()->with('success', 'Refund penuh telah dicatat.'); }
    public function rules(Request $r) { $this->owner($r); $rules = DB::table('point_rules')->where('business_id', $r->user()->business_id)->orderByDesc('version')->get(); return view('rules', compact('rules')); }
    public function storeRule(Request $r)
    {
        $this->owner($r); $data = $r->validate(['earn_step' => 'required|integer|min:1', 'earn_points' => 'required|integer|min:1', 'minimum_spend' => 'required|integer|min:0', 'redeem_value' => 'required|integer|min:1', 'minimum_redeem' => 'required|integer|min:1', 'maximum_redeem_percent' => 'required|integer|between:1,100', 'approval_threshold' => 'required|integer|min:1', 'valid_days' => 'required|integer|min:1', 'effective_at' => 'required|date']);
        $id = DB::transaction(function () use ($r, $data) { DB::table('businesses')->where('id', $r->user()->business_id)->lockForUpdate()->first(); $version = (int) DB::table('point_rules')->where('business_id', $r->user()->business_id)->max('version') + 1; return DB::table('point_rules')->insertGetId($data + ['business_id' => $r->user()->business_id, 'version' => $version, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]); });
        $this->points->audit($r->user(), 'rule_created', 'point_rule', $id, null, $data); return back()->with('success', 'Versi aturan baru berhasil disimpan.');
    }
    public function team(Request $r) { $this->owner($r); $users = User::where('business_id', $r->user()->business_id)->get(); $outlets = DB::table('outlets')->where('business_id', $r->user()->business_id)->get(); return view('team', compact('users', 'outlets')); }
    public function storeUser(Request $r) { $this->owner($r); $data = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8', 'role' => ['required', Rule::in(['cashier','manager'])], 'outlet_id' => ['required', Rule::exists('outlets', 'id')->where('business_id', $r->user()->business_id)]]); $user = User::create($data + ['business_id' => $r->user()->business_id]); $this->points->audit($r->user(), 'staff_created', 'user', $user->id, null, ['name' => $user->name, 'role' => $user->role]); return back()->with('success', 'Staf berhasil ditambahkan.'); }
    public function storeOutlet(Request $r) { $this->owner($r); $data = $r->validate(['name' => 'required|string|max:100', 'code' => ['required','alpha_dash','max:20',Rule::unique('outlets','code')->where('business_id',$r->user()->business_id)]]); $id = DB::table('outlets')->insertGetId($data + ['business_id' => $r->user()->business_id, 'created_at' => now(), 'updated_at' => now()]); $this->points->audit($r->user(), 'outlet_created', 'outlet', $id, null, $data); return back()->with('success', 'Outlet berhasil ditambahkan.'); }
    public function audit(Request $r) { $this->owner($r); $logs = DB::table('audit_logs as a')->join('users as u', 'u.id', '=', 'a.actor_id')->where('a.business_id', $r->user()->business_id)->select('a.*', 'u.name as actor_name')->orderByDesc('a.id')->paginate(20); return view('audit', compact('logs')); }
}
