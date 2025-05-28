import axios from 'axios'
import { ElMessage } from 'element-plus'

// 创建axios实例
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// 请求拦截器
api.interceptors.request.use(
  (config) => {
    // 添加token（如果有的话）
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 响应拦截器
api.interceptors.response.use(
  (response) => {
    return response.data
  },
  (error) => {
    // 统一处理错误
    if (error.response) {
      const { status, data } = error.response
      switch (status) {
        case 401:
          ElMessage.error('登录已过期，请重新登录')
          localStorage.removeItem('token')
          // 可以在这里跳转到登录页
          break
        case 422:
          // 表单验证错误
          if (data.errors) {
            const firstError = Object.values(data.errors)[0]
            ElMessage.error(Array.isArray(firstError) ? firstError[0] : firstError)
          } else {
            ElMessage.error(data.message || '请求参数错误')
          }
          break
        case 500:
          ElMessage.error('服务器内部错误')
          break
        default:
          ElMessage.error(data.message || '网络错误')
      }
    } else {
      ElMessage.error('网络连接异常')
    }
    return Promise.reject(error)
  }
)

// 认证相关API
export const authAPI = {
  // 用户注册
  register: (data) => api.post('/auth/register', data),
  // 用户登录
  login: (data) => api.post('/auth/login', data),
  // 发送短信验证码
  sendSms: (data) => api.post('/auth/send-register-code', data),
  // 验证短信验证码
  verifySms: (data) => api.post('/auth/verify-sms', data),
  // 获取用户信息
  getUserInfo: () => api.get('/auth/me'),
  // 退出登录
  logout: () => api.post('/auth/logout'),
}

// 特权用户管理API
export const privilegeUserAPI = {
  // 获取用户列表
  getUsers: (params) => api.get('/admin/privilege-users', { params }),
  
  // 获取特权用户统计信息
  getStats: () => api.get('/admin/privilege-users/stats'),
  
  // 获取单个用户详情
  getUserDetail: (userId) => api.get(`/admin/privilege-users/${userId}`),
  
  // 切换单个用户特权状态
  togglePrivilege: (userId, isPrivileged) => 
    api.put(`/admin/privilege-users/${userId}/toggle-privilege`, { is_privileged: isPrivileged }),
  
  // 批量设置特权状态
  batchTogglePrivilege: (userIds, isPrivileged) => 
    api.put('/admin/privilege-users/batch-toggle-privilege', { user_ids: userIds, is_privileged: isPrivileged }),
  
  // 重置用户虚拟ID
  resetVirtualIds: (userId) => api.delete(`/admin/privilege-users/${userId}/virtual-ids`),
}

export default api