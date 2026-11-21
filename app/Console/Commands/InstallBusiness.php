<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallBusiness extends Command
{
    protected $signature = 'loyalty:install {--name=} {--outlet=} {--email=} {--password=}';
    protected $description = 'Buat bisnis, outlet awal, owner, dan aturan poin contoh';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nama bisnis');
        $outletName = $this->option('outlet') ?: $this->ask('Nama outlet pertama');
        $email = $this->option('email') ?: $this->ask('Email owner');
        $password = $this->option('password') ?: $this->secret('Kata sandi owner (minimal 8 karakter)');
        if (!$name || !$outletName || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 8) { $this->error('Data tidak valid.'); return self::FAILURE; }
        if (User::where('email', $email)->exists()) { $this->error('Email sudah digunakan.'); return self::FAILURE; }
        DB::transaction(function () use ($name, $outletName, $email, $password) {
            $business = DB::table('businesses')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $outlet = DB::table('outlets')->insertGetId(['business_id' => $business, 'name' => $outletName, 'code' => 'UTM', 'created_at' => now(), 'updated_at' => now()]);
            $owner = User::create(['business_id' => $business, 'outlet_id' => null, 'role' => 'owner', 'name' => 'Owner', 'email' => $email, 'password' => $password]);
            DB::table('point_rules')->insert(['business_id' => $business, 'version' => 1, 'earn_step' => 10000, 'earn_points' => 1, 'minimum_spend' => 10000, 'redeem_value' => 100, 'minimum_redeem' => 10, 'maximum_redeem_percent' => 50, 'approval_threshold' => 100, 'valid_days' => 365, 'effective_at' => now(), 'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->info('Bisnis dibuat. Masuk dengan email owner yang tadi diberikan.'); return self::SUCCESS;
    }
}
