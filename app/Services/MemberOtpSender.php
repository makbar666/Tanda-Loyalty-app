<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MemberOtpSender
{
    public function send(string $phone, string $code): void
    {
        $driver = config('services.member_otp.driver');
        if ($driver === 'log' && app()->environment(['local', 'testing'])) {
            Log::info('Kode masuk portal member', ['phone' => $phone, 'code' => $code]);
            return;
        }

        if ($driver !== 'whatsapp') {
            throw new RuntimeException('Pengiriman kode portal belum dikonfigurasi.');
        }

        $config = config('services.member_otp.whatsapp');
        if (blank($config['phone_number_id']) || blank($config['token']) || blank($config['template'])) {
            throw new RuntimeException('Konfigurasi WhatsApp portal belum lengkap.');
        }

        Http::withToken($config['token'])->timeout(10)->post(
            'https://graph.facebook.com/'.$config['version'].'/'.$config['phone_number_id'].'/messages',
            [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $config['template'],
                    'language' => ['code' => $config['language']],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [['type' => 'text', 'text' => $code]],
                    ]],
                ],
            ]
        )->throw();
    }
}
