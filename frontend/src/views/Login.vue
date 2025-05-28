<template>
  <div class="login-page">
    <div class="login-container">
      <div class="login-card">
        <h2 class="login-title">用户登录</h2>
        
        <el-form 
          :model="loginForm" 
          :rules="loginRules" 
          ref="loginFormRef"
          size="large"
        >
          <el-form-item prop="phone">
            <el-input
              v-model="loginForm.phone"
              placeholder="请输入手机号"
              prefix-icon="Phone"
            />
          </el-form-item>
          
          <el-form-item prop="password">
            <el-input
              v-model="loginForm.password"
              type="password"
              placeholder="请输入密码"
              prefix-icon="Lock"
              show-password
            />
          </el-form-item>
          
          <el-form-item>
            <el-checkbox v-model="loginForm.remember">
              记住登录状态
            </el-checkbox>
          </el-form-item>
          
          <el-form-item>
            <el-button 
              type="primary" 
              class="login-btn"
              :loading="loading"
              @click="handleLogin"
            >
              登录
            </el-button>
          </el-form-item>
        </el-form>
        
        <div class="login-footer">
          <span>还没有账号？</span>
          <el-button  @click="goToRegister">
            立即注册
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { ElMessage } from 'element-plus'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

// 表单数据
const loginForm = reactive({
  phone: '',
  password: '',
  remember: false
})

// 表单验证规则
const loginRules = {
  phone: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码长度至少6位', trigger: 'blur' }
  ]
}

const loginFormRef = ref()
const loading = ref(false)

// 处理登录
const handleLogin = async () => {
  try {
    // 表单验证
    await loginFormRef.value.validate()
    
    loading.value = true
    
    // 调用登录
    const result = await userStore.login({
      phone: loginForm.phone,
      password: loginForm.password,
      remember: loginForm.remember
    })
    
    if (result.success) {
      // 登录成功，检查是否有重定向参数
      const redirectPath = route.query.redirect || '/'
      
      // 如果重定向到管理页面，确保用户有管理员权限
      if (redirectPath.includes('/admin/')) {
        // 给一点时间让用户状态更新
        setTimeout(() => {
          router.push(redirectPath)
        }, 100)
      } else {
        router.push(redirectPath)
      }
    }
    
  } catch (error) {
    console.error('登录失败:', error)
  } finally {
    loading.value = false
  }
}

// 跳转到注册页面
const goToRegister = () => {
  router.push('/register')
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-container {
  width: 100%;
  max-width: 400px;
  padding: var(--spacing-md);
}

.login-card {
  background: var(--bg-color);
  padding: var(--spacing-xl);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.login-title {
  text-align: center;
  margin-bottom: var(--spacing-xl);
  color: var(--text-color-primary);
  font-weight: 600;
}

.login-btn {
  width: 100%;
  height: 44px;
}

.login-footer {
  text-align: center;
  margin-top: var(--spacing-md);
  color: var(--text-color-secondary);
}

.login-footer .el-button {
  padding: 0;
  margin-left: var(--spacing-xs);
}

/* 响应式 */
@media (max-width: 768px) {
  .login-container {
    padding: var(--spacing-sm);
  }
  
  .login-card {
    padding: var(--spacing-lg);
  }
}
</style>