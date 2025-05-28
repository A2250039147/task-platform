<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'member_id',
        'phone',
        'username',
        'password',
        'total_earnings',
        'available_earnings',
        'frozen_earnings',
        'is_privileged',
        'privilege_level',
        'phone_verified_at',
        'last_login_ip',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'total_earnings' => 'decimal:2',
            'available_earnings' => 'decimal:2',
            'frozen_earnings' => 'decimal:2',
            'is_privileged' => 'boolean',
        ];
    }

    /**
     * 模型启动时的事件
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            if (empty($user->member_id)) {
                $user->member_id = self::generateMemberId();
            }
        });
    }

    /**
     * 生成唯一用户ID
     */
    public static function generateMemberId(): string
    {
        do {
            $memberId = 'U' . time() . rand(1000, 9999);
        } while (self::where('member_id', $memberId)->exists());
        
        return $memberId;
    }

    /**
     * 检查手机号是否已验证
     */
    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    /**
     * 标记手机号已验证
     */
    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * 检查是否为特权用户
     */
    public function isPrivileged(): bool
    {
        return $this->is_privileged == 1;
    }

    /**
     * 用户的虚拟ID
     */
    public function virtualIds()
    {
        return $this->hasMany(VirtualUserId::class, 'real_user_id');
    }

    /**
     * 获取用户在指定平台的虚拟ID
     */
    public function getVirtualIdsForPlatform(int $platformId)
    {
        return $this->virtualIds()->where('platform_id', $platformId)->get();
    }
}