<template>
  <div class="register-page">
    <div class="register-container">
      <div class="register-card">
        <h2 class="register-title">用户注册</h2>
        
        <el-form 
          :model="registerForm" 
          :rules="registerRules" 
          ref="registerFormRef"
          size="large"
        >
          <el-form-item prop="phone">
            <el-input
              v-model="registerForm.phone"
              placeholder="请输入手机号"
              prefix-icon="Phone"
            />
          </el-form-item>
          
          <el-form-item prop="code">
            <div class="code-input-group">
              <el-input
                v-model="registerForm.code"
                placeholder="请输入验证码"
                prefix-icon="Message"
              />
              <el-button 
                type="primary" 
                :loading="smsLoading"
                :disabled="countdown > 0"
                @click="sendSmsCode"
                class="code-btn"
              >
                {{ countdown > 0 ? `${countdown}s后重发` : '获取验证码' }}
              </el-button>
            </div>
          </el-form-item>
          
          <el-form-item prop="username">
            <el-input
              v-model="registerForm.username"
              placeholder="请输入用户名"
              prefix-icon="User"
            />
          </el-form-item>
          
          <el-form-item prop="password">
            <el-input
              v-model="registerForm.password"
              type="password"
              placeholder="请输入密码"
              prefix-icon="Lock"
              show-password
            />
          </el-form-item>
          
          <el-form-item prop="confirmPassword">
            <el-input
              v-model="registerForm.confirmPassword"
              type="password"
              placeholder="请确认密码"
              prefix-icon="Lock"
              show-password
            />
          </el-form-item>
          
          <el-form-item>
            <el-checkbox v-model="registerForm.agree">
              我已阅读并同意
              <el-button type="text">《用户协议》</el-button>
              和
              <el-button type="text">《隐私政策》</el-button>
            </el-checkbox>
          </el-form-item>
          
          <el-form-item>
            <el-button 
              type="primary" 
              class="register-btn"
              :loading="loading"
              @click="handleRegister"
            >
              注册
            </el-button>
          </el-form-item>
        </el-form>
        
        <div class="register-footer">
          <span>已有账号？</span>
          <el-button type="text" @click="goToLogin">
            立即登录
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { ElMessage } from 'element-plus'

const router = useRouter()
const userStore = useUserStore()

// 表单数据
const registerForm = reactive({
  phone: '',
  code: '',
  username: '',
  password: '',
  confirmPassword: '',
  agree: false
})

// 表单验证规则
const registerRules = {
  phone: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' }
  ],
  code: [
    { required: true, message: '请输入验证码', trigger: 'blur' },
    { pattern: /^\d{6}$/, message: '验证码为6位数字', trigger: 'blur' }
  ],
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 2, max: 20, message: '用户名长度在2-20位之间', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码长度至少6位', trigger: 'blur' }
  ],
  confirmPassword: [
    { required: true, message: '请确认密码', trigger: 'blur' },
    {
      validator: (rule, value, callback) => {
        if (value !== registerForm.password) {
          callback(new Error('两次输入密码不一致'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

const registerFormRef = ref()
const loading = ref(false)
const smsLoading = ref(false)
const countdown = ref(0)

// 发送短信验证码
const sendSmsCode = async () => {
  console.log('点击发送验证码，手机号:', registerForm.phone)
  
  // 先验证手机号
  if (!registerForm.phone) {
    ElMessage.error('请先输入手机号')
    return
  }
  
  if (!/^1[3-9]\d{9}$/.test(registerForm.phone)) {
    ElMessage.error('手机号格式不正确')
    return
  }
  
  smsLoading.value = true
  
  try {
    const result = await userStore.sendSmsCode(registerForm.phone)
    console.log('发送结果:', result)
    
    if (result.success) {
      // 开始倒计时
      countdown.value = 60
      const timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
          clearInterval(timer)
        }
      }, 1000)
    }
  } catch (error) {
    console.error('发送验证码失败:', error)
    ElMessage.error('发送失败，请重试')
  } finally {
    smsLoading.value = false
  }
}

// 处理注册
const handleRegister = async () => {
  try {
    // 表单验证
    await registerFormRef.value.validate()
    
    // 检查是否同意协议
    if (!registerForm.agree) {
      ElMessage.error('请阅读并同意用户协议和隐私政策')
      return
    }
    
    loading.value = true
    
    // 调用注册
    const result = await userStore.register({
      phone: registerForm.phone,
      code: registerForm.code,
      username: registerForm.username,
      password: registerForm.password,
      password_confirmation: registerForm.confirmPassword
    })
    
    if (result.success) {
      ElMessage.success('注册成功！')
      // 注册成功，跳转到登录页面
      router.push('/login')
    }
    
  } catch (error) {
    console.error('注册失败:', error)
  } finally {
    loading.value = false
  }
}

// 跳转到登录页面
const goToLogin = () => {
  router.push('/login')
}
</script>

<style scoped>
.register-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.register-container {
  width: 100%;
  max-width: 400px;
  padding: var(--spacing-md, 16px);
}

.register-card {
  background: var(--bg-color, #ffffff);
  padding: var(--spacing-xl, 32px);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.register-title {
  text-align: center;
  margin-bottom: var(--spacing-xl, 32px);
  color: var(--text-color-primary, #333);
  font-weight: 600;
}

.code-input-group {
  display: flex;
  gap: var(--spacing-sm, 8px);
}

.code-input-group .el-input {
  flex: 1;
}

.code-btn {
  width: 120px;
  flex-shrink: 0;
}

.register-btn {
  width: 100%;
  height: 44px;
}

.register-footer {
  text-align: center;
  margin-top: var(--spacing-md, 16px);
  color: var(--text-color-secondary, #666);
}

.register-footer .el-button {
  padding: 0;
  margin-left: var(--spacing-xs, 4px);
}

/* 响应式 */
@media (max-width: 768px) {
  .register-container {
    padding: var(--spacing-sm, 8px);
  }
  
  .register-card {
    padding: var(--spacing-lg, 24px);
  }
}
</style>