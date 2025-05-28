<?php

namespace App\Services\Platform;

use App\Models\Task;
use App\Models\Platform;
use Illuminate\Support\Facades\Log;

class YuxshuService
{
    /**
     * 创建手动任务
     */
    public function createManualTask(Platform $platform, array $taskData): Task
    {
        try {
            // 验证必要字段
            $requiredFields = ['title', 'source_url', 'reward'];
            foreach ($requiredFields as $field) {
                if (empty($taskData[$field])) {
                    throw new \Exception("缺少必要字段：{$field}");
                }
            }

            // 从URL中提取任务ID
            $platformTaskId = $this->extractTaskIdFromUrl($taskData['source_url']);
            
            if (empty($platformTaskId)) {
                throw new \Exception('无法从URL中提取任务ID');
            }

            // 检查任务是否已存在
            $existingTask = Task::where('platform_id', $platform->id)
                ->where('platform_task_id', $platformTaskId)
                ->first();

            if ($existingTask) {
                throw new \Exception('该任务已存在');
            }

            // 创建任务
            $task = Task::create([
                'platform_id' => $platform->id,
                'platform_task_id' => $platformTaskId,
                'title' => $taskData['title'],
                'description' => $taskData['description'] ?? '',
                'original_price' => $taskData['reward'], // 鱼小数手动设置原价
                'reward' => $taskData['reward'],
                'commission' => $this->calculateCommission($taskData['reward']),
                'duration' => $taskData['duration'] ?? 0,
                'source_url' => $taskData['source_url'],
                'is_manual' => 1,
                'status' => $taskData['status'] ?? 1
            ]);

            Log::info('鱼小数手动任务创建成功', [
                'task_id' => $task->id,
                'platform_task_id' => $platformTaskId,
                'title' => $taskData['title']
            ]);

            return $task;

        } catch (\Exception $e) {
            Log::error('创建鱼小数手动任务失败', [
                'platform_id' => $platform->id,
                'task_data' => $taskData,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * 批量创建任务
     */
    public function batchCreateTasks(Platform $platform, array $tasksData): array
    {
        $results = [
            'success_count' => 0,
            'failed_count' => 0,
            'errors' => []
        ];

        foreach ($tasksData as $index => $taskData) {
            try {
                $this->createManualTask($platform, $taskData);
                $results['success_count']++;
                
            } catch (\Exception $e) {
                $results['failed_count']++;
                $results['errors'][] = [
                    'index' => $index,
                    'title' => $taskData['title'] ?? '未知',
                    'error' => $e->getMessage()
                ];
            }
        }

        Log::info('鱼小数批量创建任务完成', $results);

        return $results;
    }

    /**
     * 更新手动任务
     */
    public function updateManualTask(int $taskId, array $updateData): Task
    {
        try {
            $task = Task::findOrFail($taskId);
            
            // 只允许更新特定字段
            $allowedFields = ['title', 'description', 'reward', 'duration', 'status'];
            $filteredData = array_intersect_key($updateData, array_flip($allowedFields));
            
            // 如果更新了奖励，重新计算佣金
            if (isset($filteredData['reward'])) {
                $filteredData['commission'] = $this->calculateCommission($filteredData['reward']);
                $filteredData['original_price'] = $filteredData['reward'];
            }

            $task->update($filteredData);

            Log::info('鱼小数手动任务更新成功', [
                'task_id' => $taskId,
                'updated_fields' => array_keys($filteredData)
            ]);

            return $task->fresh();

        } catch (\Exception $e) {
            Log::error('更新鱼小数手动任务失败', [
                'task_id' => $taskId,
                'update_data' => $updateData,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * 删除手动任务
     */
    public function deleteManualTask(int $taskId): bool
    {
        try {
            $task = Task::findOrFail($taskId);
            
            if (!$task->is_manual) {
                throw new \Exception('只能删除手动创建的任务');
            }

            $task->delete();

            Log::info('鱼小数手动任务删除成功', [
                'task_id' => $taskId,
                'title' => $task->title
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('删除鱼小数手动任务失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * 验证回调签名
     */
    public function verifyCallback(array $params, string $secret): bool
    {
        $memberId = $params['memberId'] ?? '';
        $status = $params['status'] ?? '';
        
        $expectedSign = md5($memberId . $status . $secret);
        return strtolower($expectedSign) === strtolower($params['signStr'] ?? '');
    }

    /**
     * 处理鱼小数平台回调
     */
    public function handleCallback(array $params): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'data' => []
        ];

        try {
            // 基本参数验证
            $requiredParams = ['memberId', 'status', 'signStr'];
            foreach ($requiredParams as $param) {
                if (!isset($params[$param])) {
                    throw new \Exception("缺少必要参数：{$param}");
                }
            }

            // 获取平台配置
            $platform = Platform::where('code', 'yuxshu')->first();
            if (!$platform) {
                throw new \Exception('未找到鱼小数平台配置');
            }

            // 验证签名
            $secret = $platform->api_config['secret'] ?? '';
            if (!$this->verifyCallback($params, $secret)) {
                throw new \Exception('签名验证失败');
            }

            $result['success'] = true;
            $result['message'] = '回调处理成功';
            $result['data'] = [
                'member_id' => $params['memberId'],
                'status' => $params['status'],
                'status_text' => $this->getStatusText($params['status'])
            ];

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            
            Log::error('鱼小数回调处理失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * 解析URL中的任务ID
     */
    private function extractTaskIdFromUrl(string $url): string
    {
        // 从鱼小数URL中提取任务ID
        // 例如: https://www.yuxshu.cn/s/hmeP6SeZF8?dealerCode=A001&userInfo1=M001
        if (preg_match('/\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // 如果无法提取，生成一个基于URL的唯一ID
        return 'manual_' . substr(md5($url), 0, 8);
    }

    /**
     * 获取状态文本
     */
    private function getStatusText(string $status): string
    {
        $statusMap = [
            '1' => '正常结束',
            '3' => '超配额',
            '5' => '甄别'
        ];

        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 计算平台佣金
     */
    private function calculateCommission(float $reward): float
    {
        // 鱼小数手动任务，假设平台保留15%的佣金
        return round($reward * 0.15, 2);
    }

    /**
     * 验证任务URL格式
     */
    public function validateTaskUrl(string $url): bool
    {
        return preg_match('/^https:\/\/www\.yuxshu\.cn\/s\/[a-zA-Z0-9]+/', $url);
    }

    /**
     * 生成参与链接
     */
    public function generateParticipateUrl(string $baseUrl, string $dealerCode, string $virtualId): string
    {
        $params = [
            'dealerCode' => $dealerCode,
            'userInfo1' => $virtualId
        ];

        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
        return $baseUrl . $separator . http_build_query($params);
    }
}