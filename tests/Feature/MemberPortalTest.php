<?php

namespace Tests\Feature;

use App\Services\MemberOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $businessName, string $memberName, string $phone = '6281234567890'): array
    {
        $business = DB::table('businesses')->insertGetId(['name' => $businessName, 'created_at' => now(), 'updated_at' => now()]);
        $member = DB::table('members')->insertGetId(['business_id' => $business, 'name' => $memberName,
            'phone' => $phone, 'code' => Str::random(8), 'point_balance' => 12,
            'created_at' => now(), 'updated_at' => now()]);
        return [$business, $member];
    }

    public function test_member_sees_only_own_business_and_ledger_after_one_time_code(): void
    {
        [$business, $member] = $this->member('Toko Satu', 'Budi');
        [$otherBusiness, $otherMember] = $this->member('Toko Dua', 'Rahasia');
        DB::table('point_ledger_entries')->insert([
            ['business_id' => $business, 'member_id' => $member, 'type' => 'earn', 'points_delta' => 12, 'note' => 'Belanja Budi', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $otherBusiness, 'member_id' => $otherMember, 'type' => 'earn', 'points_delta' => 99, 'note' => 'Rahasia outlet lain', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $code = null;
        $sender = \Mockery::mock(MemberOtpSender::class);
        $sender->shouldReceive('send')->once()->andReturnUsing(function ($phone, $value) use (&$code) { $code = $value; });
        $this->app->instance(MemberOtpSender::class, $sender);

        $this->get(route('portal.home', $business))->assertRedirect(route('portal.login', $business));
        $this->post(route('portal.code', $business), ['phone' => '0812-3456-7890'])->assertRedirect(route('portal.verify', $business));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->post(route('portal.confirm', $business), ['code' => $code])->assertRedirect(route('portal.home', $business));
        $this->get(route('portal.home', $business))->assertOk()->assertSee('Budi')->assertSee('Belanja Budi')->assertDontSee('Rahasia outlet lain');
        $this->get(route('portal.home', $otherBusiness))->assertRedirect(route('portal.login', $otherBusiness));
        $this->post(route('portal.confirm', $business), ['code' => $code])->assertRedirect(route('portal.login', $business));
        $this->post(route('portal.logout', $business))->assertRedirect(route('portal.login', $business));
        $this->get(route('portal.home', $business))->assertRedirect(route('portal.login', $business));
    }

    public function test_unknown_number_is_generic_and_code_expires_after_five_minutes(): void
    {
        [$business] = $this->member('Toko Satu', 'Budi');
        $sender = \Mockery::mock(MemberOtpSender::class);
        $sender->shouldReceive('send')->never();
        $this->app->instance(MemberOtpSender::class, $sender);
        $this->post(route('portal.code', $business), ['phone' => '081111111111'])->assertRedirect(route('portal.verify', $business));
        $this->get(route('portal.verify', $business))->assertOk()->assertSee('Kode dikirim');
        $this->post(route('portal.confirm', $business), ['code' => '111111'])->assertSessionHasErrors('code');

        $code = null;
        $sender = \Mockery::mock(MemberOtpSender::class);
        $sender->shouldReceive('send')->once()->andReturnUsing(function ($phone, $value) use (&$code) { $code = $value; });
        $this->app->instance(MemberOtpSender::class, $sender);
        $this->post(route('portal.code', $business), ['phone' => '081234567890'])->assertRedirect(route('portal.verify', $business));
        $this->travel(6)->minutes();
        $this->post(route('portal.confirm', $business), ['code' => $code])->assertSessionHasErrors('code');
        $this->get(route('portal.home', $business))->assertRedirect(route('portal.login', $business));
    }

    public function test_code_has_attempt_limit_and_resend_cooldown(): void
    {
        [$business, $member] = $this->member('Toko Satu', 'Budi');
        $code = null;
        $sender = \Mockery::mock(MemberOtpSender::class);
        $sender->shouldReceive('send')->once()->andReturnUsing(function ($phone, $value) use (&$code) { $code = $value; });
        $this->app->instance(MemberOtpSender::class, $sender);
        $url = route('portal.code', $business);
        $this->post($url, ['phone' => '081234567890'])->assertRedirect();
        $this->post($url, ['phone' => '081234567890'])->assertRedirect();
        $this->assertSame(1, DB::table('member_login_codes')->where('member_id', $member)->count());
        for ($i = 0; $i < 5; $i++) $this->post(route('portal.confirm', $business), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post(route('portal.confirm', $business), ['code' => $code])->assertSessionHasErrors('code');
        $this->get(route('portal.home', $business))->assertRedirect();
    }

    public function test_delivery_failure_does_not_reveal_membership_or_leave_a_valid_code(): void
    {
        [$business, $member] = $this->member('Toko Satu', 'Budi');
        $sender = \Mockery::mock(MemberOtpSender::class);
        $sender->shouldReceive('send')->once()->andThrow(new \RuntimeException('Provider tidak tersedia'));
        $this->app->instance(MemberOtpSender::class, $sender);
        $response = $this->post(route('portal.code', $business), ['phone' => '081234567890']);
        $response->assertRedirect(route('portal.verify', $business));
        $this->assertDatabaseMissing('member_login_codes', ['member_id' => $member]);
    }

    public function test_whatsapp_sender_uses_configured_template(): void
    {
        config()->set('services.member_otp.driver', 'whatsapp');
        config()->set('services.member_otp.whatsapp', [
            'phone_number_id' => '12345', 'version' => 'v27.0', 'token' => 'test-token',
            'template' => 'member_login', 'language' => 'id',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        app(MemberOtpSender::class)->send('6281234567890', '123456');
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v27.0/12345/messages'
            && $request['to'] === '6281234567890'
            && $request['template']['components'][0]['parameters'][0]['text'] === '123456');
    }
}
