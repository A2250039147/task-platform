<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 消息模板表
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('模板代码');
            $table->string('name', 100)->comment('模板名称');
            $table->enum('type', ['system', 'sms', 'email'])->comment('消息类型');
            $table->string('title', 200)->nullable()->comment('消息标题');
            $table->text('content')->comment('消息内容(支持变量)');
            $table->json('variables')->nullable()->comment('可用变量说明');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        // 系统消息表
        Schema::create('system_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('接收用户ID，NULL表示全体用户');
            $table->string('title', 200);
            $table->text('content');
            $table->enum('type', ['system', 'task', 'withdrawal', 'promotion'])->default('system');
            $table->tinyInteger('is_read')->default(0)->comment('是否已读');
            $table->tinyInteger('is_global')->default(0)->comment('是否全局消息');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read']);
            $table->index(['is_global', 'expires_at']);
        });

        // 短信发送记录表
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('template_code', 50);
            $table->text('content');
            $table->json('variables')->nullable()->comment('模板变量');
            $table->tinyInteger('status')->default(0)->comment('0:发送中 1:成功 2:失败');
            $table->string('provider', 20)->nullable()->comment('短信服务商');
            $table->text('provider_response')->nullable()->comment('服务商响应');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['phone', 'created_at']);
            $table->index('status');
        });

        // 消息推送设置表
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('task_completion')->default(1)->comment('任务完成通知');
            $table->tinyInteger('withdrawal_status')->default(1)->comment('提现状态通知');
            $table->tinyInteger('system_announcement')->default(1)->comment('系统公告通知');
            $table->tinyInteger('promotion_activity')->default(1)->comment('活动推广通知');
            $table->tinyInteger('sms_enabled')->default(1)->comment('短信通知开关');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('system_messages');
        Schema::dropIfExists('message_templates');
    }
};