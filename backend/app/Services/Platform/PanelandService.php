<?php

namespace App\Services\Platform;

use App\Models\Task;
use App\Models\Platform;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PanelandService
{
    /**
     * 同步Paneland平台任务
     */
    public function syncTasks(Platform $platform): int
    {
        try {
            $apiConfig = $platform->api_config;
            $mid = $apiConfig['mid'] ?? '';
            
            if (empty($mid)) {
                throw new \Exception('Paneland平台配置不完整');
            }

            // 调用Paneland API获取任务列表
            $response = Http::timeout(30)->get('https://partner.paneland.com/MediaJson.php', [
                'Mid' => $mid,
                'offset' => 0,
                'limit' => 100 // 每次获取100个任务
            ]);
            
            if (!$response->successful()) {
                throw new \Exception('API请求失败：' . $response->status());
            }

            $tasks = $response->json();
            
            if (empty($tasks)) {
                Log::info('Paneland平台暂无可用任务');
                return 0;
            }

            $syncedCount = 0;
            
            foreach ($tasks as $taskData) {
                try {
                    // 检查任务是否已存在
                    $existingTask = Task::where('platform_id', $platform->id)
                        ->where('platform_task_id', $taskData['PNO'])
                        ->first();

                    if ($existingTask) {
                        // 更新现有任务
                        $existingTask->update([
                            'title' => $taskData['Title'],
                            'original_price' => $taskData['CPI'],
                            'reward' => $this->calculateReward($taskData['CPI'], $platform->price_ratio),
                            'commission' => $this->calculateCommission($taskData['CPI']),
                            'duration' => $this->parseLOI($taskData['LOI']),
                            'source_url' => $taskData['URL'],
                            'updated_at' => now()
                        ]);
                    } else {
                        // 创建新任务
                        Task::create([
                            'platform_id' => $platform->id,
                            'platform_task_id' => $taskData['PNO'],
                            'title' => $taskData['Title'],
                            'description' => $this->buildDescription($taskData),
                            'original_price' => $taskData['CPI'],
                            'reward' => $this->calculateReward($taskData['CPI'], $platform->price_ratio),
                            'commission' => $this->calculateCommission($taskData['CPI']),
                            'duration' => $this->parseLOI($taskData['LOI']),
                            'source_url' => $taskData['URL'],
                            'is_manual' => 0,
                            'status' => 1
                        ]);
                        
                        $syncedCount++;
                    }
                    
                } catch (\Exception $e) {
                    Log::warning('同步Paneland任务项失败', [
                        'task_pno' => $taskData['PNO'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 更新平台最后同步时间
            $platform->update(['last_sync_at' => now()]);

            Log::info('Paneland平台任务同步完成', [
                'platform_id' => $platform->id,
                'synced_count' => $syncedCount,
                'total_received' => count($tasks)
            ]);

            return $syncedCount;

        } catch (\Exception $e) {
            Log::error('同步Paneland平台任务失败', [
                'platform_id' => $platform->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * 验证回调签名
     */
    public function verifyCallback(array $params, string $key): bool
    {
        $signString = $params['Uid'] . $params['PNO'] . $params['Status'] . $key;
        $expectedSign = md5($signString);
        return strtolower($expectedSign) === strtolower($params['Sign'] ?? '');
    }

    /**
     * 处理Paneland平台回调
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
            $requiredParams = ['Uid', 'PNO', 'Status', 'Sign'];
            foreach ($requiredParams as $param) {
                if (!isset($params[$param])) {
                    throw new \Exception("缺少必要参数：{$param}");
                }
            }

            // 获取平台配置
            $platform = Platform::where('code', 'paneland')->first();
            if (!$platform) {
                throw new \Exception('未找到Paneland平台配置');
            }

            // 验证签名
            $key = $platform->api_config['key'] ?? '';
            if (!$this->verifyCallback($params, $key)) {
                throw new \Exception('签名验证失败');
            }

            $result['success'] = true;
            $result['message'] = '回调处理成功';
            $result['data'] = [
                'member_id' => $params['Uid'],
                'project_no' => $params['PNO'],
                'status' => $params['Status'],
                'status_text' => $this->getStatusText($params['Status']),
                'cpi' => $params['CPI'] ?? 0
            ];

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            
            Log::error('Paneland回调处理失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * 获取属性列表
     */
    public function getAttributeList(Platform $platform, string $market = 'China'): array
    {
        try {
            $apiConfig = $platform->api_config;
            $mid = $apiConfig['mid'] ?? '';

            $response = Http::timeout(30)->get('https://partner.paneland.com/task.php/getSelectData/getSelectList', [
                'market' => $market,
                'Mid' => $mid,
                'datatype' => 'addval',
                'itype' => 1
            ]);

            if (!$response->successful()) {
                throw new \Exception('获取属性列表失败');
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('获取Paneland属性列表失败', [
                'platform_id' => $platform->id,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * 构建任务描述
     */
    private function buildDescription(array $taskData): string
    {
        $description = [];
        
        if (!empty($taskData['Market'])) {
            $description[] = "市场：{$taskData['Market']}";
        }
        
        if (!empty($taskData['SS'])) {
            $description[] = "样本数量：{$taskData['SS']}";
        }
        
        if (!empty($taskData['deviceType'])) {
            $description[] = "设备要求：{$taskData['deviceType']}";
        }
        
        if (!empty($taskData['IR'])) {
            $description[] = "出现率：{$taskData['IR']}%";
        }

        // 添加抽样条件
        if (!empty($taskData['Sample']) && is_array($taskData['Sample'])) {
            $sample = $taskData['Sample'][0] ?? [];
            $conditions = [];
            
            if (!empty($sample['Gender'])) {
                $conditions[] = "性别要求";
            }
            if (!empty($sample['ageStart']) || !empty($sample['ageend'])) {
                $conditions[] = "年龄要求";
            }
            if (!empty($sample['Educational'])) {
                $conditions[] = "教育程度要求";
            }
            
            if (!empty($conditions)) {
                $description[] = "筛选条件：" . implode('、', $conditions);
            }
        }

        return implode('；', $description);
    }

    /**
     * 解析LOI（问卷时长）
     */
    private function parseLOI(string $loi): int
    {
        // 解析如 "10M", "15分钟" 等格式
        if (preg_match('/(\d+)/', $loi, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    /**
     * 获取状态文本
     */
    private function getStatusText(string $status): string
    {
        $statusMap = [
            'C' => '完成',
            'S' => '不符合要求',
            'Q' => '配额已满',
            'D' => '重复答题',
            'O' => '错误定位',
            'T' => '答题过快',
            'P' => '项目暂停',
            'E' => '项目关闭'
        ];

        return $statusMap[$status] ?? '未知状态';
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
        // 佣金 = 原价 - 用户奖励，这里假设平台保留25%
        return round($originalPrice * 0.25, 2);
    }
}