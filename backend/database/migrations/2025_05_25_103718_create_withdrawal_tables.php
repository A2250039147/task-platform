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
        // 提现申请表
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 10, 2)->comment('提现金额');
            $table->decimal('fee', 8, 2)->default(0)->comment('手续费');
            $table->decimal('actual_amount', 10, 2)->comment('实际到账金额');
            $table->enum('payment_method', ['alipay', 'wechat', 'bank'])->comment('提现方式');
            $table->string('payment_account', 100)->comment('收款账号');
            $table->string('payment_name', 50)->comment('收款人姓名');
            $table->string('bank_name', 100)->nullable()->comment('银行名称(银行卡提现)');
            $table->tinyInteger('status')->default(0)->comment('0:待审核 1:审核通过 2:处理中 3:已完成 4:已拒绝');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('审核管理员ID');
            $table->text('admin_remark')->nullable()->comment('审核备注');
            $table->timestamp('processed_at')->nullable()->comment('处理时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->string('transaction_id', 100)->nullable()->comment('交易流水号');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // 提现配置表
        Schema::create('withdrawal_configs', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_amount', 8, 2)->default(10.00)->comment('最低提现金额');
            $table->decimal('max_amount', 10, 2)->default(10000.00)->comment('最高提现金额');
            $table->decimal('fee_rate', 5, 4)->default(0.0200)->comment('手续费率');
            $table->decimal('daily_limit', 10, 2)->default(5000.00)->comment('每日提现限额');
            $table->decimal('monthly_limit', 12, 2)->default(50000.00)->comment('每月提现限额');
            $table->string('processing_days', 20)->default('1-3')->comment('处理时间说明');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // 财务对账表
        Schema::create('financial_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('date')->comment('对账日期');
            $table->decimal('total_income', 12, 2)->default(0)->comment('总收入');
            $table->decimal('total_user_rewards', 12, 2)->default(0)->comment('用户总奖励');
            $table->decimal('total_platform_commission', 12, 2)->default(0)->comment('平台总佣金');
            $table->decimal('total_withdrawals', 12, 2)->default(0)->comment('总提现金额');
            $table->decimal('withdrawal_fees', 10, 2)->default(0)->comment('提现手续费');
            $table->decimal('profit', 12, 2)->default(0)->comment('净利润');
            $table->tinyInteger('status')->default(0)->comment('0:未对账 1:已对账');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('对账管理员');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            
            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliations');
        Schema::dropIfExists('withdrawal_configs');
        Schema::dropIfExists('withdrawal_requests');
    }
};