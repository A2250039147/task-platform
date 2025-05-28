<template>
  <header class="header">
    <div class="header-container">
      <!-- Logo区域 -->
      <div class="logo">
        <router-link to="/" class="logo-link">
          <span class="logo-text">任务聚合平台</span>
        </router-link>
      </div>
      
      <!-- 导航菜单 -->
      <nav class="nav-menu">
        <router-link to="/" class="nav-item">首页</router-link>
        <a href="#" class="nav-item">任务大厅</a>
        <a href="#" class="nav-item">用户中心</a>
      </nav>
      
      <!-- 用户操作区 -->
      <div class="user-actions">
        <!-- 未登录状态 -->
        <div v-if="!userStore.isLoggedIn">
          <el-button @click="goToLogin">登录</el-button>
          <el-button type="primary" @click="goToRegister">注册</el-button>
        </div>
        
        <!-- 已登录状态 -->
        <div v-else class="user-info">
          <span class="welcome-text">欢迎，{{ userStore.userName }}</span>
          <el-button @click="handleLogout">退出</el-button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

// 跳转到登录页
const goToLogin = () => {
  router.push('/login')
}

// 跳转到注册页
const goToRegister = () => {
  router.push('/register')
}

// 处理退出登录
const handleLogout = () => {
  userStore.logout()
}
</script>

<style scoped>
.header {
  background: var(--bg-color);
  border-bottom: 1px solid var(--border-color);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--spacing-md);
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.logo-link {
  text-decoration: none;
}

.logo-text {
  font-size: 20px;
  font-weight: 600;
  color: var(--primary-color);
}

.nav-menu {
  display: flex;
  align-items: center;
  gap: var(--spacing-lg);
}

.nav-item {
  color: var(--text-color-regular);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s;
}

.nav-item:hover,
.nav-item.router-link-active {
  color: var(--primary-color);
}

.user-actions {
  display: flex;
  gap: var(--spacing-sm);
}

.user-info {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.welcome-text {
  color: var(--text-color-primary);
  font-weight: 500;
}

/* 响应式 */
@media (max-width: 768px) {
  .nav-menu {
    display: none;
  }
  
  .header-container {
    padding: 0 var(--spacing-sm);
  }
  
  .welcome-text {
    display: none;
  }
}
</style>