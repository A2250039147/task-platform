<template>
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-content">
        <div class="footer-section">
          <h4>关于我们</h4>
          <p>专业的任务聚合平台，为用户提供优质的任务体验。</p>
        </div>
        
        <div class="footer-section">
          <h4>联系我们</h4>
          <p>客服QQ: 123456789</p>
          <p>客服微信: service123</p>
        </div>
        
        <div class="footer-section">
          <h4>友情链接</h4>
          <div class="links">
            <a href="#">合作伙伴</a>
            <a href="#">意见反馈</a>
          </div>
        </div>
      </div>
      
      <div class="footer-bottom">
        <p>&copy; 2024 任务聚合平台. All rights reserved.</p>
      </div>
    </div>
  </footer>
</template>

<script setup>
// 暂时没有逻辑
</script>

<style scoped>
.footer {
  background: var(--bg-color);
  border-top: 1px solid var(--border-color);
  margin-top: auto;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--spacing-xl) var(--spacing-md) var(--spacing-md);
}

.footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: var(--spacing-lg);
  margin-bottom: var(--spacing-lg);
}

.footer-section h4 {
  margin-bottom: var(--spacing-sm);
  color: var(--text-color-primary);
  font-weight: 600;
}

.footer-section p {
  color: var(--text-color-secondary);
  line-height: 1.6;
  margin-bottom: var(--spacing-xs);
}

.links {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.links a {
  color: var(--text-color-secondary);
  text-decoration: none;
  transition: color 0.3s;
}

.links a:hover {
  color: var(--primary-color);
}

.footer-bottom {
  text-align: center;
  padding-top: var(--spacing-md);
  border-top: 1px solid var(--border-color-light);
  color: var(--text-color-secondary);
  font-size: 12px;
}

/* 响应式 */
@media (max-width: 768px) {
  .footer-container {
    padding: var(--spacing-lg) var(--spacing-sm) var(--spacing-sm);
  }
  
  .footer-content {
    grid-template-columns: 1fr;
    gap: var(--spacing-md);
  }
}
</style>