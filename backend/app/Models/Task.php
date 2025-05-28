<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'platform_id',
        'platform_task_id',
        'title',
        'description',
        'original_price',
        'reward',
        'commission',
        'duration',
        'is_manual',
        'source_url',
        'link_id',
        'status',
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'reward' => 'decimal:2',
        'commission' => 'decimal:2',
        'is_manual' => 'boolean',
    ];

    /**
     * 关联平台
     */
    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * 关联用户任务记录
     */
    public function userTasks()
    {
        return $this->hasMany(UserTask::class);
    }

    /**
     * 获取已完成的任务数量
     */
    public function getCompletedCountAttribute(): int
    {
        return $this->userTasks()->where('status', 1)->count();
    }

    /**
     * 获取参与用户数量
     */
    public function getParticipantCountAttribute(): int
    {
        return $this->userTasks()->distinct('user_id')->count('user_id');
    }

    /**
     * 检查任务是否可用
     */
    public function isAvailable(): bool
    {
        return $this->status == 1;
    }

    /**
     * 获取任务参与链接
     */
    public function getParticipationUrl(string $memberId): string
    {
        if ($this->is_manual) {
            // 手动任务直接返回原始链接
            return $this->source_url;
        }

        // 自动同步任务需要构建带参数的链接
        $platform = $this->platform;
        $baseUrl = $platform->api_config['base_url'] ?? '';
        
        return match($platform->code) {
            'meeduo' => "{$baseUrl}/task/{$this->platform_task_id}?member_id={$memberId}",
            'paneland' => "{$baseUrl}/participate/{$this->platform_task_id}?uid={$memberId}",
            'yuxshu' => $this->source_url, // 鱼小数使用原始链接
            default => $this->source_url
        };
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            0 => '禁用',
            1 => '启用',
            default => '未知'
        };
    }

    /**
     * 范围查询：可用任务
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 范围查询：按平台筛选
     */
    public function scopeByPlatform($query, int $platformId)
    {
        return $query->where('platform_id', $platformId);
    }

    /**
     * 范围查询：手动任务
     */
    public function scopeManual($query)
    {
        return $query->where('is_manual', 1);
    }

    /**
     * 范围查询：自动同步任务
     */
    public function scopeAutoSync($query)
    {
        return $query->where('is_manual', 0);
    }
}