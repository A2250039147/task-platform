<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SmsVerificationCode extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'type',
        'is_used',
        'expires_at',
        'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    /**
     * 生成验证码
     */
    public static function generate(string $phone, string $type = 'register', int $minutes = 5): self
    {
        // 先清除该手机号该类型的过期验证码
        self::where('phone', $phone)
            ->where('type', $type)
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                      ->orWhere('is_used', 1);
            })->delete();

        return self::create([
            'phone' => $phone,
            'code' => str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT),
            'type' => $type,
            'expires_at' => now()->addMinutes($minutes),
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * 验证验证码
     */
    public static function verify(string $phone, string $code, string $type = 'register'): bool
    {
        $verification = self::where('phone', $phone)
            ->where('code', $code)
            ->where('type', $type)
            ->where('is_used', 0)
            ->where('expires_at', '>', now())
            ->first();

        if ($verification) {
            $verification->update(['is_used' => 1]);
            return true;
        }

        return false;
    }
}