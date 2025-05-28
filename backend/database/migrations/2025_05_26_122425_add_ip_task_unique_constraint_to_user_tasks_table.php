<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_tasks', function (Blueprint $table) {
            // 添加IP和任务的唯一约束，防止同一IP重复参与同一任务
            $table->unique(['task_id', 'ip_address'], 'user_tasks_task_ip_unique');
        });
    }

    public function down()
    {
        Schema::table('user_tasks', function (Blueprint $table) {
            $table->dropUnique('user_tasks_task_ip_unique');
        });
    }
};