<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'sync_mode',
        'price_ratio',
        'api_config',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'price_ratio' => 'decimal:4',
        'api_config' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    // 关联任务
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // 关联虚拟用户ID
    public function virtualUserIds()
    {
        return $this->hasMany(VirtualUserId::class);
    }

    // 检查是否为自动同步模式
    public function isAutoSync(): bool
    {
        return $this->sync_mode === 'auto';
    }
}