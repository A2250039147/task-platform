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
        Schema::create('sms_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->comment('手机号');
            $table->string('code', 6)->comment('验证码');
            $table->string('type', 20)->default('register')->comment('验证码类型');
            $table->timestamp('expires_at')->comment('过期时间');
            $table->tinyInteger('is_used')->default(0)->comment('是否已使用 0:未使用 1:已使用');
            $table->string('ip_address', 45)->comment('发送IP地址');
            $table->timestamps();
            
            $table->index(['phone', 'code']);
            $table->index(['phone', 'type']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_verification_codes');
    }
};