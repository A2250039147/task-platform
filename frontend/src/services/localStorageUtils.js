/**
 * 本地存储防重复参与工具类
 */
class LocalStorageUtils {
  constructor() {
    this.prefix = 'task_participated_'
    this.expirationKey = 'task_expiration_'
  }

  /**
   * 检查用户是否已经参与过任务
   * @param {number} taskId 任务ID
   * @param {string} userId 用户ID
   * @returns {boolean} 是否已参与
   */
  hasParticipated(taskId, userId) {
    const key = this.getKey(taskId, userId)
    const expirationKey = this.getExpirationKey(taskId, userId)
    
    // 检查过期时间
    const expiration = localStorage.getItem(expirationKey)
    if (expiration && new Date().getTime() > parseInt(expiration)) {
      // 已过期，清除记录
      this.removeParticipation(taskId, userId)
      return false
    }
    
    return localStorage.getItem(key) === 'true'
  }

  /**
   * 标记用户已参与任务
   * @param {number} taskId 任务ID
   * @param {string} userId 用户ID
   * @param {number} hours 过期小时数，默认24小时
   */
  markParticipated(taskId, userId, hours = 24) {
    const key = this.getKey(taskId, userId)
    const expirationKey = this.getExpirationKey(taskId, userId)
    const expirationTime = new Date().getTime() + (hours * 60 * 60 * 1000)
    
    localStorage.setItem(key, 'true')
    localStorage.setItem(expirationKey, expirationTime.toString())
  }

  /**
   * 移除参与记录
   * @param {number} taskId 任务ID
   * @param {string} userId 用户ID
   */
  removeParticipation(taskId, userId) {
    const key = this.getKey(taskId, userId)
    const expirationKey = this.getExpirationKey(taskId, userId)
    
    localStorage.removeItem(key)
    localStorage.removeItem(expirationKey)
  }

  /**
   * 检查IP是否已参与（模拟，实际由后端控制）
   * @param {number} taskId 任务ID
   * @returns {boolean} 是否已参与
   */
  hasIPParticipated(taskId) {
    const key = `ip_participated_${taskId}`
    const expirationKey = `ip_expiration_${taskId}`
    
    // 检查过期时间
    const expiration = localStorage.getItem(expirationKey)
    if (expiration && new Date().getTime() > parseInt(expiration)) {
      localStorage.removeItem(key)
      localStorage.removeItem(expirationKey)
      return false
    }
    
    return localStorage.getItem(key) === 'true'
  }

  /**
   * 标记IP已参与
   * @param {number} taskId 任务ID
   * @param {number} hours 过期小时数，默认24小时
   */
  markIPParticipated(taskId, hours = 24) {
    const key = `ip_participated_${taskId}`
    const expirationKey = `ip_expiration_${taskId}`
    const expirationTime = new Date().getTime() + (hours * 60 * 60 * 1000)
    
    localStorage.setItem(key, 'true')
    localStorage.setItem(expirationKey, expirationTime.toString())
  }

  /**
   * 清理所有过期的记录
   */
  cleanupExpired() {
    const currentTime = new Date().getTime()
    const keysToRemove = []
    
    // 遍历所有localStorage项
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      
      // 检查过期时间键
      if (key && key.includes('_expiration_')) {
        const expirationTime = parseInt(localStorage.getItem(key))
        if (currentTime > expirationTime) {
          // 找到对应的数据键
          const dataKey = key.replace('_expiration_', '_participated_')
          keysToRemove.push(key, dataKey)
        }
      }
    }
    
    // 移除过期项
    keysToRemove.forEach(key => {
      localStorage.removeItem(key)
    })
    
    console.log(`清理了 ${keysToRemove.length / 2} 个过期的任务参与记录`)
  }

  /**
   * 获取用户参与记录的键名
   * @private
   */
  getKey(taskId, userId) {
    return `${this.prefix}${taskId}_${userId}`
  }

  /**
   * 获取过期时间的键名
   * @private
   */
  getExpirationKey(taskId, userId) {
    return `${this.expirationKey}${taskId}_${userId}`
  }

  /**
   * 获取所有参与记录（调试用）
   */
  getAllParticipations() {
    const participations = []
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(this.prefix)) {
        participations.push({
          key,
          value: localStorage.getItem(key),
          expiration: localStorage.getItem(key.replace('participated', 'expiration'))
        })
      }
    }
    return participations
  }

  /**
   * 清除所有参与记录（调试用）
   */
  clearAllParticipations() {
    const keysToRemove = []
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && (key.includes('_participated_') || key.includes('_expiration_'))) {
        keysToRemove.push(key)
      }
    }
    
    keysToRemove.forEach(key => {
      localStorage.removeItem(key)
    })
    
    console.log(`清除了所有任务参与记录，共 ${keysToRemove.length} 项`)
  }
}

// 创建单例实例
const localStorageUtils = new LocalStorageUtils()

// 启动时清理过期记录
localStorageUtils.cleanupExpired()

// 定期清理过期记录（每10分钟）
setInterval(() => {
  localStorageUtils.cleanupExpired()
}, 10 * 60 * 1000)

export default localStorageUtils