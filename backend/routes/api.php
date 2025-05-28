<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PrivilegeUserController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\CallbackController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 公开API路由（无需认证）
Route::prefix('auth')->group(function () {
    // 发送注册验证码 - 限制1分钟5次
    Route::post('send-register-code', [AuthController::class, 'sendRegisterCode'])
        ->middleware('throttle:5,1');
    
    // 用户注册 - 限制1分钟3次
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:3,1');
    
    // 用户登录 - 限制1分钟10次
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
});

// 平台回调路由（无需认证，但需要签名验证）
Route::prefix('callback')->group(function () {
    // 米多平台回调
    Route::any('meeduo', [CallbackController::class, 'meeduoCallback']);
    
    // Paneland平台回调
    Route::any('paneland', [CallbackController::class, 'panelandCallback']);
    
    // 鱼小数平台回调
    Route::any('yuxshu', [CallbackController::class, 'yuxshuCallback']);
});

// 需要认证的API路由
Route::middleware(['auth:sanctum', 'operation.log'])->group(function () {
    
    // 认证相关
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // 任务相关路由（用户端）
    Route::prefix('tasks')->group(function () {
        // 获取任务列表
        Route::get('/', [TaskController::class, 'index']);
        
        // 获取任务详情
        Route::get('{taskId}', [TaskController::class, 'show']);
        
        // 参与任务
        Route::post('{taskId}/participate', [TaskController::class, 'participate']);
        
        // 获取用户任务记录
        Route::get('user/records', [TaskController::class, 'userTasks']);
    });

    // 管理员路由（需要管理员权限）
    Route::middleware('admin')->prefix('admin')->group(function () {
        
        // 特权用户管理
        Route::prefix('privilege-users')->group(function () {
            // 获取用户列表（支持搜索、筛选、分页）
            Route::get('/', [PrivilegeUserController::class, 'index']);
            
            // 获取特权用户统计信息
            Route::get('stats', [PrivilegeUserController::class, 'getPrivilegeStats']);
            
            // 获取单个用户详情
            Route::get('{userId}', [PrivilegeUserController::class, 'show']);
            
            // 切换单个用户特权状态
            Route::put('{userId}/toggle-privilege', [PrivilegeUserController::class, 'togglePrivilege']);
            
            // 批量设置特权状态
            Route::put('batch-toggle-privilege', [PrivilegeUserController::class, 'batchTogglePrivilege']);
            
            // 重置用户虚拟ID
            Route::delete('{userId}/virtual-ids', [PrivilegeUserController::class, 'resetVirtualIds']);
        });

        // 任务管理
        Route::prefix('tasks')->group(function () {
            // 获取所有任务列表（管理员视图）
            Route::get('/', [TaskController::class, 'adminIndex']);
            
            // 同步平台任务
            Route::post('sync', [TaskController::class, 'syncTasks']);
            
            // 手动任务管理（主要针对鱼小数）
            Route::prefix('manual')->group(function () {
                // 创建手动任务
                Route::post('/', [TaskController::class, 'createManualTask']);
                
                // 批量创建任务
                Route::post('batch', [TaskController::class, 'batchCreateTasks']);
                
                // 更新手动任务
                Route::put('{taskId}', [TaskController::class, 'updateManualTask']);
                
                // 删除手动任务
                Route::delete('{taskId}', [TaskController::class, 'deleteManualTask']);
            });
            
            // 任务统计和分析
            Route::get('stats', [TaskController::class, 'getTaskStats']);
            
            // 平台配置管理
            Route::prefix('platforms')->group(function () {
                Route::get('/', [TaskController::class, 'getPlatforms']);
                Route::put('{platformId}', [TaskController::class, 'updatePlatform']);
                Route::post('{platformId}/test', [TaskController::class, 'testPlatformConnection']);
            });
        });

        // 用户任务记录管理
        Route::prefix('user-tasks')->group(function () {
            // 获取所有用户任务记录
            Route::get('/', [TaskController::class, 'adminUserTasks']);
            
            // 获取任务完成统计
            Route::get('completion-stats', [TaskController::class, 'getCompletionStats']);
            
            // 手动标记任务状态
            Route::put('{userTaskId}/status', [TaskController::class, 'updateUserTaskStatus']);
        });
    });
});