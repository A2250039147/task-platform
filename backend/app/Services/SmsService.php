<?php

namespace App\Services;

use App\Models\SmsVerificationCode;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendVerificationCode(string $phone, string $type = 'register'): array
    {
        try {
            // 检查发送频率限制
            $this->checkRateLimit($phone);

            // 生成验证码
            $verification = SmsVerificationCode::generate($phone, $type);

            // 开发环境直接返回成功
            if (app()->environment('local', 'testing')) {
                Log::info("开发环境短信验证码", [
                    'phone' => $phone,
                    'code' => $verification->code,
                    'type' => $type
                ]);
                
                return [
                    'success' => true,
                    'message' => '发送成功',
                    'code' => $verification->code // 开发环境返回验证码
                ];
            }

            // 生产环境这里调用真实的短信API
            return [
                'success' => true,
                'message' => '发送成功'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function verifyCode(string $phone, string $code, string $type = 'register'): bool
    {
        return SmsVerificationCode::verify($phone, $code, $type);
    }

    private function checkRateLimit(string $phone): void
    {
        // 检查1小时内发送次数
        $hourlyCount = SmsVerificationCode::where('phone', $phone)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($hourlyCount >= 5) {
            throw new \Exception('短信发送过于频繁，请1小时后再试');
        }

        // 检查最后一次发送时间（60秒限制）
        $lastSent = SmsVerificationCode::where('phone', $phone)
            ->latest()
            ->first();

        if ($lastSent && $lastSent->created_at->diffInSeconds(now()) < 60) {
            $waitTime = 60 - $lastSent->created_at->diffInSeconds(now());
            throw new \Exception("请等待{$waitTime}秒后再发送");
        }
    }
}