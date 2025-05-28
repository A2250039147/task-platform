<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('virtual_user_ids', function (Blueprint $table) {
            $table->tinyInteger('is_privileged_user')->default(0)->comment('是否特权用户的ID');
            $table->bigInteger('task_id')->nullable()->comment('关联的任务ID（特权用户专用）');
            
            // 添加索引
            $table->index('virtual_member_id', 'idx_virtual_member_id');
            $table->index(['real_user_id', 'platform_id', 'is_privileged_user'], 'idx_user_platform_privileged');
        });
    }

    public function down()
    {
        Schema::table('virtual_user_ids', function (Blueprint $table) {
            $table->dropIndex('idx_user_platform_privileged');
            $table->dropIndex('idx_virtual_member_id');
            $table->dropColumn(['is_privileged_user', 'task_id']);
        });
    }
};