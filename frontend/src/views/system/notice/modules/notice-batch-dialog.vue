<!-- 系统通知批量发送对话框 -->
<template>
  <ElDialog
    :model-value="visible"
    @update:model-value="handleCancel"
    title="批量发送通知"
    width="800px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="handleClosed"
    class="notice-batch-dialog"
  >
    <ElForm
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
      class="notice-batch-form"
    >
      <!-- 接收用户 -->
      <ElFormItem label="接收用户" prop="user_ids">
        <ElSelect
          v-model="form.user_ids"
          multiple
          filterable
          remote
          reserve-keyword
          :remote-method="handleSearchUsers"
          :loading="userLoading"
          placeholder="搜索用户名/昵称，可多选（最多 500 人）"
          style="width: 100%"
          collapse-tags
          :max-collapse-tags="3"
        >
          <ElOption
            v-for="opt in userOptions"
            :key="opt.value"
            :label="opt.label"
            :value="opt.value"
          />
        </ElSelect>
        <div class="user-count-hint">
          已选择 <span class="count">{{ form.user_ids.length }}</span> / 500 人
        </div>
      </ElFormItem>

      <!-- 类型 + 级别 一行 -->
      <ElRow :gutter="20">
        <ElCol :span="12">
          <ElFormItem label="通知类型" prop="type">
            <ElSelect v-model="form.type" placeholder="请选择" style="width: 100%">
              <ElOption :label="'系统通知'" :value="1" />
              <ElOption :label="'待办'" :value="2" />
              <ElOption :label="'预警'" :value="3" />
              <ElOption :label="'个人消息'" :value="4" />
            </ElSelect>
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="通知级别" prop="level">
            <ElSelect v-model="form.level" placeholder="请选择" style="width: 100%">
              <ElOption :label="'普通'" value="info" />
              <ElOption :label="'成功'" value="success" />
              <ElOption :label="'警告'" value="warning" />
              <ElOption :label="'严重'" value="danger" />
            </ElSelect>
          </ElFormItem>
        </ElCol>
      </ElRow>

      <!-- 标题 -->
      <ElFormItem label="通知标题" prop="title">
        <ElInput
          v-model="form.title"
          placeholder="请输入通知标题（最多 200 字）"
          maxlength="200"
          show-word-limit
          clearable
        />
      </ElFormItem>

      <!-- 内容 - 富文本 -->
      <ElFormItem label="通知内容" prop="content">
        <ArtWangEditor
          v-model="form.content"
          :height="'320px'"
          placeholder="请输入通知内容（支持富文本格式）"
          :exclude-keys="['fontFamily', 'codeBlock', 'video']"
        />
      </ElFormItem>

      <!-- 高级设置 - 折叠 -->
      <ElCollapse v-model="advancedVisible">
        <ElCollapseItem title="高级设置" name="advanced">
          <ElRow :gutter="20">
            <ElCol :span="12">
              <ElFormItem label="业务类型" prop="biz_type">
                <ElInput
                  v-model="form.biz_type"
                  placeholder="可选，如 order / contract"
                  maxlength="50"
                  clearable
                />
              </ElFormItem>
            </ElCol>
            <ElCol :span="12">
              <ElFormItem label="业务ID" prop="biz_id">
                <ElInput
                  v-model="form.biz_id"
                  placeholder="可选，关联业务记录ID"
                  maxlength="100"
                  clearable
                />
              </ElFormItem>
            </ElCol>
          </ElRow>
          <ElFormItem label="跳转链接" prop="link">
            <ElInput
              v-model="form.link"
              placeholder="可选，PC 端相对或绝对路径"
              maxlength="500"
              clearable
            />
          </ElFormItem>
          <ElFormItem label="过期时间" prop="expire_time">
            <ElDatePicker
              v-model="form.expire_time"
              type="datetime"
              placeholder="为空则永不过期"
              value-format="YYYY-MM-DD HH:mm:ss"
              format="YYYY-MM-DD HH:mm:ss"
              style="width: 100%"
            />
          </ElFormItem>
        </ElCollapseItem>
      </ElCollapse>
    </ElForm>

    <template #footer>
      <ElButton @click="handleReset">重置</ElButton>
      <ElButton @click="handleCancel">取消</ElButton>
      <ElButton type="primary" :loading="submitting" @click="handleSubmit">
        批量发送
      </ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import type { FormRules, FormInstance } from 'element-plus'
  import { ElMessage } from 'element-plus'
  import ArtWangEditor from '@/components/core/forms/art-wang-editor/index.vue'
  import { fetchBatchCreateNotice, fetchGetUserList } from '@/api/system-manage'

  defineOptions({ name: 'NoticeBatchDialog' })

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'success'): void
  }

  const props = defineProps<{ visible: boolean }>()
  const emit = defineEmits<Emits>()

  const formRef = ref<FormInstance>()
  const submitting = ref(false)
  const userLoading = ref(false)
  const advancedVisible = ref<string[]>([])

  const userOptions = ref<Array<{ label: string; value: number }>>([])

  const form = reactive({
    user_ids: [] as number[],
    type: 1,
    level: 'info' as 'info' | 'success' | 'warning' | 'danger',
    title: '',
    content: '',
    biz_type: '',
    biz_id: '',
    link: '',
    expire_time: ''
  })

  const rules = reactive<FormRules>({
    user_ids: [
      {
        required: true,
        type: 'array',
        min: 1,
        message: '请选择至少一位接收人',
        trigger: 'change'
      }
    ],
    title: [
      { required: true, message: '请输入通知标题', trigger: 'blur' },
      { max: 200, message: '标题不能超过 200 个字符', trigger: 'blur' }
    ],
    type: [{ required: true, message: '请选择类型', trigger: 'change' }],
    level: [{ required: true, message: '请选择级别', trigger: 'change' }],
    content: [
      {
        validator: (_rule, value, callback) => {
          if (!value || value.replace(/<[^>]*>/g, '').trim() === '') {
            callback(new Error('请输入通知内容'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ]
  })

  /**
   * 远程搜索用户
   */
  const handleSearchUsers = async (query: string): Promise<void> => {
    userLoading.value = true
    try {
      const res = await fetchGetUserList({
        page: 1,
        limit: 50,
        keyword: query || ''
      })
      userOptions.value = (res.list || []).map((u) => ({
        label: u.nickname ? `${u.username}（${u.nickname}）` : u.username,
        value: u.id
      }))
    } catch (error) {
      console.error('搜索用户失败', error)
    } finally {
      userLoading.value = false
    }
  }

  const resetForm = (): void => {
    formRef.value?.resetFields()
    Object.assign(form, {
      user_ids: [],
      type: 1,
      level: 'info',
      title: '',
      content: '',
      biz_type: '',
      biz_id: '',
      link: '',
      expire_time: ''
    })
    userOptions.value = []
    advancedVisible.value = []
  }

  const handleReset = (): void => {
    resetForm()
    // 重置后重新加载用户列表
    nextTick(() => {
      handleSearchUsers('')
    })
  }

  const handleSubmit = async (): Promise<void> => {
    if (!formRef.value) return
    try {
      await formRef.value.validate()
    } catch {
      ElMessage.error('请检查表单填写')
      return
    }

    if (form.user_ids.length > 500) {
      ElMessage.error('单次批量发送最多 500 人')
      return
    }

    submitting.value = true
    try {
      const res = await fetchBatchCreateNotice({ ...form })
      ElMessage.success(`发送成功，共 ${res.count || form.user_ids.length} 条`)
      emit('success')
      handleCancel()
    } catch (error) {
      console.error('批量发送失败', error)
    } finally {
      submitting.value = false
    }
  }

  const handleCancel = (): void => {
    emit('update:visible', false)
  }

  const handleClosed = (): void => {
    resetForm()
  }

  watch(
    () => props.visible,
    (newVal) => {
      if (newVal) {
        // 打开时主动拉一次用户列表
        nextTick(() => {
          handleSearchUsers('')
        })
      }
    }
  )
</script>

<style scoped lang="scss">
  .notice-batch-dialog {
    :deep(.el-dialog__body) {
      padding-top: 16px;
    }
  }

  .notice-batch-form {
    :deep(.el-form-item) {
      margin-bottom: 16px;
    }
  }

  .user-count-hint {
    margin-top: 6px;
    font-size: 12px;
    color: var(--el-text-color-secondary);

    .count {
      color: var(--el-color-primary);
      font-weight: 600;
    }
  }

  :deep(.el-collapse) {
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 4px;

    .el-collapse-item__header {
      font-size: 13px;
      font-weight: 500;
      color: var(--el-text-color-secondary);
      background: var(--el-fill-color-light);
      padding-left: 12px;
      padding-right: 12px;
    }

    .el-collapse-item__wrap {
      border-bottom: none;
    }

    .el-collapse-item__content {
      padding: 12px 12px 0;
    }
  }
</style>
