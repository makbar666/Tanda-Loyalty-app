<?php

namespace App\Http\Controllers;

use App\Services\PointService;
use App\Services\PosRedemptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosApiController extends Controller
{
    public function __construct(private PointService $points, private PosRedemptionService $redemptions) {}
    private function connectionFor(Request $r): object
    {
        $token=(string)$r->bearerToken();
        if (!preg_match('/^(\d+)\.([A-Za-z0-9]{64})$/',$token,$parts)) abort(401);
        $connection=DB::table('pos_connections')->where('id',(int)$parts[1])->where('active',true)->first();
        if (!$connection || !hash_equals($connection->secret_hash,hash('sha256',$parts[2]))) abort(401);
        DB::table('pos_connections')->where('id',$connection->id)->update(['last_used_at'=>now()]);
        return $connection;
    }
    public function connection(Request $r) { $c=$this->connectionFor($r); return response()->json(['business_id'=>$c->business_id,'business_name'=>DB::table('businesses')->where('id',$c->business_id)->value('name'),'outlets'=>DB::table('outlets')->where('business_id',$c->business_id)->select('id','name','code')->get()]); }
    public function member(Request $r) { $c=$this->connectionFor($r); $data=$r->validate(['q'=>'required|string|min:3|max:50']); $q=trim($data['q']); $digits=preg_replace('/\D+/','',$q); if(Str::startsWith($digits,'0'))$digits='62'.substr($digits,1); $members=DB::table('members')->where('business_id',$c->business_id)->where(function($query)use($q,$digits){$query->where('name','like','%'.$q.'%')->orWhere('code','like','%'.$q.'%');if(strlen($digits)>=3)$query->orWhere('phone','like','%'.$digits.'%');})->select('id','name','code','phone','point_balance')->orderBy('name')->limit(10)->get(); return response()->json(['members'=>$members,'redemption'=>$this->redemptions->options($c->business_id)]); }
    public function reserve(Request $r)
    {
        $c=$this->connectionFor($r);
        $d=$r->validate([
            'external_key'=>'required|uuid','outlet_id'=>'required|integer','member_id'=>'required|integer',
            'subtotal'=>'required|integer|min:0','discount'=>'required|integer|min:0',
            'eligible_subtotal'=>'required|integer|min:0','points'=>'required|integer|min:1',
            'operator_role'=>'required|string|max:40',
            'items'=>'required|array|min:1','items.*.name'=>'required|string|max:120',
            'items.*.quantity'=>'required|integer|min:1|max:1000','items.*.unit_price'=>'required|integer|min:0|max:1000000000',
            'items.*.eligible'=>'required|boolean',
        ]);
        $subtotal=array_sum(array_map(fn($i)=>$i['quantity']*$i['unit_price'],$d['items']));
        $eligible=array_sum(array_map(fn($i)=>$i['eligible']?$i['quantity']*$i['unit_price']:0,$d['items']));
        if($subtotal!==$d['subtotal']||$eligible!==$d['eligible_subtotal']||$d['discount']>$subtotal)abort(422,'Nominal reservasi tidak cocok.');
        $reservation=$this->redemptions->reserve($c,$d);
        return response()->json(['status'=>$reservation->status,'points'=>$reservation->points,'redeem_rupiah'=>$reservation->rupiah,'total'=>$reservation->subtotal-$reservation->discount-$reservation->rupiah]);
    }
    public function release(Request $r,string $key)
    {
        $c=$this->connectionFor($r);
        $this->redemptions->release($c,$key);
        return response()->json(['status'=>'released']);
    }
    public function sale(Request $r)
    {
        $c=$this->connectionFor($r);
        $d=$r->validate(['external_sale_id'=>'required|string|max:100','idempotency_key'=>'required|uuid','outlet_id'=>'required|integer','member_id'=>'required|integer','subtotal'=>'required|integer|min:0','discount'=>'required|integer|min:0','total'=>'required|integer|min:0','redeem_points'=>'sometimes|integer|min:0','redeem_rupiah'=>'sometimes|integer|min:0','reservation_key'=>'sometimes|uuid','payment_method'=>'required|in:cash,transfer,qris,card','items'=>'required|array|min:1','items.*.name'=>'required|string|max:120','items.*.quantity'=>'required|integer|min:1|max:1000','items.*.unit_price'=>'required|integer|min:0|max:1000000000','items.*.eligible'=>'required|boolean']);
        if(!DB::table('outlets')->where('business_id',$c->business_id)->where('id',$d['outlet_id'])->exists())abort(422,'Outlet tidak cocok.');
        if(!DB::table('members')->where('business_id',$c->business_id)->where('id',$d['member_id'])->exists())abort(422,'Member tidak cocok.');
        $subtotal=array_sum(array_map(fn($i)=>$i['quantity']*$i['unit_price'],$d['items']));
        $redeemPoints=(int)($d['redeem_points']??0);$redeemRupiah=(int)($d['redeem_rupiah']??0);
        if($subtotal!==$d['subtotal'] || $subtotal-$d['discount']-$redeemRupiah!==$d['total'])abort(422,'Nominal tidak cocok.');
        if(($redeemPoints>0)!==($redeemRupiah>0) || ($redeemPoints>0 && empty($d['reservation_key'])))abort(422,'Reservasi poin wajib dan harus cocok.');
        $hash=hash('sha256',json_encode($d,JSON_THROW_ON_ERROR));
        $existing=DB::table('pos_sale_links')->where('pos_connection_id',$c->id)->where('external_sale_id',$d['external_sale_id'])->first();
        if($existing){if(!hash_equals($existing->payload_hash,$hash))abort(409,'ID penjualan telah digunakan dengan isi berbeda.');return $this->result($existing->transaction_id);}
        return DB::transaction(function()use($c,$d,$hash,$redeemPoints,$redeemRupiah){
            $actor=DB::table('users')->where('id',$c->created_by)->where('business_id',$c->business_id)->first();
            if(!$actor)abort(403);
            $reservation=null;
            if($redeemPoints){
                $reservation=DB::table('pos_point_reservations')->where('pos_connection_id',$c->id)->where('external_key',$d['reservation_key'])->first();
                if(!$reservation||$reservation->status!=='reserved'||$reservation->member_id!==$d['member_id']||$reservation->outlet_id!==$d['outlet_id']||$reservation->points!==$redeemPoints||$reservation->rupiah!==$redeemRupiah)abort(422,'Reservasi poin tidak cocok.');
            }
            $input=['idempotency_key'=>$d['idempotency_key'],'outlet_id'=>$d['outlet_id'],'member_id'=>$d['member_id'],'discount'=>$d['discount'],'redeem_points'=>$redeemPoints,'payment_method'=>$d['payment_method'],'items'=>$d['items']];
            if($reservation)$input['reservation_id']=$reservation->id;
            $tx=$this->points->checkout($actor,$input);
            if($tx->total!==$d['total'])throw ValidationException::withMessages(['total'=>'Total loyalty tidak cocok.']);
            DB::table('pos_sale_links')->insert(['pos_connection_id'=>$c->id,'external_sale_id'=>$d['external_sale_id'],'payload_hash'=>$hash,'transaction_id'=>$tx->id,'created_at'=>now(),'updated_at'=>now()]);
            return $this->result($tx->id);
        },3);
    }
    private function result(int $id) { $tx=DB::table('transactions')->where('id',$id)->first(); return response()->json(['transaction_id'=>$tx->id,'status'=>$tx->status,'earned_points'=>$tx->earned_points,'balance_after'=>$tx->balance_after]); }
    public function refund(Request $r,string $externalId) { $c=$this->connectionFor($r); $d=$r->validate(['reason'=>'required|string|min:5|max:500']); $link=DB::table('pos_sale_links')->where('pos_connection_id',$c->id)->where('external_sale_id',$externalId)->first(); if(!$link)abort(404); $actor=DB::table('users')->where('id',$c->created_by)->where('business_id',$c->business_id)->first(); if(!$actor)abort(403); $tx=$this->points->refund($actor,$link->transaction_id,$d['reason']); return response()->json(['transaction_id'=>$tx->id,'status'=>$tx->status]); }
}
