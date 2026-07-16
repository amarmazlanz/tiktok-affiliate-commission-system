<?php

namespace App\Http\Controllers;

use App\Models\TiktokShopToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiktokOAuthController extends Controller
{
    public function callback(Request $request)
    {
        $code = $request->query('code') ?? $request->query('auth_code');

        if (! $code) {
            Log::error('TikTok OAuth callback: no code received', $request->all());

            return redirect()->route('admin.dashboard')
                ->with('error', 'TikTok authorization gagal - tiada code diterima.');
        }

        $response = Http::get('https://auth.tiktok-shops.com/api/v2/token/get', [
            'app_key' => config('services.tiktok_shop.key'),
            'app_secret' => config('services.tiktok_shop.secret'),
            'auth_code' => $code,
            'grant_type' => 'authorized_code',
        ]);

        $json = $response->json();

        if (! $response->successful() || ($json['code'] ?? -1) !== 0) {
            Log::error('TikTok token exchange failed', ['response' => $json]);

            return redirect()->route('admin.dashboard')
                ->with('error', 'TikTok authorization gagal. Sila cuba lagi.');
        }

        $data = $json['data'];

        TiktokShopToken::updateOrCreate(
            ['open_id' => $data['open_id'] ?? 'my_shop'],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'seller_name' => $data['seller_name'] ?? null,
                'region' => $data['seller_base_region'] ?? 'MY',
                'expires_at' => isset($data['access_token_expire_in'])
                    ? now()->addSeconds((int) $data['access_token_expire_in'])
                    : now()->addHours(24),
                'refresh_token_expires_at' => isset($data['refresh_token_expire_in'])
                    ? now()->addSeconds((int) $data['refresh_token_expire_in'])
                    : now()->addDays(30),
            ]
        );

        return redirect()->route('admin.dashboard')
            ->with('success', 'TikTok Shop berjaya disambungkan! Access token telah disimpan.');
    }
}
