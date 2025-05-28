<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 获取当前认证用户
        $user = $request->user();
        
        // 检查用户是否已认证
        if (!$user) {
            return $this->unauthorizedResponse('用户未认证');
        }
        
        // 检查用户是否为管理员
        // 方式1: 检查用户表中的admin字段（如果有的话）
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }
        
        // 方式2: 检查特定的用户ID（超级管理员）
        $adminUserIds = config('auth.admin_user_ids', [1]); // 可以在config中配置
        if (in_array($user->id, $adminUserIds)) {
            return $next($request);
        }
        
        // 方式3: 检查用户角色（如果你有角色系统）
        // if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
        //     return $next($request);
        // }
        
        // 方式4: 简单的邮箱检查（临时方案）
        $adminEmails = config('auth.admin_emails', ['admin@example.com']);
        if (isset($user->email) && in_array($user->email, $adminEmails)) {
            return $next($request);
        }
        
        // 如果都不满足，返回权限不足
        return $this->forbiddenResponse('权限不足，需要管理员权限');
    }
    
    /**
     * 返回未认证响应
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'code' => 401,
            'message' => $message,
            'data' => null
        ], 401);
    }
    
    /**
     * 返回权限不足响应
     */
    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'code' => 403,
            'message' => $message,
            'data' => null
        ], 403);
    }
}