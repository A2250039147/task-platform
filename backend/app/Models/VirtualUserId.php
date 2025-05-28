<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualUserId extends Model
{
    use HasFactory;

    protected $fillable = [
        'real_user_id',
        'virtual_member_id',
        'platform_id',
        'id_format',
        'is_active',
        'usage_count',
        'last_used_at',
        'is_privileged_user',
        'task_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_privileged_user' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    // 关联到真实用户
    public function realUser()
    {
        return $this->belongsTo(User::class, 'real_user_id');
    }

    // 关联到平台
    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    // 关联到任务
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    // 记录使用次数
    public function recordUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }
}