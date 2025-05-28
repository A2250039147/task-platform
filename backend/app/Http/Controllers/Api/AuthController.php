<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * 发送注册验证码
     */
    public function sendRegisterCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'regex:/^1[3-9]\d{9}$/',
                'unique:users,phone'
            ],
        ], [
            'phone.required' => '手机号不能为空',
            'phone.regex' => '手机号格式不正确',
            'phone.unique' => '手机号已被注册',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $result = $this->smsService->sendVerificationCode($request->phone, 'register');

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? '验证码发送成功' : $result['message'],
                'data' => app()->environment('local') ? ['code' => $result['code'] ?? null] : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429);
        }
    }

    /**
     * 用户注册
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'regex:/^1[3-9]\d{9}$/',
                'unique:users,phone'
            ],
            'code' => 'required|string|size:6',
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'unique:users,username',
            ],
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // 验证短信验证码
        if (!$this->smsService->verifyCode($request->phone, $request->code, 'register')) {
            return response()->json([
                'success' => false,
                'message' => '验证码错误或已过期',
            ], 422);
        }

        try {
            // 创建用户
            $user = User::create([
                'phone' => $request->phone,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'phone_verified_at' => now(),
                'last_login_ip' => $request->ip(),
                'status' => 1,
            ]);

            // 生成访问令牌
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => '注册成功',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'member_id' => $user->member_id,
                        'phone' => $user->phone,
                        'username' => $user->username,
                        'total_earnings' => number_format($user->total_earnings, 2),
                        'available_earnings' => number_format($user->available_earnings, 2),
                    ],
                    'token' => $token,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '注册失败，请稍后重试',
            ], 500);
        }
    }

    /**
     * 用户登录
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if (!Auth::attempt(['phone' => $request->phone, 'password' => $request->password])) {
            return response()->json([
                'success' => false,
                'message' => '账号或密码错误',
            ], 401);
        }

        $user = Auth::user();

        // 检查用户状态
        if ($user->status == 0) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => '账号已被禁用',
            ], 403);
        }

        // 更新最后登录IP
        $user->update(['last_login_ip' => $request->ip()]);

        // 生成访问令牌
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => '登录成功',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'member_id' => $user->member_id,
                    'phone' => $user->phone,
                    'username' => $user->username,
                    'total_earnings' => number_format($user->total_earnings, 2),
                    'available_earnings' => number_format($user->available_earnings, 2),
                    'is_privileged' => $user->is_privileged,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * 用户登出
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => '登出成功',
        ]);
    }

    /**
     * 获取当前用户信息
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'member_id' => $user->member_id,
                'phone' => $user->phone,
                'username' => $user->username,
                'total_earnings' => number_format($user->total_earnings, 2),
                'available_earnings' => number_format($user->available_earnings, 2),
                'is_privileged' => $user->is_privileged,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}