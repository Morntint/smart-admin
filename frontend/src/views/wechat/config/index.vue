<!-- 微信配置管理页面 -->
<template>
  <div class="wechat-config-page" v-loading="loading">
    <ElCard class="page-header-card">
      <template #header>
        <div class="card-header">
          <span>微信配置管理</span>
          <ElButton
            type="primary"
            @click="handleSave"
            :loading="saving"
            v-auth="'wechat:config:edit'"
          >
            <el-icon><Check /></el-icon>
            保存配置
          </ElButton>
        </div>
      </template>
      <ElAlert
        type="info"
        :closable="false"
        show-icon
        title="配置说明"
        description="敏感字段（AppSecret / Token / AESKey 等）默认以星号占位显示。如需修改，请点击「修改」按钮解锁该字段，留空提交则保持原值不变。"
      />
    </ElCard>

    <ElForm ref="formRef" :model="formData" :rules="rules" label-width="160px" size="default">
      <ElRow :gutter="20">
        <ElCol :xs="24" :sm="24" :md="12" v-for="(group, key) in configGroups" :key="key">
          <ElCard class="config-card" shadow="hover">
            <template #header>
              <div class="group-header">
                <el-icon class="group-icon"
                  ><component :is="iconMap[group.icon] || Setting"
                /></el-icon>
                <span class="group-name">{{ group.name }}</span>
              </div>
            </template>

            <template v-for="item in group.items" :key="item.key">
              <ElFormItem :label="item.name" :prop="item.key" :required="isRequired(item.key)">
                <div class="config-input">
                  <ElInput
                    v-model="formData[item.key]"
                    :type="isSecret(item) ? 'password' : 'text'"
                    :placeholder="placeholderFor(item)"
                    :disabled="isSecret(item) && !unlocked.has(item.key)"
                    clearable
                    show-password
                  />
                  <ElButton
                    v-if="isSecret(item)"
                    :type="unlocked.has(item.key) ? 'warning' : 'default'"
                    @click="toggleUnlock(item)"
                    v-auth="'wechat:config:edit'"
                  >
                    {{ unlocked.has(item.key) ? '取消修改' : '修改' }}
                  </ElButton>
                </div>
                <div v-if="item.remark" class="form-tip">{{ item.remark }}</div>
              </ElFormItem>
            </template>
          </ElCard>
        </ElCol>
      </ElRow>
    </ElForm>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, onMounted } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import {
    Check,
    Setting,
    ChatDotRound,
    DataLine,
    Money,
    OfficeBuilding,
    Monitor
  } from '@element-plus/icons-vue'
  import { fetchWechatConfig, updateWechatConfig } from '@/api/wechat'

  defineOptions({ name: 'WechatConfig' })

  type ConfigItem = {
    id: number
    key: string
    name: string
    value: string
    remark: string
    /** 后端标记是否敏感字段 */
    is_secret?: boolean
    /** 后端是否已存在值（用于决定占位符 / 校验文案） */
    has_value?: boolean
  }
  type ConfigGroup = { name: string; icon: string; items: ConfigItem[] }

  const formRef = ref<FormInstance>()
  const loading = ref(false)
  const saving = ref(false)
  /** 表单数据（key => value）。敏感字段在用户主动「修改」前保留为空字符串 */
  const formData = reactive<Record<string, string>>({})
  /** 用户已解锁可编辑的敏感字段集合 */
  const unlocked = ref<Set<string>>(new Set())

  const configGroups = ref<Record<string, ConfigGroup>>({})

  const iconMap: Record<string, any> = {
    wechat: ChatDotRound,
    app: Monitor,
    global: DataLine,
    company: OfficeBuilding,
    money: Money
  }

  /** 必填的非敏感字段（敏感字段是否必填取决于后端 has_value） */
  const requiredKeys = [
    'wechat_official_appid',
    'wechat_official_secret',
    'wechat_mini_appid',
    'wechat_mini_secret',
    'wechat_pay_appid',
    'wechat_pay_mch_id'
  ]

  const isRequired = (key: string): boolean => requiredKeys.includes(key)

  /** 敏感字段：后端 is_secret 优先，否则按后缀兜底 */
  const isSecret = (item: ConfigItem): boolean => {
    if (typeof item.is_secret === 'boolean') return item.is_secret
    return /(_secret|_aes_key|_token|_pay_key|_private_key)$/.test(item.key)
  }

  const placeholderFor = (item: ConfigItem): string => {
    if (isSecret(item)) {
      return item.has_value ? '已配置（留空表示不变）' : `请输入${item.name}`
    }
    return `请输入${item.name}`
  }

  /** 切换敏感字段的编辑状态 */
  const toggleUnlock = (item: ConfigItem): void => {
    if (unlocked.value.has(item.key)) {
      unlocked.value.delete(item.key)
      formData[item.key] = ''
    } else {
      unlocked.value.add(item.key)
      formData[item.key] = ''
    }
  }

  /** 表单校验规则：非敏感必填字段必须填写；敏感字段除非用户解锁且为空，否则不校验 */
  const rules = reactive<FormRules>({})

  const buildRules = (): void => {
    Object.keys(rules).forEach((k) => delete (rules as any)[k])
    requiredKeys.forEach((key) => {
      ;(rules as any)[key] = [{ required: true, message: '该字段必填', trigger: 'blur' }]
    })
  }

  // 加载配置
  const loadConfig = async (): Promise<void> => {
    loading.value = true
    try {
      const data = (await fetchWechatConfig()) as Record<string, ConfigGroup>
      configGroups.value = data || {}

      // 清空已有 formData / unlocked，再回填，避免上次残留
      Object.keys(formData).forEach((k) => delete formData[k])
      unlocked.value.clear()

      Object.values(configGroups.value).forEach((group) => {
        group.items.forEach((item) => {
          // 敏感字段：表单内值留空（占位符里告诉用户已配置 / 未配置）
          formData[item.key] = isSecret(item) ? '' : item.value || ''
        })
      })

      buildRules()
    } catch (error) {
      console.error('加载配置失败:', error)
      ElMessage.error('加载配置失败')
    } finally {
      loading.value = false
    }
  }

  // 保存配置
  const handleSave = async (): Promise<void> => {
    try {
      await formRef.value?.validate()
    } catch {
      return
    }

    try {
      await ElMessageBox.confirm('确认更新微信配置？敏感字段留空表示不变。', '保存确认', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
    } catch {
      return
    }

    // 提交时：跳过未解锁的敏感字段（防止误传空值清空后端真值）
    const payload: Record<string, string> = {}
    Object.values(configGroups.value).forEach((group) => {
      group.items.forEach((item) => {
        if (isSecret(item) && !unlocked.value.has(item.key)) return
        if (formData[item.key] !== undefined && formData[item.key] !== null) {
          payload[item.key] = formData[item.key]
        }
      })
    })

    saving.value = true
    try {
      await updateWechatConfig(payload)
      ElMessage.success('保存成功')
      unlocked.value.clear()
      await loadConfig()
    } catch (error: any) {
      console.error('保存配置失败:', error)
      ElMessage.error('保存失败：' + (error?.message ?? '请稍后重试'))
    } finally {
      saving.value = false
    }
  }

  onMounted(() => {
    loadConfig()
  })
</script>

<style scoped lang="scss">
  .wechat-config-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .page-header-card {
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
  }

  .config-card {
    margin-bottom: 20px;

    .group-header {
      display: flex;
      align-items: center;
      gap: 8px;

      .group-icon {
        font-size: 18px;
        color: var(--el-color-primary);
      }

      .group-name {
        font-size: 16px;
        font-weight: 500;
      }
    }
  }

  .config-input {
    display: flex;
    gap: 8px;
    width: 100%;
  }

  .form-tip {
    margin-top: 4px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
    line-height: 1.5;
  }
</style>
