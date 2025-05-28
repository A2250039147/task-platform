<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserTask;
use App\Models\User;
use App\Models\Platform;
use App\Models\Earning;
use App\Services\VirtualIdService;
use App\Services\Platform\MeeduoService;
use App\Services\Platform\PanelandService;
use App\Services\Platform\YuxshuService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
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
     * 米多平台回调处理
     */
    public function meeduoCallback(Request $request): JsonResponse
    {
        try {
            $params = $request->all();
            
            Log::info('收到米多平台回调', ['params' => $params]);

            // 使用米多服务验证和处理回调
            $result = $this->meeduoService->handleCallback($params);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            $callbackData = $result['data'];
            $virtualId = $callbackData['member_id'];
            $status = $callbackData['status'];
            $rewardAmount = $callbackData['point'];

            // 通过虚拟ID找到真实用户和任务记录
            $processResult = $this->processTaskCompletion(
                'meeduo',
                $virtualId,
                $status,
                $rewardAmount,
                $callbackData
            );

            if (!$processResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $processResult['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => '回调处理成功'
            ]);

        } catch (\Exception $e) {
            Log::error('米多回调处理异常', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '回调处理失败'
            ], 500);
        }
    }

    /**
     * Paneland平台回调处理
     */
    public function panelandCallback(Request $request): JsonResponse
    {
        try {
            $params = $request->all();
            
            Log::info('收到Paneland平台回调', ['params' => $params]);

            // 使用Paneland服务验证和处理回调
            $result = $this->panelandService->handleCallback($params);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            $callbackData = $result['data'];
            $virtualId = $callbackData['member_id'];
            $status = $callbackData['status'];
            $rewardAmount = $callbackData['cpi'] ?? 0;

            // 通过虚拟ID找到真实用户和任务记录
            $processResult = $this->processTaskCompletion(
                'paneland',
                $virtualId,
                $status,
                $rewardAmount,
                $callbackData
            );

            if (!$processResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $processResult['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => '回调处理成功'
            ]);

        } catch (\Exception $e) {
            Log::error('Paneland回调处理异常', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '回调处理失败'
            ], 500);
        }
    }

    /**
     * 鱼小数平台回调处理
     */
    public function yuxshuCallback(Request $request): JsonResponse
    {
        try {
            $params = $request->all();
            
            Log::info('收到鱼小数平台回调', ['params' => $params]);

            // 使用鱼小数服务验证和处理回调
            $result = $this->yuxshuService->handleCallback($params);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            $callbackData = $result['data'];
            $virtualId = $callbackData['member_id'];
            $status = $callbackData['status'];
            
            // 鱼小数回调中没有直接的奖励金额，需要从任务记录中获取
            $rewardAmount = 0;

            // 通过虚拟ID找到真实用户和任务记录
            $processResult = $this->processTaskCompletion(
                'yuxshu',
                $virtualId,
                $status,
                $rewardAmount,
                $callbackData
            );

            if (!$processResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $processResult['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => '回调处理成功'
            ]);

        } catch (\Exception $e) {
            Log::error('鱼小数回调处理异常', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '回调处理失败'
            ], 500);
        }
    }

    /**
     * 处理任务完成核心逻辑
     */
    private function processTaskCompletion(
        string $platformCode,
        string $virtualId,
        string $status,
        float $rewardAmount,
        array $callbackData
    ): array {
        try {
            // 🔥 核心功能：通过虚拟ID找到真实用户
            $realUser = $this->virtualIdService->findRealUserByVirtualId($virtualId);
            
            if (!$realUser) {
                throw new \Exception("未找到虚拟ID对应的真实用户: {$virtualId}");
            }

            // 查找用户任务记录
            $userTask = UserTask::where('virtual_member_id', $virtualId)
                ->where('status', 0) // 只处理进行中的任务
                ->with(['task'])
                ->first();

            if (!$userTask) {
                throw new \Exception("未找到对应的用户任务记录: {$virtualId}");
            }

            // 开始数据库事务
            DB::beginTransaction();

            // 根据不同平台的状态判断任务是否成功
            $isSuccess = $this->isTaskSuccessful($platformCode, $status);
            $finalStatus = $isSuccess ? 1 : 2; // 1:成功 2:失败

            // 如果回调中没有奖励金额，使用任务设定的奖励
            if ($rewardAmount <= 0 && $userTask->task) {
                $rewardAmount = $userTask->task->reward;
            }

            // 更新用户任务状态
            $userTask->update([
                'status' => $finalStatus,
                'reward_amount' => $isSuccess ? $rewardAmount : 0,
                'completed_at' => now()
            ]);

            // 如果任务成功完成，发放奖励
            if ($isSuccess && $rewardAmount > 0) {
                $this->grantReward($realUser, $userTask, $rewardAmount);
            }

            // 记录操作日志
            \App\Models\OperationLog::create([
                'user_id' => $realUser->id,
                'action' => 'task_callback',
                'module' => 'task',
                'resource' => 'user_task',
                'resource_id' => $userTask->id,
                'ip_address' => request()->ip(),
                'request_data' => $callbackData,
                'response_data' => [
                    'virtual_id' => $virtualId,
                    'real_user_id' => $realUser->id,
                    'task_status' => $finalStatus,
                    'reward_amount' => $isSuccess ? $rewardAmount : 0
                ],
                'status' => 'success',
                'risk_level' => 'low'
            ]);

            DB::commit();

            Log::info('任务完成处理成功', [
                'platform' => $platformCode,
                'virtual_id' => $virtualId,
                'real_user_id' => $realUser->id,
                'task_id' => $userTask->task_id,
                'status' => $finalStatus,
                'reward' => $isSuccess ? $rewardAmount : 0
            ]);

            return [
                'success' => true,
                'message' => '任务完成处理成功',
                'data' => [
                    'user_id' => $realUser->id,
                    'task_id' => $userTask->task_id,
                    'status' => $finalStatus,
                    'reward' => $isSuccess ? $rewardAmount : 0
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('任务完成处理失败', [
                'platform' => $platformCode,
                'virtual_id' => $virtualId,
                'error' => $e->getMessage(),
                'callback_data' => $callbackData
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 判断任务是否成功完成
     */
    private function isTaskSuccessful(string $platformCode, string $status): bool
    {
        switch ($platformCode) {
            case 'meeduo':
                // 米多平台: immediate=2表示成功完成
                return $status == '2';
                
            case 'paneland':
                // Paneland平台: Status=C表示完成
                return $status === 'C';
                
            case 'yuxshu':
                // 鱼小数平台: status=1表示正常结束
                return $status === '1';
                
            default:
                return false;
        }
    }

    /**
     * 发放奖励给用户
     */
    private function grantReward(User $user, UserTask $userTask, float $amount): void
    {
        // 更新用户余额
        $user->increment('total_earnings', $amount);
        $user->increment('available_earnings', $amount);

        // 创建收益记录
        Earning::create([
            'user_id' => $user->id,
            'user_task_id' => $userTask->id,
            'type' => 1, // 1:任务完成
            'amount' => $amount,
            'description' => "完成任务：{$userTask->task->title}",
            'status' => 1, // 1:已结算
            'settlement_at' => now()
        ]);

        Log::info('用户奖励发放成功', [
            'user_id' => $user->id,
            'task_id' => $userTask->task_id,
            'amount' => $amount,
            'new_total' => $user->fresh()->total_earnings
        ]);
    }

    /**
     * 测试回调接口（开发调试用）
     */
    public function testCallback(Request $request): JsonResponse
    {
        if (!app()->environment('local', 'testing')) {
            return response()->json(['message' => '仅在开发环境可用'], 403);
        }

        $platform = $request->get('platform');
        $virtualId = $request->get('virtual_id');
        
        $testData = [
            'meeduo' => [
                'memberid' => $virtualId,
                'eventid' => 'test_' . time(),
                'sid' => '999',
                'immediate' => '2',
                'point' => '5.00',
                'totalpoint' => '10.00',
                'sign' => 'test_sign'
            ],
            'paneland' => [
                'Uid' => $virtualId,
                'PNO' => 'TEST_' . time(),
                'Status' => 'C',
                'CPI' => '8.00',
                'Sign' => 'test_sign'
            ],
            'yuxshu' => [
                'memberId' => $virtualId,
                'status' => '1',
                'signStr' => 'test_sign'
            ]
        ];

        if (!isset($testData[$platform])) {
            return response()->json(['message' => '不支持的平台'], 400);
        }

        $request->merge($testData[$platform]);

        switch ($platform) {
            case 'meeduo':
                return $this->meeduoCallback($request);
            case 'paneland':
                return $this->panelandCallback($request);
            case 'yuxshu':
                return $this->yuxshuCallback($request);
        }

        return response()->json(['message' => '未知平台'], 400);
    }
}