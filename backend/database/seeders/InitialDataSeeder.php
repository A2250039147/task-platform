<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 插入系统配置
        DB::table('system_configs')->insert([
            // 基础配置
            [
                'category' => 'basic',
                'key_name' => 'site_name',
                'value' => '任务聚合平台',
                'description' => '网站名称',
                'type' => 'string',
                'is_public' => 1,
                'sort_order' => 1
            ],
            [
                'category' => 'basic',
                'key_name' => 'site_description',
                'value' => '专业的任务聚合平台',
                'description' => '网站描述',
                'type' => 'string',
                'is_public' => 1,
                'sort_order' => 2
            ],
            [
                'category' => 'basic',
                'key_name' => 'customer_service_qq',
                'value' => '123456789',
                'description' => '客服QQ',
                'type' => 'string',
                'is_public' => 1,
                'sort_order' => 3
            ],
            [
                'category' => 'basic',
                'key_name' => 'customer_service_wechat',
                'value' => 'service123',
                'description' => '客服微信',
                'type' => 'string',
                'is_public' => 1,
                'sort_order' => 4
            ],

            // 提现配置
            [
                'category' => 'withdrawal',
                'key_name' => 'min_amount',
                'value' => '10.00',
                'description' => '最低提现金额',
                'type' => 'number',
                'is_public' => 1,
                'sort_order' => 1
            ],
            [
                'category' => 'withdrawal',
                'key_name' => 'max_amount',
                'value' => '10000.00',
                'description' => '最高提现金额',
                'type' => 'number',
                'is_public' => 1,
                'sort_order' => 2
            ],
            [
                'category' => 'withdrawal',
                'key_name' => 'fee_rate',
                'value' => '0.02',
                'description' => '提现手续费率',
                'type' => 'number',
                'is_public' => 0,
                'sort_order' => 3
            ],
            [
                'category' => 'withdrawal',
                'key_name' => 'daily_limit',
                'value' => '5000.00',
                'description' => '每日提现限额',
                'type' => 'number',
                'is_public' => 1,
                'sort_order' => 4
            ],
            [
                'category' => 'withdrawal',
                'key_name' => 'processing_time',
                'value' => '1-3个工作日',
                'description' => '提现处理时间',
                'type' => 'string',
                'is_public' => 1,
                'sort_order' => 5
            ],

            // 短信配置
            [
                'category' => 'sms',
                'key_name' => 'provider',
                'value' => 'aliyun',
                'description' => '短信服务商',
                'type' => 'string',
                'is_public' => 0,
                'sort_order' => 1
            ],
            [
                'category' => 'sms',
                'key_name' => 'daily_limit',
                'value' => '10',
                'description' => '每日短信发送限制',
                'type' => 'number',
                'is_public' => 0,
                'sort_order' => 2
            ],
            [
                'category' => 'sms',
                'key_name' => 'template_register',
                'value' => 'SMS_123456',
                'description' => '注册验证码模板',
                'type' => 'string',
                'is_public' => 0,
                'sort_order' => 3
            ],

            // 防作弊配置
            [
                'category' => 'fraud',
                'key_name' => 'enable_ip_check',
                'value' => 'true',
                'description' => '是否启用IP防重复检查',
                'type' => 'boolean',
                'is_public' => 0,
                'sort_order' => 1
            ],
            [
                'category' => 'fraud',
                'key_name' => 'enable_phone_register_only',
                'value' => 'true',
                'description' => '是否只允许手机号注册',
                'type' => 'boolean',
                'is_public' => 0,
                'sort_order' => 2
            ],
            [
                'category' => 'fraud',
                'key_name' => 'max_virtual_ids_per_user',
                'value' => '50',
                'description' => '每用户最大虚拟ID数',
                'type' => 'number',
                'is_public' => 0,
                'sort_order' => 3
            ]
        ]);

        // 插入消息模板
        DB::table('message_templates')->insert([
            [
                'code' => 'task_completed',
                'name' => '任务完成通知',
                'type' => 'system',
                'title' => '任务完成',
                'content' => '恭喜您成功完成任务"{{task_title}}"，获得奖励{{reward}}元！',
                'variables' => json_encode(['task_title' => '任务标题', 'reward' => '奖励金额']),
                'is_active' => 1
            ],
            [
                'code' => 'withdrawal_approved',
                'name' => '提现审核通过',
                'type' => 'system',
                'title' => '提现审核通过',
                'content' => '您的提现申请已审核通过，金额{{amount}}元将在{{processing_time}}内到账。',
                'variables' => json_encode(['amount' => '提现金额', 'processing_time' => '处理时间']),
                'is_active' => 1
            ],
            [
                'code' => 'withdrawal_rejected',
                'name' => '提现审核拒绝',
                'type' => 'system',
                'title' => '提现审核被拒绝',
                'content' => '很抱歉，您的提现申请被拒绝。原因：{{reason}}',
                'variables' => json_encode(['reason' => '拒绝原因']),
                'is_active' => 1
            ],
            [
                'code' => 'withdrawal_completed',
                'name' => '提现完成',
                'type' => 'sms',
                'title' => '提现完成通知',
                'content' => '您的提现{{amount}}元已完成，请查收。【任务平台】',
                'variables' => json_encode(['amount' => '提现金额']),
                'is_active' => 1
            ],
            [
                'code' => 'system_maintenance',
                'name' => '系统维护通知',
                'type' => 'system',
                'title' => '系统维护通知',
                'content' => '系统将于{{start_time}}进行维护，预计持续{{duration}}，维护期间暂停服务。',
                'variables' => json_encode(['start_time' => '开始时间', 'duration' => '持续时间']),
                'is_active' => 1
            ]
        ]);

        // 插入平台数据
        DB::table('platforms')->insert([
            [
                'code' => 'meeduo',
                'name' => '米多',
                'sync_mode' => 'auto',
                'price_ratio' => 0.8000,
                'api_config' => json_encode([
                    'base_url' => 'http://www.meeduo.com',
                    'sid' => '',
                    'key' => ''
                ]),
                'is_active' => 1
            ],
            [
                'code' => 'paneland',
                'name' => 'Paneland',
                'sync_mode' => 'auto',
                'price_ratio' => 0.7500,
                'api_config' => json_encode([
                    'base_url' => 'https://partner.paneland.com',
                    'mid' => '',
                    'key' => ''
                ]),
                'is_active' => 1
            ],
            [
                'code' => 'yuxshu',
                'name' => '鱼小数',
                'sync_mode' => 'manual',
                'price_ratio' => 1.0000,
                'api_config' => json_encode([
                    'dealer_code' => '',
                    'secret' => ''
                ]),
                'is_active' => 1
            ]
        ]);

        // 插入默认管理员
        DB::table('admins')->insert([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'name' => '超级管理员',
            'role' => 'super_admin',
            'status' => 1
        ]);

        // 插入提现配置默认值
        DB::table('withdrawal_configs')->insert([
            'min_amount' => 10.00,
            'max_amount' => 10000.00,
            'fee_rate' => 0.0200,
            'daily_limit' => 5000.00,
            'monthly_limit' => 50000.00,
            'processing_days' => '1-3',
            'is_active' => 1
        ]);
    }
}