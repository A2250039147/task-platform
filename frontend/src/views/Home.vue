<template>
  <div class="home-content">
    <div class="hero-section">
      <h1>欢迎来到任务聚合平台</h1>
      <p>整合多平台任务，让赚钱更简单</p>
      <div class="hero-buttons">
        <el-button type="primary" size="large">开始任务</el-button>
        <el-button size="large">了解更多</el-button>
      </div>
    </div>
    
    <div class="features-section">
      <h2>平台特色</h2>
      <div class="features-grid">
        <div class="feature-card">
          <el-icon size="48" color="#409eff"><Star /></el-icon>
          <h3>安全可靠</h3>
          <p>四重防作弊机制，保障平台公平性</p>
        </div>
        <div class="feature-card">
          <el-icon size="48" color="#67c23a"><Money /></el-icon>
          <h3>收益丰厚</h3>
          <p>多平台任务聚合，收益机会更多</p>
        </div>
        <div class="feature-card">
          <el-icon size="48" color="#e6a23c"><Clock /></el-icon>
          <h3>提现快速</h3>
          <p>1-3个工作日内完成提现处理</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Star, Money, Clock } from '@element-plus/icons-vue'
</script>

<style scoped>
.home-content {
  width: 100%;
}

.hero-section {
  text-align: center;
  padding: var(--spacing-xxl, 48px) 0;
  background: linear-gradient(135deg, #f0f9ff 0%, #f0fff4 100%);
  border-radius: 8px;
  margin-bottom: var(--spacing-xl);
}

.hero-section h1 {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-color-primary);
  margin-bottom: var(--spacing-md);
}

.hero-section p {
  font-size: 1.2rem;
  color: var(--text-color-secondary);
  margin-bottom: var(--spacing-xl);
}

.hero-buttons {
  display: flex;
  gap: var(--spacing-md);
  justify-content: center;
}

.features-section {
  margin-top: var(--spacing-xl);
}

.features-section h2 {
  text-align: center;
  font-size: 2rem;
  color: var(--text-color-primary);
  margin-bottom: var(--spacing-xl);
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: var(--spacing-lg);
}

.feature-card {
  text-align: center;
  padding: var(--spacing-xl);
  background: var(--bg-color);
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-4px);
}

.feature-card h3 {
  margin: var(--spacing-md) 0 var(--spacing-sm) 0;
  color: var(--text-color-primary);
}

.feature-card p {
  color: var(--text-color-secondary);
  line-height: 1.6;
}

/* 响应式 */
@media (max-width: 768px) {
  .hero-section h1 {
    font-size: 2rem;
  }
  
  .hero-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .features-grid {
    grid-template-columns: 1fr;
  }
}
</style>