<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserTask;
use App\Models\Platform;
use App\Services\VirtualIdService;
use App\Services\Platform\MeeduoService;
use App\Services\Platform\PanelandService;
use App\Services\Platform\YuxshuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    protected VirtualIdService $virtualIdService;
    protected MeeduoService $meeduoService;
    protected PanelandService $panelandService;
    protected YuxshuService $yuxshuService;

    public function __construct(
        VirtualIdService $virtualIdService,
        MeeduoService $meeduoService,
        PanelandService $panelandService,
        YuxshuService $yuxshuService
    ) {
        $this->virtualIdService = $virtualIdService;
        $this->meeduoService = $meeduoService;
        $this->panelandService = $panelandService;
        $this->yuxshuService = $yuxshuService;
    }

    /**
     * 获取任务列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Task::with('platform')
                ->where('status', 1)
                ->orderBy('created_at', 'desc');

            // 平台筛选
            if ($request->filled('platform_id')) {
                $query->where('platform_id', $request->platform_id);
            }

            // 任务类型筛选
            if ($request->filled('is_manual')) {
                $query->where('is_manual', $request->boolean('is_manual'));
            }

            // 分页
            $perPage = $request->get('per_page', 20);
            $tasks = $query->paginate($perPage);

            // 为每个任务添加用户参与状态
            $tasks->getCollection()->transform(function ($task) use ($user) {
                // 检查用户是否已参与此任务
                $userTask = UserTask::where('user_id', $user->id)
                    ->where('task_id', $task->id)
                    ->first();

                $task->user_participated = $userTask ? true : false;
                $task->user_task_status = $userTask ? $userTask->status : null;
                $task->can_participate = $this->canUserParticipate($user, $task);

                return $task;
            });

            return response()->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $tasks
            ]);

        } catch (\Exception $e) {
            Log::error('获取任务列表失败', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'code' => 500,
                'message' => '获取任务列表失败'
            ], 500);
        }
    }

    /**
     * 参与任务
     */
    public function participate(Request $request, int $taskId): JsonResponse
    {
        try {
            $user = Auth::user();
            $task = Task::with('platform')->findOrFail($taskId);

            // 检查用户是否可以参与任务
            if (!$this->canUserParticipate($user, $task)) {
                return response()->json([
                    'code' => 400,
                    'message' => '您已参与过此任务或不符合参与条件'
                ], 400);
            }

            // 开始数据库事务
            DB::beginTransaction();

            // 生成虚拟ID（核心功能）
            $virtualId = $this->virtualIdService->generateTaskVirtualId(
                $user, 
                $task->platform, 
                $taskId
            );

            // 检查IP防重复（如果不是特权用户）
            if (!$user->is_privileged) {
                $ipExists = UserTask::where('task_id', $taskId)
                    ->where('ip_address', $request->ip())
                    ->exists();

                if ($ipExists) {
                    DB::rollBack();
                    return response()->json([
                        'code' => 400,
                        'message' => '该IP已参与过此任务'
                    ], 400);
                }
            }

            // 创建用户任务记录
            $userTask = UserTask::create([
                'user_id' => $user->id,
                'task_id' => $taskId,
                'virtual_member_id' => $virtualId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 0, // 进行中
                'started_at' => now()
            ]);

            // 生成参与链接
            $participateUrl = $this->generateParticipateUrl($task, $virtualId);

            // 记录操作日志
            \App\Models\OperationLog::create([
                'user_id' => $user->id,
                'action' => 'task_participate',
                'module' => 'task',
                'resource' => 'task',
                'resource_id' => $taskId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => [
                    'task_id' => $taskId,
                    'virtual_id' => $virtualId
                ],
                'status' => 'success',
                'risk_level' => $user->is_privileged ? 'medium' : 'low'
            ]);

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => '参与成功',
                'data' => [
                    'user_task_id' => $userTask->id,
                    'virtual_id' => $virtualId,
                    'participate_url' => $participateUrl,
                    'task' => $task
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('参与任务失败', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'task_id' => $taskId
            ]);

            return response()->json([
                'code' => 500,
                'message' => '参与任务失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取用户任务记录
     */
    public function userTasks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = UserTask::with(['task.platform'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // 状态筛选
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // 平台筛选
            if ($request->filled('platform_id')) {
                $query->whereHas('task', function($q) use ($request) {
                    $q->where('platform_id', $request->platform_id);
                });
            }

            // 分页
            $perPage = $request->get('per_page', 20);
            $userTasks = $query->paginate($perPage);

            return response()->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $userTasks
            ]);

        } catch (\Exception $e) {
            Log::error('获取用户任务记录失败', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'code' => 500,
                'message' => '获取任务记录失败'
            ], 500);
        }
    }

    /**
     * 获取任务详情
     */
    public function show(int $taskId): JsonResponse
    {
        try {
            $user = Auth::user();
            $task = Task::with('platform')->findOrFail($taskId);

            // 获取用户参与记录
            $userTask = UserTask::where('user_id', $user->id)
                ->where('task_id', $taskId)
                ->first();

            $task->user_participated = $userTask ? true : false;
            $task->user_task_status = $userTask ? $userTask->status : null;
            $task->user_task = $userTask;
            $task->can_participate = $this->canUserParticipate($user, $task);

            return response()->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $task
            ]);

        } catch (\Exception $e) {
            Log::error('获取任务详情失败', [
                'error' => $e->getMessage(),
                'task_id' => $taskId
            ]);

            return response()->json([
                'code' => 500,
                'message' => '获取任务详情失败'
            ], 500);
        }
    }

    /**
     * 检查用户是否可以参与任务
     */
    private function canUserParticipate($user, $task): bool
    {
        // 特权用户可以重复参与
        if ($user->is_privileged) {
            return true;
        }

        // 普通用户检查是否已参与
        $existingTask = UserTask::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->exists();

        return !$existingTask;
    }

    /**
     * 生成参与链接
     */
    private function generateParticipateUrl($task, $virtualId): string
    {
        $platform = $task->platform;
        
        switch ($platform->code) {
            case 'meeduo':
                // 米多平台链接格式
                return "http://www.meeduo.com/go.mdq?uid={$platform->api_config['uid']}&acode={$task->platform_task_id}&pm1={$virtualId}";
                
            case 'paneland':
                // Paneland平台链接格式
                $baseUrl = str_replace('[uid]', $virtualId, $task->source_url);
                return $baseUrl;
                
            case 'yuxshu':
                // 鱼小数平台链接格式
                $dealerCode = $platform->api_config['dealer_code'] ?? 'A001';
                return "{$task->source_url}?dealerCode={$dealerCode}&userInfo1={$virtualId}";
                
            default:
                return $task->source_url ?? '';
        }
    }

    /**
     * 同步平台任务（定时任务调用）
     */
    public function syncTasks(): JsonResponse
    {
        try {
            $platforms = Platform::where('sync_mode', 'auto')
                ->where('is_active', 1)
                ->get();

            $syncResults = [];

            foreach ($platforms as $platform) {
                try {
                    $count = 0;
                    
                    switch ($platform->code) {
                        case 'meeduo':
                            $count = $this->meeduoService->syncTasks($platform);
                            break;
                            
                        case 'paneland':
                            $count = $this->panelandService->syncTasks($platform);
                            break;
                            
                        // 鱼小数是手动模式，不同步
                    }
                    
                    $syncResults[] = [
                        'platform' => $platform->name,
                        'synced_count' => $count,
                        'status' => 'success'
                    ];
                    
                } catch (\Exception $e) {
                    $syncResults[] = [
                        'platform' => $platform->name,
                        'error' => $e->getMessage(),
                        'status' => 'failed'
                    ];
                    
                    Log::error("同步{$platform->name}任务失败", [
                        'platform_id' => $platform->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'code' => 200,
                'message' => '同步完成',
                'data' => $syncResults
            ]);

        } catch (\Exception $e) {
            Log::error('同步任务失败', ['error' => $e->getMessage()]);

            return response()->json([
                'code' => 500,
                'message' => '同步任务失败'
            ], 500);
        }
    }
}