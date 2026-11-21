<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_sale_retry_and_refund_are_idempotent(): void
    {
        $business=DB::table('businesses')->insertGetId(['name'=>'Toko Uji','created_at'=>now(),'updated_at'=>now()]);
        $outlet=DB::table('outlets')->insertGetId(['business_id'=>$business,'name'=>'Utama','code'=>'UTM','created_at'=>now(),'updated_at'=>now()]);
        $owner=User::create(['business_id'=>$business,'outlet_id'=>$outlet,'role'=>'owner','name'=>'Owner','email'=>'owner@uji.local','password'=>'password123']);
        DB::table('point_rules')->insert(['business_id'=>$business,'version'=>1,'earn_step'=>10000,'earn_points'=>1,'minimum_spend'=>10000,'redeem_value'=>100,'minimum_redeem'=>10,'maximum_redeem_percent'=>50,'valid_days'=>365,'effective_at'=>now()->subMinute(),'created_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
        $member=DB::table('members')->insertGetId(['business_id'=>$business,'code'=>'M001','name'=>'Budi','phone'=>'6281234567890','created_at'=>now(),'updated_at'=>now()]);
        $secret=Str::random(64);$connection=DB::table('pos_connections')->insertGetId(['business_id'=>$business,'created_by'=>$owner->id,'name'=>'Kasir','secret_hash'=>hash('sha256',$secret),'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $headers=['Authorization'=>'Bearer '.$connection.'.'.$secret];
        $this->getJson('/api/v1/pos/connection')->assertUnauthorized();
        $this->withHeaders($headers)->getJson('/api/v1/pos/members?q=081234567890')->assertOk()->assertJsonPath('members.0.id',$member);
        $payload=['external_sale_id'=>'42','idempotency_key'=>(string)Str::uuid(),'outlet_id'=>$outlet,'member_id'=>$member,'subtotal'=>20000,'discount'=>0,'total'=>20000,'payment_method'=>'cash','items'=>[['name'=>'Kopi','quantity'=>1,'unit_price'=>20000,'eligible'=>true]]];
        $this->withHeaders($headers)->postJson('/api/v1/pos/sales',$payload)->assertOk()->assertJsonPath('earned_points',2);
        $this->withHeaders($headers)->postJson('/api/v1/pos/sales',$payload)->assertOk();
        $this->assertDatabaseCount('pos_sale_links',1);$this->assertSame(2,DB::table('members')->where('id',$member)->value('point_balance'));
        $this->withHeaders(['Authorization'=>'Bearer invalid'])->postJson('/api/v1/pos/reconciliation',['sale_ids'=>['42']])->assertUnauthorized();
        $this->withHeaders($headers)->postJson('/api/v1/pos/reconciliation',['sale_ids'=>['42','missing']])
            ->assertOk()->assertJsonPath('sales.42.status','paid')->assertJsonPath('sales.42.member_id',$member)
            ->assertJsonPath('sales.missing',null);
        $secondSecret=Str::random(64);
        $secondConnection=DB::table('pos_connections')->insertGetId(['business_id'=>$business,'created_by'=>$owner->id,
            'name'=>'Kasir lain','secret_hash'=>hash('sha256',$secondSecret),'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->withHeaders(['Authorization'=>'Bearer '.$secondConnection.'.'.$secondSecret])
            ->postJson('/api/v1/pos/reconciliation',['sale_ids'=>['42']])->assertOk()->assertJsonPath('sales.42',null);
        $this->withHeaders($headers)->postJson('/api/v1/pos/sales/42/refund',['reason'=>'Barang dikembalikan'])->assertOk();
        $this->withHeaders($headers)->postJson('/api/v1/pos/sales/42/refund',['reason'=>'Barang dikembalikan'])->assertOk();
        $this->assertSame(0,DB::table('members')->where('id',$member)->value('point_balance'));
        $this->withHeaders($headers)->postJson('/api/v1/pos/reconciliation',['sale_ids'=>['42']])
            ->assertJsonPath('sales.42.status','refunded');
    }
}
