<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosRedemptionService
{
    public function __construct(private PointService $points) {}

    public function options(int $businessId): ?array
    {
        $rule = $this->points->rule($businessId);
        return $rule ? [
            'redeem_value' => $rule->redeem_value,
            'minimum_redeem' => $rule->minimum_redeem,
            'maximum_redeem_percent' => $rule->maximum_redeem_percent,
            'approval_threshold' => $rule->approval_threshold,
        ] : null;
    }

    public function reserve(object $connection, array $data): object
    {
        return DB::transaction(function () use ($connection, $data) {
            $existing = DB::table('pos_point_reservations')
                ->where('pos_connection_id', $connection->id)->where('external_key', $data['external_key'])->lockForUpdate()->first();
            if ($existing) {
                $this->assertSameRequest($existing, $data);
                if ($existing->status !== 'reserved') throw ValidationException::withMessages(['external_key' => 'Reservasi ini sudah selesai.']);
                return $existing;
            }

            $member = DB::table('members')->where('business_id', $connection->business_id)
                ->where('id', $data['member_id'])->lockForUpdate()->first();
            if (!$member) throw ValidationException::withMessages(['member_id' => 'Member tidak ditemukan.']);
            if (!DB::table('outlets')->where('business_id', $connection->business_id)->where('id', $data['outlet_id'])->exists()) {
                throw ValidationException::withMessages(['outlet_id' => 'Outlet tidak valid.']);
            }
            $this->points->expireMember($member);
            $member = DB::table('members')->where('id', $member->id)->first();
            $rule = $this->points->rule($connection->business_id);
            if (!$rule) throw ValidationException::withMessages(['points' => 'Aturan poin belum aktif.']);
            if (!in_array($data['operator_role'], ['owner', 'manager'], true) && $data['points'] >= $rule->approval_threshold) {
                throw ValidationException::withMessages(['points' => 'Penukaran ini memerlukan manager atau owner.']);
            }
            $eligible = max(0, $data['eligible_subtotal'] - $data['discount']);
            $preview = $this->points->preview($rule, $eligible, 0, $data['points']);
            if ($data['points'] > $member->point_balance) throw ValidationException::withMessages(['points' => 'Saldo poin tidak cukup.']);

            $id = DB::table('pos_point_reservations')->insertGetId([
                'pos_connection_id' => $connection->id, 'external_key' => $data['external_key'],
                'business_id' => $connection->business_id, 'member_id' => $member->id, 'outlet_id' => $data['outlet_id'],
                'point_rule_id' => $rule->id, 'points' => $data['points'], 'rupiah' => $preview['redeem_rupiah'],
                'subtotal' => $data['subtotal'], 'discount' => $data['discount'], 'eligible_subtotal' => $data['eligible_subtotal'],
                'status' => 'reserved', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $left = $data['points'];
            foreach (DB::table('point_lots')->where('member_id', $member->id)->where('remaining_points', '>', 0)
                ->where('expires_at', '>', now())->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get() as $lot) {
                $take = min($left, $lot->remaining_points);
                if (!$take) break;
                DB::table('point_lots')->where('id', $lot->id)->decrement('remaining_points', $take);
                DB::table('pos_reservation_allocations')->insert(['reservation_id' => $id, 'point_lot_id' => $lot->id, 'points' => $take]);
                $left -= $take;
            }
            if ($left) throw ValidationException::withMessages(['points' => 'Saldo lot poin tidak cukup.']);
            DB::table('members')->where('id', $member->id)->decrement('point_balance', $data['points']);
            $this->points->recordLedger($connection->business_id, $member->id, null, null, $connection->created_by, 'pos_reserve', -$data['points'], 'Reservasi POS '.$data['external_key']);

            return DB::table('pos_point_reservations')->where('id', $id)->first();
        }, 3);
    }

    public function release(object $connection, string $key): void
    {
        DB::transaction(function () use ($connection, $key) {
            $reservation = DB::table('pos_point_reservations')->where('pos_connection_id', $connection->id)
                ->where('external_key', $key)->first();
            if (!$reservation) return;
            $member = DB::table('members')->where('id', $reservation->member_id)->lockForUpdate()->first();
            $reservation = DB::table('pos_point_reservations')->where('id', $reservation->id)->lockForUpdate()->first();
            if ($reservation->status === 'released') return;
            if ($reservation->status === 'confirmed') throw ValidationException::withMessages(['external_key' => 'Poin sudah dipakai oleh penjualan lunas.']);
            $restored = 0;
            foreach (DB::table('pos_reservation_allocations')->where('reservation_id', $reservation->id)->get() as $allocation) {
                $lot = DB::table('point_lots')->where('id', $allocation->point_lot_id)->lockForUpdate()->first();
                if (Carbon::parse($lot->expires_at)->isFuture()) {
                    DB::table('point_lots')->where('id', $lot->id)->increment('remaining_points', $allocation->points);
                    $restored += $allocation->points;
                }
            }
            if ($restored) DB::table('members')->where('id', $member->id)->increment('point_balance', $restored);
            $this->points->recordLedger($connection->business_id, $member->id, null, null, $connection->created_by, 'pos_release', $restored, 'Pembatalan reservasi POS '.$key);
            DB::table('pos_point_reservations')->where('id', $reservation->id)->update(['status' => 'released', 'updated_at' => now()]);
        }, 3);
    }

    private function assertSameRequest(object $existing, array $data): void
    {
        foreach (['member_id', 'outlet_id', 'points', 'subtotal', 'discount', 'eligible_subtotal'] as $field) {
            if ((int) $existing->$field !== (int) $data[$field]) {
                throw ValidationException::withMessages(['external_key' => 'Kunci reservasi sudah digunakan dengan isi berbeda.']);
            }
        }
    }
}
