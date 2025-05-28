<template>
  <div class="layout">
    <!-- 顶部导航 -->
    <Header />
    
    <!-- 主要内容区 -->
    <main class="main-content">
      <div class="content-container">
        <slot></slot>
      </div>
    </main>
    
    <!-- 底部 -->
    <Footer />
  </div>
</template>

<script setup>
import Header from './Header.vue'
import Footer from './Footer.vue'
</script>

<style scoped>
.layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.main-content {
  flex: 1;
  background-color: var(--bg-color-page);
}

.content-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--spacing-lg) var(--spacing-md);
  min-height: 400px;
}

/* 响应式 */
@media (max-width: 768px) {
  .content-container {
    padding: var(--spacing-md) var(--spacing-sm);
  }
}
</style>