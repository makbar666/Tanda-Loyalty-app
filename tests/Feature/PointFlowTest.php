<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PointFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setupBusiness(): array
    {
        $business = DB::table('businesses')->insertGetId(['name' => 'Toko Uji', 'created_at' => now(), 'updated_at' => now()]);
        $outlet = DB::table('outlets')->insertGetId(['business_id' => $business, 'name' => 'Pusat', 'code' => 'PST', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::create(['business_id' => $business, 'outlet_id' => $outlet, 'role' => 'manager', 'name' => 'Kasir Uji', 'email' => Str::random(8).'@test.local', 'password' => 'password123']);
        DB::table('point_rules')->insert(['business_id' => $business, 'version' => 1, 'earn_step' => 10000, 'earn_points' => 1, 'minimum_spend' => 10000, 'redeem_value' => 100, 'minimum_redeem' => 10, 'maximum_redeem_percent' => 50, 'valid_days' => 365, 'effective_at' => now()->subMinute(), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $member = DB::table('members')->insertGetId(['business_id' => $business, 'code' => Str::random(8), 'name' => 'Budi', 'phone' => '6281234567890', 'created_at' => now(), 'updated_at' => now()]);
        return [$user, $member, $outlet];
    }

    private function input(int $member, int $outlet, int $amount, int $redeem = 0, ?string $key = null): array
    {
        return ['idempotency_key' => $key ?? (string) Str::uuid(), 'outlet_id' => $outlet, 'member_id' => $member, 'discount' => 0, 'redeem_points' => $redeem, 'payment_method' => 'cash', 'items' => [['name' => 'Produk', 'quantity' => 1, 'unit_price' => $amount, 'eligible' => true]]];
    }

    public function test_earn_redeem_retry_and_refund(): void
    {
        [$user, $member, $outlet] = $this->setupBusiness(); $service = app(PointService::class);
        $firstInput = $this->input($member, $outlet, 200000);
        $first = $service->checkout($user, $firstInput);
        $same = $service->checkout($user, $firstInput);
        $this->assertSame($first->id, $same->id);
        $this->assertSame(20, DB::table('members')->where('id', $member)->value('point_balance'));
        $second = $service->checkout($user, $this->input($member, $outlet, 120000, 20));
        $this->assertSame(2000, $second->redeem_rupiah);
        $this->assertSame(11, $second->earned_points);
        $this->assertSame(11, DB::table('members')->where('id', $member)->value('point_balance'));
        $service->refund($user, $second->id, 'Pelanggan membatalkan belanja');
        $service->refund($user, $second->id, 'Retry refund');
        $this->assertSame(20, DB::table('members')->where('id', $member)->value('point_balance'));
        $this->assertSame(1, DB::table('transactions')->where('id', $second->id)->where('status', 'refunded')->count());
    }

    public function test_refund_after_points_spent_creates_debt(): void
    {
        [$user, $member, $outlet] = $this->setupBusiness(); $service = app(PointService::class);
        $first = $service->checkout($user, $this->input($member, $outlet, 200000));
        $service->checkout($user, $this->input($member, $outlet, 20000, 20));
        $service->refund($user, $first->id, 'Transaksi asal dibatalkan');
        $record = DB::table('members')->where('id', $member)->first();
        $this->assertSame(1, $record->point_balance);
        $this->assertSame(20, $record->point_debt);
        $service->checkout($user, $this->input($member, $outlet, 200000));
        $this->assertSame(0, DB::table('members')->where('id', $member)->value('point_debt'));
    }

    public function test_expiry_is_idempotent(): void
    {
        [$user, $member, $outlet] = $this->setupBusiness(); $service = app(PointService::class);
        $service->checkout($user, $this->input($member, $outlet, 100000));
        DB::table('point_lots')->where('member_id', $member)->update(['expires_at' => now()->subDay()]);
        $this->artisan('points:expire')->assertSuccessful();
        $this->artisan('points:expire')->assertSuccessful();
        $this->assertSame(0, DB::table('members')->where('id', $member)->value('point_balance'));
        $this->assertSame(1, DB::table('point_ledger_entries')->where('member_id', $member)->where('type', 'expire')->count());
    }

    public function test_refund_of_expired_points_does_not_create_debt(): void
    {
        [$user, $member, $outlet] = $this->setupBusiness(); $service = app(PointService::class);
        $tx = $service->checkout($user, $this->input($member, $outlet, 100000));
        DB::table('point_lots')->where('member_id', $member)->update(['expires_at' => now()->subDay()]);
        $this->artisan('points:expire')->assertSuccessful();
        $service->refund($user, $tx->id, 'Pengembalian setelah poin hangus');
        $this->assertSame(0, DB::table('members')->where('id', $member)->value('point_debt'));
    }

    public function test_authenticated_pages_render_and_manager_cannot_change_rules(): void
    {
        [$user, $member] = $this->setupBusiness();
        foreach (['/', '/members', '/members/'.$member, '/checkout', '/transactions'] as $path) $this->actingAs($user)->get($path)->assertOk();
        $this->actingAs($user)->get('/rules')->assertForbidden();
        $this->actingAs($user)->get('/audit')->assertForbidden();
    }

    public function test_owner_pages_and_member_adjustment(): void
    {
        [$user, $member] = $this->setupBusiness();
        $user->update(['role' => 'owner', 'outlet_id' => null]);
        foreach (['/rules', '/team', '/audit', '/members/'.$member] as $path) $this->actingAs($user)->get($path)->assertOk();
        $this->actingAs($user)->post('/members/'.$member.'/adjust', ['points' => 25, 'reason' => 'Bonus pembukaan toko'])->assertRedirect();
        $this->assertSame(25, DB::table('members')->where('id', $member)->value('point_balance'));
        $this->actingAs($user)->post('/members/'.$member.'/adjust', ['points' => -10, 'reason' => 'Koreksi input manual'])->assertRedirect();
        $this->assertSame(15, DB::table('members')->where('id', $member)->value('point_balance'));
    }
}
