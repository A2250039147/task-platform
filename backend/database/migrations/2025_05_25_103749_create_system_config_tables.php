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
        // 系统配置表
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->comment('配置分类');
            $table->string('key_name', 100)->comment('配置键名');
            $table->text('value')->nullable()->comment('配置值');
            $table->string('description')->nullable()->comment('配置说明');
            $table->enum('type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->tinyInteger('is_public')->default(0)->comment('是否公开给前端');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            
            $table->unique(['category', 'key_name']);
            $table->index('category');
        });

        // 操作日志表（增强版）
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理员ID');
            $table->string('action', 50)->comment('操作动作');
            $table->string('module', 50)->comment('操作模块');
            $table->string('resource', 100)->nullable()->comment('操作资源');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('资源ID');
            $table->string('method', 10)->nullable()->comment('HTTP方法');
            $table->string('url', 500)->nullable()->comment('请求URL');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->json('request_data')->nullable()->comment('请求参数');
            $table->json('response_data')->nullable()->comment('响应数据');
            $table->integer('execution_time')->nullable()->comment('执行时间(毫秒)');
            $table->enum('status', ['success', 'error', 'warning'])->default('success');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('admin_id');
            $table->index(['action', 'module']);
            $table->index('ip_address');
            $table->index('risk_level');
            $table->index('created_at');
        });

        // 管理员表
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->string('name', 50);
            $table->string('avatar')->nullable();
            $table->enum('role', ['super_admin', 'admin', 'operator', 'finance'])->default('admin');
            $table->json('permissions')->nullable()->comment('权限列表');
            $table->tinyInteger('status')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('operation_logs');
        Schema::dropIfExists('system_configs');
    }
};