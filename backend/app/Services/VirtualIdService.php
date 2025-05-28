<?php

namespace App\Services;

use App\Models\User;
use App\Models\Platform;
use App\Models\VirtualUserId;
use App\Models\UserTask;
use Illuminate\Support\Str;

class VirtualIdService
{
    /**
     * 为用户参与任务动态生成虚拟ID（新方案：按需生成）
     */
    public function generateTaskVirtualId(User $user, Platform $platform, int $taskId): string
    {
        // 检查用户类型
        if (!$user->isPrivileged()) {
            // 普通用户：为每个平台生成一个固定的适配ID
            return $this->getOrCreateRealUserPlatformId($user, $platform);
        }
        
        // 特权用户：每次动态生成新的唯一ID
        return $this->generateUniquePrivilegedId($user, $platform, $taskId);
    }

    /**
     * 为普通用户获取或创建平台适配ID（一对一映射）
     */
    private function getOrCreateRealUserPlatformId(User $user, Platform $platform): string
    {
        // 查找是否已有该平台的ID
        $existingId = VirtualUserId::where('real_user_id', $user->id)
            ->where('platform_id', $platform->id)
            ->where('is_privileged_user', false)
            ->first();
            
        if ($existingId) {
            return $existingId->virtual_member_id;
        }
        
        // 生成新的平台适配ID
        $platformId = $this->generatePlatformAdaptedId($platform);
        
        // 保存映射关系
        VirtualUserId::create([
            'real_user_id' => $user->id,
            'virtual_member_id' => $platformId,
            'platform_id' => $platform->id,
            'id_format' => $this->getPlatformFormat($platform),
            'is_privileged_user' => false,
            'is_active' => true,
            'task_id' => null, // 普通用户不关联具体任务
        ]);
        
        return $platformId;
    }
    
    /**
     * 为特权用户生成唯一的虚拟ID
     */
    private function generateUniquePrivilegedId(User $user, Platform $platform, int $taskId): string
    {
        $maxAttempts = 20;
        $attempts = 0;
        
        do {
            $virtualId = $this->generatePlatformAdaptedId($platform);
            $attempts++;
            
            if ($attempts > $maxAttempts) {
                throw new \Exception('生成唯一ID失败，请重试');
            }
            
        } while (VirtualUserId::where('virtual_member_id', $virtualId)->exists());
        
        // 保存记录
        VirtualUserId::create([
            'real_user_id' => $user->id,
            'virtual_member_id' => $virtualId,
            'platform_id' => $platform->id,
            'id_format' => $this->getPlatformFormat($platform),
            'is_privileged_user' => true,
            'task_id' => $taskId,
            'is_active' => true,
        ]);
        
        return $virtualId;
    }
    
    /**
     * 根据平台要求生成适配的ID
     */
    private function generatePlatformAdaptedId(Platform $platform): string
    {
        return match($platform->code) {
            // 米多：英文字母或数字，32位以内
            'meeduo' => 'MD' . $this->generateAlphaNumeric(8),
            
            // Paneland：支持字母数字组合
            'paneland' => 'PL_' . $this->generateAlphaNumeric(6) . '_' . substr(time(), -4),
            
            // 鱼小数：支持字母数字和下划线
            'yuxshu' => 'YX_' . $this->generateNumeric(10),
            
            default => 'USER_' . $this->generateAlphaNumeric(8),
        };
    }
    
    /**
     * 获取平台ID格式描述
     */
    private function getPlatformFormat(Platform $platform): string
    {
        return match($platform->code) {
            'meeduo' => 'MD{alphanumeric_8}',
            'paneland' => 'PL_{alphanumeric_6}_{timestamp_4}',
            'yuxshu' => 'YX_{numeric_10}',
            default => 'USER_{alphanumeric_8}',
        };
    }
    
    /**
     * 生成字母数字组合
     */
    private function generateAlphaNumeric(int $length): string
    {
        return Str::upper(Str::random($length));
    }
    
    /**
     * 生成纯数字
     */
    private function generateNumeric(int $length): string
    {
        $number = '';
        for ($i = 0; $i < $length; $i++) {
            $number .= rand(0, 9);
        }
        return $number;
    }
    
    /**
     * 根据虚拟ID查找真实用户
     */
    public function findRealUserByVirtualId(string $virtualId): ?User
    {
        $virtualRecord = VirtualUserId::where('virtual_member_id', $virtualId)->first();
        return $virtualRecord ? $virtualRecord->realUser : null;
    }
    
    /**
     * 记录虚拟ID使用
     */
    public function recordVirtualIdUsage(string $virtualId): void
    {
        VirtualUserId::where('virtual_member_id', $virtualId)
            ->increment('usage_count');
            
        VirtualUserId::where('virtual_member_id', $virtualId)
            ->update(['last_used_at' => now()]);
    }
    
    /**
     * 获取用户在平台的所有虚拟ID
     */
    public function getUserVirtualIds(User $user, Platform $platform): array
    {
        return VirtualUserId::where('real_user_id', $user->id)
            ->where('platform_id', $platform->id)
            ->pluck('virtual_member_id')
            ->toArray();
    }

    /**
     * 获取用户虚拟ID统计信息（新增方法 - 解决控制器500错误）
     */
    public function getUserStats(int $userId): array
    {
        try {
            // 基础统计
            $totalVirtualIds = VirtualUserId::where('real_user_id', $userId)->count();
            $activeVirtualIds = VirtualUserId::where('real_user_id', $userId)
                ->where('is_active', true)
                ->count();
            $totalUsage = VirtualUserId::where('real_user_id', $userId)
                ->sum('usage_count') ?? 0;
            $lastUsedAt = VirtualUserId::where('real_user_id', $userId)
                ->whereNotNull('last_used_at')
                ->max('last_used_at');

            return [
                'total_virtual_ids' => $totalVirtualIds,
                'active_virtual_ids' => $activeVirtualIds,
                'total_usage' => $totalUsage,
                'last_used_at' => $lastUsedAt,
            ];
        } catch (\Exception $e) {
            // 出错时返回默认值，避免页面崩溃
            return [
                'total_virtual_ids' => 0,
                'active_virtual_ids' => 0,
                'total_usage' => 0,
                'last_used_at' => null,
            ];
        }
    }
}