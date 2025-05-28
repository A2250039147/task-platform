<template>
  <div class="privilege-user-management">
    <!-- 页面标题和统计信息 -->
    <el-card class="stats-card">
      <h2>特权用户管理</h2>
      <el-row :gutter="20" class="stats-row">
        <el-col :span="6">
          <el-statistic title="总用户数" :value="stats.total_users" />
        </el-col>
        <el-col :span="6">
          <el-statistic title="特权用户" :value="stats.privileged_users" />
        </el-col>
        <el-col :span="6">
          <el-statistic title="普通用户" :value="stats.normal_users" />
        </el-col>
        <el-col :span="6">
          <el-statistic 
            title="特权比例" 
            :value="stats.privilege_rate" 
            suffix="%" 
          />
        </el-col>
      </el-row>
    </el-card>

    <!-- 搜索和操作区域 -->
    <el-card class="search-card">
      <el-row :gutter="20">
        <el-col :span="4">
          <el-input
            v-model="searchForm.phone"
            placeholder="手机号"
            clearable
            @clear="loadUsers"
            @keyup.enter="loadUsers"
          >
            <template #prefix>
              <el-icon><Phone /></el-icon>
            </template>
          </el-input>
        </el-col>
        
        <el-col :span="4">
          <el-input
            v-model="searchForm.username"
            placeholder="用户名"
            clearable
            @clear="loadUsers"
            @keyup.enter="loadUsers"
          >
            <template #prefix>
              <el-icon><User /></el-icon>
            </template>
          </el-input>
        </el-col>
        
        <el-col :span="3">
          <el-select v-model="searchForm.is_privileged" placeholder="用户类型" clearable>
            <el-option label="全部" value="" />
            <el-option label="特权用户" :value="true" />
            <el-option label="普通用户" :value="false" />
          </el-select>
        </el-col>
        
        <el-col :span="3">
          <el-select v-model="searchForm.status" placeholder="状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-col>
        
        <el-col :span="4">
          <el-button type="primary" @click="loadUsers" :loading="loading">
            <el-icon><Search /></el-icon>
            搜索
          </el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-col>
        
        <el-col :span="6" class="text-right">
          <el-button 
            type="success" 
            @click="batchTogglePrivilege(true)"
            :disabled="selectedUsers.length === 0"
          >
            批量设为特权
          </el-button>
          <el-button 
            type="warning" 
            @click="batchTogglePrivilege(false)"
            :disabled="selectedUsers.length === 0"
          >
            批量取消特权
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 用户列表 -->
    <el-card>
      <el-table 
        :data="users" 
        v-loading="loading"
        @selection-change="handleSelectionChange"
        stripe
      >
        <el-table-column type="selection" width="50" />
        
        <el-table-column prop="id" label="用户ID" width="80" />
        
        <el-table-column prop="phone" label="手机号" width="120" />
        
        <el-table-column prop="username" label="用户名" width="120" />
        
        <el-table-column label="当前状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.is_privileged ? 'success' : 'info'">
              {{ row.is_privileged ? '特权用户' : '普通用户' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="账户状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column prop="created_at" label="注册时间" width="160">
          <template #default="{ row }">
            {{ formatDate(row.created_at) }}
          </template>
        </el-table-column>
        
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <!-- 特权开关 -->
            <el-switch
              v-model="row.is_privileged"
              active-text="特权"
              inactive-text="普通"
              @change="togglePrivilege(row)"
              class="mr-2"
            />
            
            <el-button 
              type="primary" 
              size="small" 
              @click="viewUserDetail(row)"
            >
              详情
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.size"
          :total="pagination.total"
          :page-sizes="[10, 15, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="loadUsers"
          @size-change="loadUsers"
        />
      </div>
    </el-card>

    <!-- 用户详情对话框 -->
    <el-dialog 
      v-model="detailDialogVisible" 
      title="用户详情" 
      width="600px"
      append-to-body
    >
      <div v-if="currentUser">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="用户ID">{{ currentUser.id }}</el-descriptions-item>
          <el-descriptions-item label="手机号">{{ currentUser.phone }}</el-descriptions-item>
          <el-descriptions-item label="用户名">{{ currentUser.username }}</el-descriptions-item>
          <el-descriptions-item label="用户类型">
            <el-tag :type="currentUser.is_privileged ? 'success' : 'info'">
              {{ currentUser.is_privileged ? '特权用户' : '普通用户' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="注册时间">
            {{ formatDate(currentUser.created_at) }}
          </el-descriptions-item>
          <el-descriptions-item label="最后登录">
            {{ formatDate(currentUser.updated_at) }}
          </el-descriptions-item>
        </el-descriptions>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Phone, User, Search
} from '@element-plus/icons-vue'
import { privilegeUserAPI } from '@/services/api'

// 响应式数据
const loading = ref(false)
const users = ref([])
const selectedUsers = ref([])
const stats = ref({
  total_users: 0,
  privileged_users: 0,
  normal_users: 0,
  privilege_rate: 0
})

const detailDialogVisible = ref(false)
const currentUser = ref(null)

// 搜索表单 - 保持原有逻辑
const searchForm = reactive({
  phone: '',
  username: '',
  is_privileged: null,
  status: null
})

// 分页数据
const pagination = reactive({
  current: 1,
  size: 15,
  total: 0
})

// 加载用户列表 - 保持原有逻辑
const loadUsers = async () => {
  loading.value = true
  try {
    const cleanParams = Object.fromEntries(
      Object.entries(searchForm).filter(([_, value]) => value !== null && value !== '')
    )
    
    // 添加分页参数
    const params = {
      ...cleanParams,
      page: pagination.current,
      per_page: pagination.size
    }
    
    const result = await privilegeUserAPI.getUsers(params)
    if (result.code === 200) {
      users.value = result.data.data
      pagination.total = result.data.total
    } else {
      ElMessage.error(result.message || '获取用户列表失败')
    }
  } catch (error) {
    console.error('加载用户列表失败:', error)
  } finally {
    loading.value = false
  }
}

// 加载统计信息
const loadStats = async () => {
  try {
    const result = await privilegeUserAPI.getStats()
    if (result.code === 200) {
      stats.value = result.data
    }
  } catch (error) {
    console.error('加载统计信息失败:', error)
  }
}

// 重置搜索 - 保持原有逻辑
const resetSearch = () => {
  Object.assign(searchForm, {
    phone: '',
    username: '',
    is_privileged: null,
    status: null
  })
  pagination.current = 1
  loadUsers()
}

// 切换特权状态
const togglePrivilege = async (user) => {
  try {
    const result = await privilegeUserAPI.togglePrivilege(user.id, user.is_privileged)
    if (result.code === 200) {
      ElMessage.success(result.message)
      loadStats() // 刷新统计信息
    } else {
      // 失败时恢复原状态
      user.is_privileged = !user.is_privileged
      ElMessage.error(result.message || '操作失败')
    }
  } catch (error) {
    user.is_privileged = !user.is_privileged
    console.error('切换特权状态失败:', error)
  }
}

// 批量切换特权
const batchTogglePrivilege = async (isPrivileged) => {
  const userIds = selectedUsers.value.map(user => user.id)
  const action = isPrivileged ? '设为特权用户' : '取消特权'
  
  try {
    await ElMessageBox.confirm(
      `确定要将选中的 ${userIds.length} 个用户${action}吗？`,
      '批量操作确认',
      { type: 'warning' }
    )
    
    const result = await privilegeUserAPI.batchTogglePrivilege(userIds, isPrivileged)
    if (result.code === 200) {
      ElMessage.success(result.message)
      loadUsers()
      loadStats()
    } else {
      ElMessage.error(result.message || '批量操作失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量操作失败:', error)
    }
  }
}

// 查看用户详情 - 简化版本，只显示基本信息
const viewUserDetail = async (user) => {
  currentUser.value = user
  detailDialogVisible.value = true
}

// 选择变化
const handleSelectionChange = (selection) => {
  selectedUsers.value = selection
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('zh-CN')
}

// 组件挂载时加载数据
onMounted(() => {
  loadUsers()
  loadStats()
})
</script>

<style scoped>
.privilege-user-management {
  padding: 20px;
}

.stats-card {
  margin-bottom: 20px;
}

.stats-row {
  margin-top: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.pagination-wrapper {
  margin-top: 20px;
  text-align: right;
}

.text-right {
  text-align: right;
}

.mr-2 {
  margin-right: 8px;
}
</style>