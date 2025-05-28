<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VirtualIdService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PrivilegeUserController extends Controller
{
    protected VirtualIdService $virtualIdService;

    public function __construct(VirtualIdService $virtualIdService)
    {
        $this->virtualIdService = $virtualIdService;
    }

    /**
     * 获取用户列表（分页）
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // 搜索条件
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->filled('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }

        if ($request->filled('is_privileged')) {
            $query->where('is_privileged', $request->boolean('is_privileged'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 排序
        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // 分页
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        // 为每个用户添加虚拟ID统计信息
        $users->getCollection()->transform(function ($user) {
            $user->virtual_id_stats = $this->virtualIdService->getUserStats($user->id);
            return $user;
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => $users
        ]);
    }

    /**
     * 获取单个用户详情
     */
    public function show(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        // 获取用户的虚拟ID统计
        $virtualIdStats = $this->virtualIdService->getUserStats($userId);
        
        // 获取用户的虚拟ID列表
        $virtualIds = $this->virtualIdService->getUserVirtualIds($userId);

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'user' => $user,
                'virtual_id_stats' => $virtualIdStats,
                'virtual_ids' => $virtualIds
            ]
        ]);
    }

    /**
     * 切换用户特权状态
     */
    public function togglePrivilege(Request $request, int $userId): JsonResponse
    {
        try {
            $request->validate([
                'is_privileged' => 'required|boolean'
            ]);

            $user = User::findOrFail($userId);
            $oldStatus = $user->is_privileged;
            $newStatus = $request->boolean('is_privileged');

            // 更新特权状态
            $user->update([
                'is_privileged' => $newStatus,
                'privilege_level' => $newStatus ? 1 : 0 // 基础特权等级
            ]);

            // 记录操作日志
            \App\Models\OperationLog::create([
                'admin_id' => auth()->id(), // 假设使用管理员认证
                'action' => 'toggle_privilege',
                'module' => 'user_management',
                'resource' => 'user',
                'resource_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => $request->all(),
                'response_data' => [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ],
                'status' => 'success',
                'risk_level' => 'high' // 特权操作为高风险
            ]);

            return response()->json([
                'code' => 200,
                'message' => $newStatus ? '用户已设为特权用户' : '用户特权已取消',
                'data' => [
                    'user_id' => $userId,
                    'is_privileged' => $newStatus,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '参数验证失败',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // 记录错误日志
            \App\Models\OperationLog::create([
                'admin_id' => auth()->id(),
                'action' => 'toggle_privilege',
                'module' => 'user_management',
                'resource' => 'user',
                'resource_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => $request->all(),
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'risk_level' => 'high'
            ]);

            return response()->json([
                'code' => 500,
                'message' => '操作失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量设置特权状态
     */
    public function batchTogglePrivilege(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'is_privileged' => 'required|boolean'
            ]);

            $userIds = $request->user_ids;
            $isPrivileged = $request->boolean('is_privileged');
            
            // 批量更新
            $affectedRows = User::whereIn('id', $userIds)->update([
                'is_privileged' => $isPrivileged,
                'privilege_level' => $isPrivileged ? 1 : 0
            ]);

            // 记录操作日志
            \App\Models\OperationLog::create([
                'admin_id' => auth()->id(),
                'action' => 'batch_toggle_privilege',
                'module' => 'user_management',
                'resource' => 'user',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => $request->all(),
                'response_data' => [
                    'affected_rows' => $affectedRows,
                    'user_ids' => $userIds,
                    'new_status' => $isPrivileged
                ],
                'status' => 'success',
                'risk_level' => 'high'
            ]);

            return response()->json([
                'code' => 200,
                'message' => "成功更新了 {$affectedRows} 个用户的特权状态",
                'data' => [
                    'affected_rows' => $affectedRows,
                    'is_privileged' => $isPrivileged
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '参数验证失败',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '批量操作失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取特权用户统计信息
     */
    public function getPrivilegeStats(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'privileged_users' => User::where('is_privileged', true)->count(),
            'normal_users' => User::where('is_privileged', false)->count(),
            'active_privileged_users' => User::where('is_privileged', true)
                ->where('status', 1)
                ->count(),
            'privilege_rate' => 0
        ];

        // 计算特权用户比例
        if ($stats['total_users'] > 0) {
            $stats['privilege_rate'] = round(($stats['privileged_users'] / $stats['total_users']) * 100, 2);
        }

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => $stats
        ]);
    }

    /**
     * 重置用户虚拟ID（清空所有虚拟ID记录）
     */
    public function resetVirtualIds(Request $request, int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            
            // 删除用户的所有虚拟ID记录
            \App\Models\VirtualUserId::where('real_user_id', $userId)->delete();

            // 记录操作日志
            \App\Models\OperationLog::create([
                'admin_id' => auth()->id(),
                'action' => 'reset_virtual_ids',
                'module' => 'user_management',
                'resource' => 'user',
                'resource_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'success',
                'risk_level' => 'medium'
            ]);

            return response()->json([
                'code' => 200,
                'message' => '用户虚拟ID已重置',
                'data' => [
                    'user_id' => $userId,
                    'phone' => $user->phone
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '重置失败：' . $e->getMessage()
            ], 500);
        }
    }
}