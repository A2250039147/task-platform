import { defineStore } from 'pinia'
import { authAPI } from '@/services/api'
import { ElMessage } from 'element-plus'

export const useUserStore = defineStore('user', {
  state: () => ({
    // 用户信息
    user: null,
    // 登录token
    token: localStorage.getItem('token') || null,
    // 是否已登录
    isLoggedIn: false,
  }),
  
  getters: {
    // 用户名
    userName: (state) => state.user?.username || '',
    // 手机号
    userPhone: (state) => state.user?.phone || '',
    // 是否管理员（支持多种判断方式）
    isAdmin: (state) => {
      if (!state.user) return false
      
      // 方式1: 检查role字段
      if (state.user.role === 'admin' || state.user.role === 'super_admin') {
        return true
      }
      
      // 方式2: 检查is_admin字段
      if (state.user.is_admin === true) {
        return true
      }
      
      // 方式3: 检查特定用户ID（可在这里配置超级管理员ID）
      const adminUserIds = [1] // 可以配置多个管理员ID
      if (adminUserIds.includes(state.user.id)) {
        return true
      }
      
      // 方式4: 检查邮箱（临时方案）
      const adminEmails = ['admin@example.com']
      if (state.user.email && adminEmails.includes(state.user.email)) {
        return true
      }
      
      return false
    },
  },
  
  actions: {
    // 用户登录
    async login(loginData) {
      try {
        const response = await authAPI.login(loginData)
        
        // 检查响应格式并正确提取数据
        if (response.success && response.data) {
          // 保存token和用户信息
          this.token = response.data.token
          this.user = response.data.user
          this.isLoggedIn = true
          // 保存到本地存储
          localStorage.setItem('token', response.data.token)
          ElMessage.success('登录成功！')
          return { success: true }
        } else {
          throw new Error(response.message || '登录失败')
        }
      } catch (error) {
        ElMessage.error('登录失败，请检查账号密码')
        return { success: false, error }
      }
    },
    
    // 用户注册
    async register(registerData) {
      try {
        const response = await authAPI.register(registerData)
        ElMessage.success('注册成功！请登录')
        return { success: true, data: response }
      } catch (error) {
        return { success: false, error }
      }
    },
    
    // 发送短信验证码
    async sendSmsCode(phone) {
      try {
        await authAPI.sendSms({ phone })
        ElMessage.success('验证码已发送！')
        return { success: true }
      } catch (error) {
        return { success: false, error }
      }
    },
    
    // 获取用户信息
    async fetchUserInfo() {
      try {
        const response = await authAPI.getUserInfo()
        this.user = response.user || response.data
        this.isLoggedIn = true
        return { success: true }
      } catch (error) {
        // 如果获取用户信息失败，清除登录状态
        this.logout()
        return { success: false }
      }
    },
    
    // 用户登出
    async logout() {
      try {
        // 调用后端登出接口
        await authAPI.logout()
      } catch (error) {
        // 即使后端出错也要清除本地状态
        console.error('登出接口调用失败:', error)
      }
      // 清除本地状态
      this.user = null
      this.token = null
      this.isLoggedIn = false
      localStorage.removeItem('token')
      ElMessage.success('已退出登录')
    },
    
    // 初始化用户状态（应用启动时调用）
    async initUserState() {
      if (this.token) {
        // 如果本地有token，尝试获取用户信息
        await this.fetchUserInfo()
      }
    },
  },
})