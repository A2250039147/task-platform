import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/stores/user'

// 布局组件
const Layout = () => import('@/components/Layout.vue')

// 页面组件
const Home = () => import('@/views/Home.vue')
const Login = () => import('@/views/Login.vue')
const Register = () => import('@/views/Register.vue')
const TaskTest = () => import('@/views/TaskTest.vue')

const routes = [
  {
    path: '/',
    component: Layout,
    children: [
      {
        path: '',
        name: 'Home',
        component: Home,
        meta: { title: '首页' }
      }
    ]
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: {
      title: '用户控制台',
      requiresAuth: true
    }
  },
  {
    path: '/task-test',
    name: 'TaskTest',
    component: TaskTest,
    meta: {
      title: '任务测试页面'
    }
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: {
      title: '用户登录',
      hideForAuth: true
    }
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: {
      title: '用户注册',
      hideForAuth: true
    }
  },
  // 管理员页面（独立路由）
  {
    path: '/admin/privilege-users',
    name: 'PrivilegeUserManagement',
    component: () => import('@/views/admin/PrivilegeUserManagement.vue'),
    meta: {
      title: '特权用户管理',
      requiresAuth: true,
      requiresAdmin: true
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 异步路由守卫
router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore()
  
  // 设置页面标题
  if (to.meta.title) {
    document.title = `${to.meta.title} - 任务聚合平台`
  }
  
  // 如果有token但没有用户信息，先尝试获取用户信息
  if (userStore.token && !userStore.user) {
    try {
      await userStore.fetchUserInfo()
    } catch (error) {
      console.error('获取用户信息失败:', error)
    }
  }
  
  // 检查是否需要登录
  if (to.meta.requiresAuth && !userStore.isLoggedIn) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }
  
  // 检查是否需要管理员权限
  if (to.meta.requiresAdmin) {
    // 如果没有用户信息，重定向到登录页
    if (!userStore.user) {
      next({ name: 'Login', query: { redirect: to.fullPath } })
      return
    }
    
    // 检查管理员权限：用户ID为1或is_privileged为true
    const isAdmin = userStore.user.id === 1 || userStore.user.is_privileged === true
    
    if (!isAdmin) {
      // 跳转到首页并提示权限不足
      next({ name: 'Home' })
      return
    }
  }
  
  // 如果是登录/注册页面，且用户已登录，重定向到首页
  if (to.meta.hideForAuth && userStore.isLoggedIn) {
    next({ name: 'Home' })
    return
  }
  
  next()
})

export default router