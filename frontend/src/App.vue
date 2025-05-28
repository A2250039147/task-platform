<template>
  <div id="app">
    <RouterView />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

// 应用启动时初始化用户状态
onMounted(() => {
  userStore.initUserState()
})
</script>

<style>
#app {
  height: 100%;
}
</style>