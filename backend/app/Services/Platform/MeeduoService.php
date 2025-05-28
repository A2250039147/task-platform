<?php

namespace App\Services\Platform;

use App\Models\Task;
use App\Models\Platform;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeeduoService
{
    /**
     * 同步米多平台任务
     */
    public function syncTasks(Platform $platform): int
    {
        try {
            $apiConfig = $platform->api_config;
            $sid = $apiConfig['sid'] ?? '';
            $key = $apiConfig['key'] ?? '';
            
            if (empty($sid) || empty($key)) {
                throw new \Exception('米多平台配置不完整');
            }

            // 构建API请求参数
            $params = [
                'sid' => $sid,
                'memberid' => '' // 空值获取所有任务
            ];
            
            // 生成签名
            $urlParams = http_build_query($params);
            $hash = md5($urlParams . $key);
            $params['hash'] = $hash;

            // 调用米多API
            $response = Http::timeout(30)->get('http://www.meeduo.com/mbdataapi.mdq', $params);
            
            if (!$response->successful()) {
                throw new \Exception('API请求失败：' . $response->status());
            }

            $data = $response->json();
            
            if ($data['status'] != 1) {
                throw new \Exception('API返回错误：' . ($data['message'] ?? '未知错误'));
            }

            $syncedCount = 0;
            
            foreach ($data['data'] as $taskData) {
                try {
                    // 检查任务是否已存在
                    $existingTask = Task::where('platform_id', $platform->id)
                        ->where('platform_task_id', $taskData['acode'])
                        ->first();

                    if ($existingTask) {
                        // 更新现有任务
                        $existingTask->update([
                            'title' => $taskData['title'],
                            'original_price' => $taskData['money'],
                            'reward' => $this->calculateReward($taskData['money'], $platform->price_ratio),
                            'commission' => $this->calculateCommission($taskData['money']),
                            'source_url' => $taskData['link'],
                            'updated_at' => now()
                        ]);
                    } else {
                        // 创建新任务
                        Task::create([
                            'platform_id' => $platform->id,
                            'platform_task_id' => $taskData['acode'],
                            'title' => $taskData['title'],
                            'description' => $taskData['note'] ?? '',
                            'original_price' => $taskData['money'],
                            'reward' => $this->calculateReward($taskData['money'], $platform->price_ratio),
                            'commission' => $this->calculateCommission($taskData['money']),
                            'duration' => $this->parseDuration($taskData['time'] ?? ''),
                            'source_url' => $taskData['link'],
                            'is_manual' => 0,
                            'status' => 1
                        ]);
                        
                        $syncedCount++;
                    }
                    
                } catch (\Exception $e) {
                    Log::warning('同步米多任务项失败', [
                        'task_code' => $taskData['acode'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 更新平台最后同步时间
            $platform->update(['last_sync_at' => now()]);

            Log::info('米多平台任务同步完成', [
                'platform_id' => $platform->id,
                'synced_count' => $syncedCount,
                'total_received' => count($data['data'])
            ]);

            return $syncedCount;

        } catch (\Exception $e) {
            Log::error('同步米多平台任务失败', [
                'platform_id' => $platform->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * 查询用户完成记录
     */
    public function getUserCompletionRecords(Platform $platform, string $memberId = '', int $days = 7): array
    {
        try {
            $apiConfig = $platform->api_config;
            $sid = $apiConfig['sid'] ?? '';
            $key = $apiConfig['key'] ?? '';

            $params = [
                'sid' => $sid,
                'memberid' => $memberId,
                'st' => 1, // 查询成功状态
                'sdate' => now()->subDays($days)->format('Y-m-d'),
                'edate' => now()->format('Y-m-d')
            ];

            // 生成签名
            $signStr = $params['sid'] . $params['memberid'] . $params['st'] . 
                      $params['sdate'] . $params['edate'] . $key;
            $params['hash'] = md5($signStr);

            $response = Http::timeout(30)->get('http://www.meeduo.com/mbrecordapi.mdq', $params);
            
            if (!$response->successful()) {
                throw new \Exception('查询记录API请求失败');
            }

            $data = $response->json();
            
            if ($data['status'] != 1) {
                throw new \Exception('API返回错误：' . ($data['message'] ?? '未知错误'));
            }

            return $data['data'] ?? [];

        } catch (\Exception $e) {
            Log::error('查询米多用户记录失败', [
                'platform_id' => $platform->id,
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * 验证回调签名
     */
    public function verifyCallback(array $params, string $key): bool
    {
        $expectedSign = md5($params['memberid'] . $params['eventid'] . $params['sid'] . $key);
        return strtolower($expectedSign) === strtolower($params['sign'] ?? '');
    }

    /**
     * 处理米多平台回调
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
            $requiredParams = ['memberid', 'eventid', 'sid', 'immediate', 'sign'];
            foreach ($requiredParams as $param) {
                if (!isset($params[$param])) {
                    throw new \Exception("缺少必要参数：{$param}");
                }
            }

            // 获取平台配置
            $platform = Platform::where('code', 'meeduo')->first();
            if (!$platform) {
                throw new \Exception('未找到米多平台配置');
            }

            // 验证签名
            $key = $platform->api_config['key'] ?? '';
            if (!$this->verifyCallback($params, $key)) {
                throw new \Exception('签名验证失败');
            }

            $result['success'] = true;
            $result['message'] = '回调处理成功';
            $result['data'] = [
                'member_id' => $params['memberid'],
                'event_id' => $params['eventid'],
                'status' => $params['immediate'],
                'point' => $params['point'] ?? 0,
                'total_point' => $params['totalpoint'] ?? 0
            ];

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            
            Log::error('米多回调处理失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * 计算用户奖励
     */
    private function calculateReward(float $originalPrice, float $priceRatio): float
    {
        return round($originalPrice * $priceRatio, 2);
    }

    /**
     * 计算平台佣金
     */
    private function calculateCommission(float $originalPrice): float
    {
        // 佣金 = 原价 - 用户奖励，这里假设平台保留20%
        return round($originalPrice * 0.2, 2);
    }

    /**
     * 解析任务时长
     */
    private function parseDuration(string $timeStr): int
    {
        // 解析如 "10M", "5分钟" 等格式
        if (preg_match('/(\d+)/', $timeStr, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
}