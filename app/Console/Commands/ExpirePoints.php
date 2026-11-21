<?php

namespace App\Console\Commands;

use App\Services\PointService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePoints extends Command
{
    protected $signature = 'points:expire';
    protected $description = 'Tutup lot poin yang sudah melewati masa berlaku';
    public function handle(PointService $points): int
    {
        $total = 0;
        DB::table('members')->whereIn('id', DB::table('point_lots')->select('member_id')->where('expires_at', '<=', now())->where('remaining_points', '>', 0))->orderBy('id')->chunkById(100, function ($members) use ($points, &$total) {
            foreach ($members as $member) $total += DB::transaction(function () use ($points, $member) { $locked = DB::table('members')->where('id', $member->id)->lockForUpdate()->first(); return $points->expireMember($locked); });
        });
        $this->info("$total poin kedaluwarsa diproses."); return self::SUCCESS;
    }
}
