<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class OperationLog extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'action',
        'module',
        'resource',
        'resource_id',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'request_data',
        'response_data',
        'execution_time',
        'status',
        'error_message',
        'risk_level',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'execution_time' => 'integer',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 记录操作日志
     */
    public static function log(string $action, string $module, array $data = []): self
    {
        $request = request();
        $user = auth()->user();
        $admin = auth('admin')->user();

        return self::create([
            'user_id' => $user?->id,
            'admin_id' => $admin?->id,
            'action' => $action,
            'module' => $module,
            'resource' => $data['resource'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $request->all(),
            'response_data' => $data['response'] ?? null,
            'execution_time' => $data['execution_time'] ?? null,
            'status' => $data['status'] ?? 'success',
            'error_message' => $data['error'] ?? null,
            'risk_level' => self::assessRiskLevel($action, $module, $data),
        ]);
    }

    /**
     * 记录用户操作
     */
    public static function logUserAction(string $action, array $data = []): self
    {
        return self::log($action, 'user', $data);
    }

    /**
     * 记录管理员操作
     */
    public static function logAdminAction(string $action, array $data = []): self
    {
        return self::log($action, 'admin', $data);
    }

    /**
     * 记录系统操作
     */
    public static function logSystemAction(string $action, array $data = []): self
    {
        return self::log($action, 'system', $data);
    }

    /**
     * 评估风险等级
     */
    private static function assessRiskLevel(string $action, string $module, array $data): string
    {
        // 高风险操作
        $highRiskActions = [
            'withdrawal_apply', 'password_change', 'phone_change',
            'admin_login', 'privilege_grant', 'config_update',
            'user_ban', 'task_delete'
        ];

        if (in_array($action, $highRiskActions)) {
            return 'high';
        }

        // 中风险操作
        $mediumRiskActions = [
            'task_participate', 'earnings_withdraw', 'profile_update',
            'task_create', 'user_status_change'
        ];

        if (in_array($action, $mediumRiskActions)) {
            return 'medium';
        }

        // 错误状态自动升级为中风险
        if (($data['status'] ?? 'success') === 'error') {
            return 'medium';
        }

        return 'low';
    }

    /**
     * 获取风险操作列表
     */
    public static function getHighRiskOperations(int $hours = 24)
    {
        return self::where('risk_level', 'high')
            ->where('created_at', '>', now()->subHours($hours))
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 获取用户操作统计
     */
    public static function getUserOperationStats(int $userId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_operations' => self::where('user_id', $userId)
                ->where('created_at', '>', $startDate)
                ->count(),
            'failed_operations' => self::where('user_id', $userId)
                ->where('status', 'error')
                ->where('created_at', '>', $startDate)
                ->count(),
            'high_risk_operations' => self::where('user_id', $userId)
                ->where('risk_level', 'high')
                ->where('created_at', '>', $startDate)
                ->count(),
            'unique_ips' => self::where('user_id', $userId)
                ->where('created_at', '>', $startDate)
                ->distinct('ip_address')
                ->count('ip_address'),
        ];
    }

    /**
     * 检测异常行为
     */
    public static function detectAnomalousActivity(int $userId): array
    {
        $warnings = [];
        $now = now();

        // 检查短时间内大量操作
        $recentOpsCount = self::where('user_id', $userId)
            ->where('created_at', '>', $now->subMinutes(10))
            ->count();

        if ($recentOpsCount > 50) {
            $warnings[] = [
                'type' => 'high_frequency',
                'message' => "10分钟内操作{$recentOpsCount}次，疑似异常",
                'severity' => 'high'
            ];
        }

        // 检查多IP登录
        $recentIPs = self::where('user_id', $userId)
            ->where('created_at', '>', $now->subHour())
            ->distinct('ip_address')
            ->count('ip_address');

        if ($recentIPs > 3) {
            $warnings[] = [
                'type' => 'multiple_ips',
                'message' => "1小时内使用{$recentIPs}个不同IP，疑似账号被盗",
                'severity' => 'medium'
            ];
        }

        // 检查高风险操作频率
        $highRiskCount = self::where('user_id', $userId)
            ->where('risk_level', 'high')
            ->where('created_at', '>', $now->subDay())
            ->count();

        if ($highRiskCount > 5) {
            $warnings[] = [
                'type' => 'high_risk_frequency',
                'message' => "24小时内{$highRiskCount}次高风险操作",
                'severity' => 'medium'
            ];
        }

        return $warnings;
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'success' => '成功',
            'error' => '失败',
            'warning' => '警告',
            default => '未知'
        };
    }

    /**
     * 获取风险等级文本
     */
    public function getRiskLevelTextAttribute(): string
    {
        return match($this->risk_level) {
            'low' => '低风险',
            'medium' => '中风险',
            'high' => '高风险',
            default => '未知'
        };
    }

    /**
     * 范围查询：按风险等级筛选
     */
    public function scopeByRiskLevel($query, string $level)
    {
        return $query->where('risk_level', $level);
    }

    /**
     * 范围查询：按状态筛选
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 范围查询：按模块筛选
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}