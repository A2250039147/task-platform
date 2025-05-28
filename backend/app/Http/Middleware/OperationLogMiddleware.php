<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\OperationLog;
use Illuminate\Support\Facades\Auth;

class OperationLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000); // 转为毫秒

        // 记录操作日志
        $this->logOperation($request, $response, $executionTime);

        return $response;
    }

    private function logOperation($request, $response, $executionTime)
    {
        try {
            $user = Auth::user();
            $statusCode = $response->getStatusCode();
            
            $status = match (true) {
                $statusCode >= 200 && $statusCode < 300 => 'success',
                $statusCode >= 400 && $statusCode < 500 => 'warning', 
                $statusCode >= 500 => 'error',
                default => 'success'
            };

            $action = $this->getActionFromRoute($request);

            OperationLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'module' => 'api',
                'method' => $request->getMethod(),
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => $this->filterSensitiveData($request->all()),
                'execution_time' => $executionTime,
                'status' => $status,
                'risk_level' => $this->assessRiskLevel($action),
            ]);

        } catch (\Exception $e) {
            \Log::error('操作日志记录失败: ' . $e->getMessage());
        }
    }

    private function getActionFromRoute($request): string
    {
        $route = $request->route();
        if ($route && $route->getActionName()) {
            $action = class_basename($route->getActionName());
            return str_replace('@', '_', $action);
        }
        
        return $request->getMethod() . '_' . str_replace('/', '_', trim($request->getPathInfo(), '/'));
    }

    private function filterSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'code'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***已过滤***';
            }
        }
        
        return $data;
    }

    private function assessRiskLevel(string $action): string
    {
        $highRiskActions = [
            'AuthController_register',
            'AuthController_login', 
            'AuthController_logout',
        ];

        if (in_array($action, $highRiskActions)) {
            return 'high';
        }

        $mediumRiskActions = [
            'AuthController_sendRegisterCode'
        ];

        if (in_array($action, $mediumRiskActions)) {
            return 'medium';
        }

        return 'low';
    }
}