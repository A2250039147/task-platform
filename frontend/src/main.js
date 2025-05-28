import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'

// 导入全局样式
import './styles/global.css'

import App from './App.vue'
import router from './router'
import { useUserStore } from './stores/user'

async function initApp() {
  const app = createApp(App)
  const pinia = createPinia()

  app.use(pinia)
  
  // 先初始化用户状态，再初始化路由
  const userStore = useUserStore()
  
  // 等待用户状态初始化完成
  await userStore.initUserState()
  
  // 然后再初始化路由和其他组件
  app.use(router)
  app.use(ElementPlus)

  app.mount('#app')
}

// 启动应用
initApp().catch(console.error)