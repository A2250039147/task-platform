<template>
  <div class="task-test-page">
    <h2>任务参与测试页面</h2>
    
    <!-- 模拟任务卡片 -->
    <div class="task-card">
      <h3>测试任务：完成注册流程</h3>
      <p>奖励：5.00元</p>
      <p>任务ID：1001</p>
      
      <!-- 使用我们创建的防重复组件 -->
      <TaskParticipationGuard
        :task-id="1001"
        :user-id="'U123456'"
        task-title="完成注册流程"
        @participate="handleParticipate"
        @blocked="handleBlocked"
      />
    </div>
  </div>
</template>

<script setup>
import TaskParticipationGuard from '@/components/TaskParticipationGuard.vue'
import { ElMessage } from 'element-plus'

const handleParticipate = (data) => {
  console.log('参与任务:', data)
  ElMessage.success('任务参与成功！')
}

const handleBlocked = (reason) => {
  console.log('参与被阻止，原因:', reason)
}
</script>

<style scoped>
.task-test-page {
  max-width: 600px;
  margin: 0 auto;
  padding: 20px;
}

.task-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  background: white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>