<template>
  <div class="task-participation-guard">
    <!-- 防重复检查提示 -->
    <el-alert
      v-if="checkResult && !checkResult.canParticipate"
      :title="checkResult.title"
      :description="checkResult.message"
      :type="checkResult.type"
      :closable="false"
      show-icon
      class="mb-4"
    />
    
    <!-- 参与按钮 -->
    <el-button
      v-if="checkResult?.canParticipate"
      type="primary"
      size="large"
      :loading="participating"
      @click="handleParticipate"
      class="participate-btn"
    >
      {{ participating ? '参与中...' : '参与任务' }}
    </el-button>
    
    <!-- 调试信息（开发环境显示） -->
    <div v-if="isDev && debugInfo" class="debug-info mt-4">
      <el-card header="调试信息" size="small">
        <el-descriptions :column="1" size="small">
          <el-descriptions-item label="用户ID">{{ debugInfo.userId }}</el-descriptions-item>
          <el-descriptions-item label="任务ID">{{ debugInfo.taskId }}</el-descriptions-item>
          <el-descriptions-item label="本地存储检查">{{ debugInfo.localCheck ? '已参与' : '未参与' }}</el-descriptions-item>
          <el-descriptions-item label="IP检查">{{ debugInfo.ipCheck ? '已参与' : '未参与' }}</el-descriptions-item>
        </el-descriptions>
        
        <el-button size="small" @click="clearLocalRecord" class="mt-2">
          清除本地记录（测试用）
        </el-button>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import localStorageUtils from '@/services/localStorageUtils'

const props = defineProps({
  taskId: {
    type: Number,
    required: true
  },
  userId: {
    type: String,
    required: true
  },
  taskTitle: {
    type: String,
    default: '未知任务'
  }
})

const emit = defineEmits(['participate', 'blocked'])

const participating = ref(false)
const checkResult = ref(null)
const debugInfo = ref(null)
const isDev = computed(() => import.meta.env.DEV)

// 检查是否可以参与任务
const checkParticipation = () => {
  // 本地存储检查
  const localCheck = localStorageUtils.hasParticipated(props.taskId, props.userId)
  const ipCheck = localStorageUtils.hasIPParticipated(props.taskId)
  
  // 调试信息
  debugInfo.value = {
    userId: props.userId,
    taskId: props.taskId,
    localCheck,
    ipCheck
  }
  
  if (localCheck) {
    checkResult.value = {
      canParticipate: false,
      title: '任务重复参与提醒',
      message: `您已经参与过任务"${props.taskTitle}"，请勿重复参与`,
      type: 'warning'
    }
    emit('blocked', 'user_participated')
    return
  }
  
  if (ipCheck) {
    checkResult.value = {
      canParticipate: false,
      title: 'IP重复参与提醒', 
      message: '当前网络环境已有用户参与过此任务，请更换网络环境或联系客服',
      type: 'error'
    }
    emit('blocked', 'ip_participated')
    return
  }
  
  checkResult.value = {
    canParticipate: true,
    title: '可以参与',
    message: '检查通过，可以参与此任务',
    type: 'success'
  }
}

// 处理任务参与
const handleParticipate = async () => {
  if (!checkResult.value?.canParticipate) {
    return
  }
  
  participating.value = true
  
  try {
    // 标记本地存储（提前标记，防止快速重复点击）
    localStorageUtils.markParticipated(props.taskId, props.userId)
    localStorageUtils.markIPParticipated(props.taskId)
    
    // 发送参与事件给父组件
    await emit('participate', {
      taskId: props.taskId,
      userId: props.userId
    })
    
    ElMessage.success('任务参与成功！')
    
    // 重新检查状态
    checkParticipation()
    
  } catch (error) {
    // 参与失败，移除本地标记
    localStorageUtils.removeParticipation(props.taskId, props.userId)
    
    ElMessage.error(error.message || '参与失败，请重试')
  } finally {
    participating.value = false
  }
}

// 清除本地记录（测试用）
const clearLocalRecord = () => {
  localStorageUtils.removeParticipation(props.taskId, props.userId)
  ElMessage.info('本地记录已清除')
  checkParticipation()
}

// 监听props变化，重新检查
watch(() => [props.taskId, props.userId], () => {
  if (props.taskId && props.userId) {
    checkParticipation()
  }
}, { immediate: true })

onMounted(() => {
  checkParticipation()
})
</script>

<style scoped>
.task-participation-guard {
  padding: 16px;
}

.participate-btn {
  width: 100%;
  font-size: 16px;
  padding: 12px;
}

.debug-info {
  border: 1px dashed #ddd;
  padding: 12px;
  border-radius: 4px;
  background-color: #f9f9f9;
}

.mb-4 {
  margin-bottom: 16px;
}

.mt-4 {
  margin-top: 16px;
}

.mt-2 {
  margin-top: 8px;
}
</style>