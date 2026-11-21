<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PointService
{
    public function rule(int $businessId): ?object
    {
        return DB::table('point_rules')->where('business_id', $businessId)->where('effective_at', '<=', now())->orderByDesc('effective_at')->orderByDesc('id')->first();
    }

    public function preview(?object $rule, int $subtotal, int $discount, int $points): array
    {
        $base = max(0, $subtotal - $discount);
        $value = $rule ? $points * $rule->redeem_value : 0;
        if ($points > 0 && (!$rule || $points < $rule->minimum_redeem || $value > intdiv($base * $rule->maximum_redeem_percent, 100) || $value > $base)) {
            throw ValidationException::withMessages(['redeem_points' => 'Jumlah penukaran melewati minimum atau batas transaksi.']);
        }
        $eligible = max(0, $base - $value);
        return ['redeem_rupiah' => $value, 'total' => $eligible, 'earned_points' => $rule && $eligible >= $rule->minimum_spend ? intdiv($eligible, $rule->earn_step) * $rule->earn_points : 0];
    }

    public function checkout(object $user, array $input): object
    {
        try { return DB::transaction(function () use ($user, $input) {
            $existing = DB::table('transactions')->where('business_id', $user->business_id)->where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing) return $existing;
            $member = isset($input['member_id']) ? DB::table('members')->where('business_id', $user->business_id)->where('id', $input['member_id'])->lockForUpdate()->first() : null;
            if (isset($input['member_id']) && !$member) throw ValidationException::withMessages(['member_id' => 'Member tidak ditemukan.']);
            $outletId = $user->role === 'owner' ? $input['outlet_id'] : $user->outlet_id;
            if (!DB::table('outlets')->where('business_id', $user->business_id)->where('id', $outletId)->exists()) throw ValidationException::withMessages(['outlet_id' => 'Outlet tidak valid.']);
            $reservation = !empty($input['reservation_id']) ? DB::table('pos_point_reservations')->where('id', $input['reservation_id'])->lockForUpdate()->first() : null;
            if (!empty($input['reservation_id']) && (!$reservation || $reservation->status !== 'reserved' || $reservation->business_id !== $user->business_id || $reservation->member_id !== ($member?->id))) {
                throw ValidationException::withMessages(['redeem_points' => 'Reservasi poin tidak valid.']);
            }
            $rule = $reservation ? DB::table('point_rules')->where('id', $reservation->point_rule_id)->first() : $this->rule($user->business_id);
            if ($rule && ($input['redeem_points'] ?? 0) >= $rule->approval_threshold && $user->role === 'cashier') throw ValidationException::withMessages(['redeem_points' => 'Penukaran ini harus diproses oleh manajer atau owner.']);
            $items = $input['items'];
            $subtotal = array_sum(array_map(fn ($i) => $i['quantity'] * $i['unit_price'], $items));
            $eligibleSubtotal = array_sum(array_map(fn ($i) => !empty($i['eligible']) ? $i['quantity'] * $i['unit_price'] : 0, $items));
            $discount = $input['discount'] ?? 0;
            if ($discount > $subtotal) throw ValidationException::withMessages(['discount' => 'Diskon melebihi subtotal.']);
            $points = $input['redeem_points'] ?? 0;
            if ($points && !$member) throw ValidationException::withMessages(['redeem_points' => 'Pilih member untuk menukar poin.']);
            $eligibleAfterDiscount = max(0, $eligibleSubtotal - $discount);
            $preview = $this->preview($rule, $eligibleAfterDiscount, 0, $points);
            if ($reservation && ($reservation->points !== $points || $reservation->rupiah !== $preview['redeem_rupiah'] || $reservation->outlet_id !== $outletId || $reservation->subtotal !== $subtotal || $reservation->discount !== $discount || $reservation->eligible_subtotal !== $eligibleSubtotal)) {
                throw ValidationException::withMessages(['redeem_points' => 'Nilai penjualan tidak cocok dengan reservasi poin.']);
            }
            if ($member && !$reservation && $points > $member->point_balance) throw ValidationException::withMessages(['redeem_points' => 'Saldo poin tidak cukup.']);
            $total = $subtotal - $discount - $preview['redeem_rupiah'];
            $earned = $member ? $preview['earned_points'] : 0;
            $id = DB::table('transactions')->insertGetId([
                'business_id' => $user->business_id, 'outlet_id' => $outletId, 'member_id' => $member?->id, 'cashier_id' => $user->id,
                'point_rule_id' => $rule?->id, 'reference' => 'TRX-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'idempotency_key' => $input['idempotency_key'], 'status' => 'paid', 'subtotal' => $subtotal, 'discount' => $discount,
                'redeem_points' => $points, 'redeem_rupiah' => $preview['redeem_rupiah'], 'total' => $total,
                'earned_points' => $earned, 'payment_method' => $input['payment_method'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($items as $item) DB::table('transaction_items')->insert(['transaction_id' => $id, 'name' => $item['name'], 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'line_total' => $item['quantity'] * $item['unit_price'], 'eligible' => !empty($item['eligible'])]);
            if ($member) {
                $this->expireMember($member);
                $member = DB::table('members')->where('id', $member->id)->first();
                if ($reservation) {
                    foreach (DB::table('pos_reservation_allocations')->where('reservation_id', $reservation->id)->get() as $allocation) {
                        DB::table('point_allocations')->insert(['transaction_id' => $id, 'point_lot_id' => $allocation->point_lot_id, 'points' => $allocation->points]);
                    }
                    $this->ledger($user->business_id, $member->id, $id, null, $user->id, 'pos_redeem_confirmed', 0);
                } else {
                    if ($points > $member->point_balance) throw ValidationException::withMessages(['redeem_points' => 'Sebagian poin sudah kedaluwarsa. Muat ulang saldo.']);
                    $left = $points;
                    foreach (DB::table('point_lots')->where('member_id', $member->id)->where('remaining_points', '>', 0)->where('expires_at', '>', now())->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get() as $lot) {
                        $take = min($left, $lot->remaining_points);
                        if (!$take) break;
                        DB::table('point_lots')->where('id', $lot->id)->decrement('remaining_points', $take);
                        DB::table('point_allocations')->insert(['transaction_id' => $id, 'point_lot_id' => $lot->id, 'points' => $take]);
                        $this->ledger($user->business_id, $member->id, $id, $lot->id, $user->id, 'redeem', -$take);
                        $left -= $take;
                    }
                    if ($left) throw ValidationException::withMessages(['redeem_points' => 'Saldo lot poin tidak cukup.']);
                }
                $netEarn = max(0, $earned - $member->point_debt);
                $debtPaid = min($earned, $member->point_debt);
                if ($netEarn) {
                    $lotId = DB::table('point_lots')->insertGetId(['business_id' => $user->business_id, 'member_id' => $member->id, 'source_transaction_id' => $id, 'original_points' => $netEarn, 'remaining_points' => $netEarn, 'expires_at' => now()->addDays($rule->valid_days), 'created_at' => now(), 'updated_at' => now()]);
                    $this->ledger($user->business_id, $member->id, $id, $lotId, $user->id, 'earn', $netEarn);
                }
                if ($debtPaid) $this->ledger($user->business_id, $member->id, $id, null, $user->id, 'debt_settled', 0, "$debtPaid poin menutup utang");
                $balanceAfter = $member->point_balance - ($reservation ? 0 : $points) + $netEarn;
                DB::table('members')->where('id', $member->id)->update(['point_balance' => $balanceAfter, 'point_debt' => $member->point_debt - $debtPaid, 'updated_at' => now()]);
                DB::table('transactions')->where('id', $id)->update(['balance_after' => $balanceAfter]);
            }
            if ($reservation) DB::table('pos_point_reservations')->where('id', $reservation->id)->update(['status' => 'confirmed', 'transaction_id' => $id, 'updated_at' => now()]);
            $this->audit($user, 'checkout', 'transaction', $id, null, ['total' => $total, 'earned_points' => $earned, 'redeem_points' => $points], $outletId);
            return DB::table('transactions')->where('id', $id)->first();
        }, 3); } catch (UniqueConstraintViolationException $e) {
            $existing = DB::table('transactions')->where('business_id', $user->business_id)->where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing) return $existing;
            throw $e;
        }
    }

    public function refund(object $user, int $id, string $reason): object
    {
        if (!in_array($user->role, ['owner', 'manager'])) abort(403);
        return DB::transaction(function () use ($user, $id, $reason) {
            $tx = DB::table('transactions')->where('business_id', $user->business_id)->where('id', $id)->lockForUpdate()->first();
            if (!$tx || ($user->role !== 'owner' && $tx->outlet_id !== $user->outlet_id)) abort(404);
            if ($tx->status === 'refunded') return $tx;
            if ($tx->status !== 'paid') throw ValidationException::withMessages(['refund' => 'Transaksi tidak dapat direfund.']);
            if ($tx->member_id) {
                $member = DB::table('members')->where('id', $tx->member_id)->lockForUpdate()->first();
                $this->expireMember($member);
                $member = DB::table('members')->where('id', $tx->member_id)->first();
                $balance = $member->point_balance; $debt = $member->point_debt;
                $earnedLot = DB::table('point_lots')->where('source_transaction_id', $id)->lockForUpdate()->first();
                if ($earnedLot) {
                    $remove = $earnedLot->remaining_points;
                    $expired = -(int) DB::table('point_ledger_entries')->where('point_lot_id', $earnedLot->id)->where('type', 'expire')->sum('points_delta');
                    DB::table('point_lots')->where('id', $earnedLot->id)->update(['remaining_points' => 0, 'updated_at' => now()]);
                    $balance -= $remove;
                    $this->ledger($tx->business_id, $tx->member_id, $id, $earnedLot->id, $user->id, 'refund_earn', -$remove);
                    $debt += max(0, $tx->earned_points - $remove - $expired);
                } elseif ($tx->earned_points) $debt += $tx->earned_points;
                foreach (DB::table('point_allocations')->where('transaction_id', $id)->get() as $allocation) {
                    $lot = DB::table('point_lots')->where('id', $allocation->point_lot_id)->lockForUpdate()->first();
                    if (Carbon::parse($lot->expires_at)->isFuture()) {
                        $cover = min($allocation->points, $debt); $debt -= $cover;
                        $restore = $allocation->points - $cover;
                        if ($restore) DB::table('point_lots')->where('id', $lot->id)->increment('remaining_points', $restore);
                        $balance += $restore;
                        $this->ledger($tx->business_id, $tx->member_id, $id, $lot->id, $user->id, 'refund_redeem', $restore);
                    }
                }
                DB::table('members')->where('id', $member->id)->update(['point_balance' => max(0, $balance), 'point_debt' => $debt, 'updated_at' => now()]);
            }
            DB::table('transactions')->where('id', $id)->update(['status' => 'refunded', 'refund_reason' => $reason, 'refunded_by' => $user->id, 'refunded_at' => now(), 'updated_at' => now()]);
            $this->audit($user, 'refund', 'transaction', $id, ['status' => 'paid'], ['status' => 'refunded', 'reason' => $reason], $tx->outlet_id);
            return DB::table('transactions')->where('id', $id)->first();
        }, 3);
    }

    public function expireMember(object $member): int
    {
        $expired = 0;
        foreach (DB::table('point_lots')->where('member_id', $member->id)->where('expires_at', '<=', now())->where('remaining_points', '>', 0)->lockForUpdate()->get() as $lot) {
            DB::table('point_lots')->where('id', $lot->id)->update(['remaining_points' => 0, 'updated_at' => now()]);
            $this->ledger($member->business_id, $member->id, null, $lot->id, null, 'expire', -$lot->remaining_points);
            $expired += $lot->remaining_points;
        }
        if ($expired) DB::table('members')->where('id', $member->id)->decrement('point_balance', $expired);
        return $expired;
    }

    public function adjust(object $user, int $memberId, int $points, string $reason): void
    {
        if ($user->role !== 'owner') abort(403);
        DB::transaction(function () use ($user, $memberId, $points, $reason) {
            $member = DB::table('members')->where('business_id', $user->business_id)->where('id', $memberId)->lockForUpdate()->first();
            if (!$member) abort(404);
            $this->expireMember($member);
            $member = DB::table('members')->where('id', $memberId)->first();
            $before = ['balance' => $member->point_balance, 'debt' => $member->point_debt];
            if ($points > 0) {
                $covered = min($member->point_debt, $points); $credit = $points - $covered;
                $rule = $this->rule($user->business_id);
                if ($credit) {
                    $lot = DB::table('point_lots')->insertGetId(['business_id' => $user->business_id, 'member_id' => $memberId, 'source_transaction_id' => null, 'original_points' => $credit, 'remaining_points' => $credit, 'expires_at' => now()->addDays($rule?->valid_days ?? 365), 'created_at' => now(), 'updated_at' => now()]);
                    $this->ledger($user->business_id, $memberId, null, $lot, $user->id, 'adjustment', $credit, $reason);
                }
                DB::table('members')->where('id', $memberId)->update(['point_balance' => $member->point_balance + $credit, 'point_debt' => $member->point_debt - $covered, 'updated_at' => now()]);
            } else {
                $remove = -$points;
                if ($remove > $member->point_balance) throw ValidationException::withMessages(['points' => 'Koreksi melebihi saldo tersedia.']);
                foreach (DB::table('point_lots')->where('member_id', $memberId)->where('remaining_points', '>', 0)->where('expires_at', '>', now())->orderBy('expires_at')->lockForUpdate()->get() as $lot) {
                    $take = min($remove, $lot->remaining_points); if (!$take) break;
                    DB::table('point_lots')->where('id', $lot->id)->decrement('remaining_points', $take);
                    $this->ledger($user->business_id, $memberId, null, $lot->id, $user->id, 'adjustment', -$take, $reason); $remove -= $take;
                }
                if ($remove) throw ValidationException::withMessages(['points' => 'Saldo lot poin tidak cukup.']);
                DB::table('members')->where('id', $memberId)->decrement('point_balance', -$points);
            }
            $this->audit($user, 'point_adjusted', 'member', $memberId, $before, ['points' => $points, 'reason' => $reason]);
        }, 3);
    }

    private function ledger(int $business, int $member, ?int $tx, ?int $lot, ?int $actor, string $type, int $delta, ?string $note = null): void
    {
        DB::table('point_ledger_entries')->insert(['business_id' => $business, 'member_id' => $member, 'transaction_id' => $tx, 'point_lot_id' => $lot, 'actor_id' => $actor, 'type' => $type, 'points_delta' => $delta, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function recordLedger(int $business, int $member, ?int $tx, ?int $lot, ?int $actor, string $type, int $delta, ?string $note = null): void
    {
        $this->ledger($business, $member, $tx, $lot, $actor, $type, $delta, $note);
    }

    public function audit(object $user, string $action, string $type, int $id, ?array $before, ?array $after, ?int $outlet = null): void
    {
        DB::table('audit_logs')->insert(['business_id' => $user->business_id, 'actor_id' => $user->id, 'outlet_id' => $outlet ?? $user->outlet_id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'before' => $before ? json_encode($before) : null, 'after' => $after ? json_encode($after) : null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
