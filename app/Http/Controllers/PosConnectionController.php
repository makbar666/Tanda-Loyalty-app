<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosConnectionController extends Controller
{
    private function owner(Request $r): void { abort_unless($r->user()->role === 'owner',403); }
    public function index(Request $r) { $this->owner($r); return view('pos-connections',['connections'=>DB::table('pos_connections')->where('business_id',$r->user()->business_id)->orderByDesc('id')->get()]); }
    public function store(Request $r) { $this->owner($r); $data=$r->validate(['name'=>'required|string|max:100']); $secret=Str::random(64); $id=DB::table('pos_connections')->insertGetId(['business_id'=>$r->user()->business_id,'created_by'=>$r->user()->id,'name'=>$data['name'],'secret_hash'=>hash('sha256',$secret),'active'=>true,'created_at'=>now(),'updated_at'=>now()]); return back()->with('new_token',$id.'.'.$secret)->with('success','Token dibuat. Salin sekarang; token hanya ditampilkan sekali.'); }
    public function revoke(Request $r,int $id) { $this->owner($r); DB::table('pos_connections')->where('business_id',$r->user()->business_id)->where('id',$id)->update(['active'=>false,'updated_at'=>now()]); return back()->with('success','Koneksi dicabut.'); }
}
