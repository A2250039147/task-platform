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
        // 每日统计表
        Schema::create('daily_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('new_users')->default(0)->comment('新增用户数');
            $table->integer('active_users')->default(0)->comment('活跃用户数');
            $table->integer('completed_tasks')->default(0)->comment('完成任务数');
            $table->decimal('total_rewards', 10, 2)->default(0)->comment('总奖励金额');
            $table->decimal('total_commission', 10, 2)->default(0)->comment('总佣金');
            $table->decimal('withdrawal_amount', 10, 2)->default(0)->comment('提现金额');
            $table->integer('withdrawal_count')->default(0)->comment('提现笔数');
            $table->decimal('avg_task_duration', 8, 2)->default(0)->comment('平均任务时长');
            $table->timestamps();
            
            $table->unique('date');
        });

        // 平台统计表
        Schema::create('platform_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_id');
            $table->date('date');
            $table->integer('total_tasks')->default(0)->comment('总任务数');
            $table->integer('completed_tasks')->default(0)->comment('完成任务数');
            $table->decimal('completion_rate', 5, 2)->default(0)->comment('完成率');
            $table->decimal('total_rewards', 10, 2)->default(0)->comment('总奖励');
            $table->decimal('avg_reward', 8, 2)->default(0)->comment('平均奖励');
            $table->integer('unique_participants')->default(0)->comment('独立参与用户数');
            $table->timestamps();
            
            $table->foreign('platform_id')->references('id')->on('platforms');
            $table->unique(['platform_id', 'date']);
        });

        // 用户行为统计表
        Schema::create('user_behavior_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->integer('login_count')->default(0)->comment('登录次数');
            $table->integer('task_view_count')->default(0)->comment('查看任务次数');
            $table->integer('task_participate_count')->default(0)->comment('参与任务次数');
            $table->integer('task_complete_count')->default(0)->comment('完成任务次数');
            $table->decimal('earned_amount', 8, 2)->default(0)->comment('当日收益');
            $table->integer('online_duration')->default(0)->comment('在线时长(秒)');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_behavior_statistics');
        Schema::dropIfExists('platform_statistics');
        Schema::dropIfExists('daily_statistics');
    }
};