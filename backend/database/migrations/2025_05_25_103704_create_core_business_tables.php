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
        // 用户表（含防作弊和特权字段）
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 32)->unique()->comment('用户会员ID');
            $table->string('phone', 20)->unique()->comment('手机号（防作弊）');
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->decimal('total_earnings', 12, 2)->default(0)->comment('总收益');
            $table->decimal('available_earnings', 12, 2)->default(0)->comment('可提现收益');
            $table->decimal('frozen_earnings', 12, 2)->default(0)->comment('冻结收益');
            $table->tinyInteger('is_privileged')->default(0)->comment('是否特权用户');
            $table->integer('privilege_level')->default(0)->comment('特权等级');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->tinyInteger('status')->default(1)->comment('1:正常 0:禁用');
            $table->timestamps();
            
            $table->index('phone');
            $table->index('member_id');
        });

        // 平台配置表
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->enum('sync_mode', ['auto', 'manual'])->default('auto');
            $table->decimal('price_ratio', 5, 4)->default(0.8000)->comment('价格显示比例');
            $table->json('api_config')->comment('API配置');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        // 任务表
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_id');
            $table->string('platform_task_id', 100);
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('original_price', 8, 2)->comment('原始价格');
            $table->decimal('reward', 8, 2)->comment('用户奖励');
            $table->decimal('commission', 8, 2)->comment('平台佣金');
            $table->integer('duration')->nullable()->comment('时长(分钟)');
            $table->tinyInteger('is_manual')->default(0);
            $table->text('source_url')->nullable();
            $table->string('link_id', 100)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            
            $table->foreign('platform_id')->references('id')->on('platforms');
            $table->unique(['platform_id', 'platform_task_id']);
        });

        // 用户任务记录表（含IP防重字段）
        Schema::create('user_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('task_id');
            $table->string('virtual_member_id', 32)->nullable()->comment('使用的会员ID（可能是虚拟ID）');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0:进行中 1:完成 2:失败');
            $table->decimal('reward_amount', 8, 2)->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('task_id')->references('id')->on('tasks');
            $table->unique(['virtual_member_id', 'task_id']);
            $table->index(['task_id', 'ip_address']);
        });

        // 虚拟用户ID管理表
        Schema::create('virtual_user_ids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('real_user_id');
            $table->string('virtual_member_id', 32)->unique();
            $table->unsignedBigInteger('platform_id');
            $table->string('id_format', 50);
            $table->tinyInteger('is_active')->default(1);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->foreign('real_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('platform_id')->references('id')->on('platforms');
        });

        // 收益记录表
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_task_id')->nullable();
            $table->tinyInteger('type')->comment('1:任务完成 2:推荐奖励');
            $table->decimal('amount', 8, 2);
            $table->string('description')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0:待结算 1:已结算');
            $table->timestamp('settlement_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('earnings');
        Schema::dropIfExists('virtual_user_ids');
        Schema::dropIfExists('user_tasks');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('users');
    }
};