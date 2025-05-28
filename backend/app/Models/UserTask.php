<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Exception;

class UserTask extends Model
{
    protected $fillable = [
        'user_id',
        'task_id',
        'virtual_member_id',
        'ip_address',
        'user_agent',
        'status',
        'reward_amount',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reward_amount' => 'decimal:2',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联任务
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * 检查用户是否可以参与任务（防重复逻辑）
     */
    public static function canParticipate(int $userId, int $taskId, string $ipAddress): array
    {
        // 1. 检查用户是否已参与此任务
        $userExists = self::where('user_id', $userId)
            ->where('task_id', $taskId)
            ->exists();
            
        if ($userExists) {
            return [
                'can_participate' => false,
                'reason' => '您已经参与过此任务',
                'code' => 'USER_ALREADY_PARTICIPATED'
            ];
        }

        // 2. 检查IP是否已参与此任务
        $ipExists = self::where('task_id', $taskId)
            ->where('ip_address', $ipAddress)
            ->exists();
            
        if ($ipExists) {
            return [
                'can_participate' => false,
                'reason' => '当前网络环境已有用户参与过此任务',
                'code' => 'IP_ALREADY_PARTICIPATED'
            ];
        }

        return [
            'can_participate' => true,
            'reason' => '可以参与',
            'code' => 'OK'
        ];
    }

    /**
     * 创建任务参与记录
     */
    public static function createParticipation(array $data): self
    {
        // 再次检查防重复（双重保险）
        $check = self::canParticipate(
            $data['user_id'], 
            $data['task_id'], 
            $data['ip_address']
        );
        
        if (!$check['can_participate']) {
            throw new Exception($check['reason']);
        }

        return self::create([
            'user_id' => $data['user_id'],
            'task_id' => $data['task_id'],
            'virtual_member_id' => $data['virtual_member_id'] ?? null,
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'] ?? null,
            'status' => 0, // 进行中
            'started_at' => now(),
        ]);
    }

    /**
     * 标记任务完成
     */
    public function markCompleted(float $rewardAmount): bool
    {
        return $this->update([
            'status' => 1,
            'reward_amount' => $rewardAmount,
            'completed_at' => now(),
        ]);
    }

    /**
     * 标记任务失败
     */
    public function markFailed(): bool
    {
        return $this->update([
            'status' => 2,
            'completed_at' => now(),
        ]);
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            0 => '进行中',
            1 => '已完成',
            2 => '失败',
            default => '未知'
        };
    }
}