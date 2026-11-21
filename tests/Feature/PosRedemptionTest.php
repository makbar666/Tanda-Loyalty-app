<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosRedemptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_confirmation_refund_and_release_are_idempotent(): void
    {
        $business=DB::table('businesses')->insertGetId(['name'=>'Toko Uji','created_at'=>now(),'updated_at'=>now()]);
        $outlet=DB::table('outlets')->insertGetId(['business_id'=>$business,'name'=>'Utama','code'=>'UTM','created_at'=>now(),'updated_at'=>now()]);
        $owner=User::create(['business_id'=>$business,'outlet_id'=>$outlet,'role'=>'owner','name'=>'Owner','email'=>'owner@uji.local','password'=>'password123']);
        DB::table('point_rules')->insert(['business_id'=>$business,'version'=>1,'earn_step'=>10000,'earn_points'=>1,'minimum_spend'=>10000,'redeem_value'=>100,'minimum_redeem'=>10,'maximum_redeem_percent'=>50,'approval_threshold'=>100,'valid_days'=>365,'effective_at'=>now()->subMinute(),'created_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
        $member=DB::table('members')->insertGetId(['business_id'=>$business,'code'=>'M001','name'=>'Budi','phone'=>'6281234567890','point_balance'=>20,'created_at'=>now(),'updated_at'=>now()]);
        $lot=DB::table('point_lots')->insertGetId(['business_id'=>$business,'member_id'=>$member,'original_points'=>20,'remaining_points'=>20,'expires_at'=>now()->addYear(),'created_at'=>now(),'updated_at'=>now()]);
        $secret=Str::random(64);$connection=DB::table('pos_connections')->insertGetId(['business_id'=>$business,'created_by'=>$owner->id,'name'=>'Kasir','secret_hash'=>hash('sha256',$secret),'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $headers=['Authorization'=>'Bearer '.$connection.'.'.$secret];
        $key=(string)Str::uuid();
        $items=[['name'=>'Kopi','quantity'=>1,'unit_price'=>20000,'eligible'=>true]];
        $reserve=['external_key'=>$key,'outlet_id'=>$outlet,'member_id'=>$member,'subtotal'=>20000,'discount'=>0,'eligible_subtotal'=>20000,'points'=>10,'operator_role'=>'cashier','items'=>$items];

        $this->withHeaders($headers)->postJson('/api/v1/pos/redemptions',$reserve)->assertOk()->assertJsonPath('redeem_rupiah',1000)->assertJsonPath('total',19000);
        $this->postJson('/api/v1/pos/redemptions',$reserve)->assertOk();
        $this->assertSame(10,DB::table('members')->where('id',$member)->value('point_balance'));
        $this->assertSame(10,DB::table('point_lots')->where('id',$lot)->value('remaining_points'));
        $this->postJson('/api/v1/pos/redemptions',[...$reserve,'external_key'=>(string)Str::uuid(),'points'=>15])->assertStatus(422);
        $this->assertSame(10,DB::table('members')->where('id',$member)->value('point_balance'));

        $sale=['external_sale_id'=>'42','idempotency_key'=>$key,'reservation_key'=>$key,'outlet_id'=>$outlet,'member_id'=>$member,'subtotal'=>20000,'discount'=>0,'redeem_points'=>10,'redeem_rupiah'=>1000,'total'=>19000,'payment_method'=>'cash','items'=>$items];
        $this->postJson('/api/v1/pos/sales',$sale)->assertOk()->assertJsonPath('earned_points',1);
        $this->postJson('/api/v1/pos/sales',$sale)->assertOk();
        $this->assertDatabaseCount('transactions',1);
        $this->assertSame(11,DB::table('members')->where('id',$member)->value('point_balance'));
        $this->postJson('/api/v1/pos/redemptions/'.$key.'/release')->assertStatus(422);

        $this->postJson('/api/v1/pos/sales/42/refund',['reason'=>'Barang dikembalikan'])->assertOk();
        $this->assertSame(20,DB::table('members')->where('id',$member)->value('point_balance'));

        $second=(string)Str::uuid();
        $this->postJson('/api/v1/pos/redemptions',[...$reserve,'external_key'=>$second])->assertOk();
        $this->postJson('/api/v1/pos/redemptions/'.$second.'/release')->assertOk();
        $this->postJson('/api/v1/pos/redemptions/'.$second.'/release')->assertOk();
        $this->assertSame(20,DB::table('members')->where('id',$member)->value('point_balance'));
    }
}
