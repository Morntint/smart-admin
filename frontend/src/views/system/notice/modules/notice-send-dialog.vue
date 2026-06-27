<!-- 系统通知发送弹窗 -->
<template>
  <ElDialog
    v-model="innerVisible"
    :title="mode === 'edit' ? '编辑系统通知' : '发送系统通知'"
    width="980px"
    :close-on-click-modal="false"
    destroy-on-close
    class="notice-send-dialog"
    @close="handleClose"
  >
    <ElForm
      ref="formRef"
      :model="form"
      :rules="rules"
      label-position="top"
      class="notice-send-form"
    >
      <ElRow :gutter="20">
        <ElCol :xs="24" :md="16">
          <!-- 标题 -->
          <ElFormItem label="通知标题" prop="title">
            <ElInput
              v-model="form.title"
              placeholder="请输入通知标题（最多 200 字）"
              maxlength="200"
              show-word-limit
              clearable
              size="large"
            />
          </ElFormItem>

          <!-- 内容 -->
          <ElFormItem label="通知内容" prop="content">
            <ArtWangEditor
              v-model="form.content"
              :height="'360px'"
              placeholder="请输入通知内容（支持富文本）"
              :exclude-keys="['fontFamily', 'codeBlock', 'video']"
            />
          </ElFormItem>
        </ElCol>

        <ElCol :xs="24" :md="8">
          <div class="notice-send-side">
            <!-- 接收人 -->
            <div class="notice-send-side-block">
              <div class="notice-send-side-label">接收用户 <span class="required">*</span></div>
              <ElSelect
                v-model="form.user_id"
                filterable
                remote
                :remote-method="handleSearchUsers"
                :loading="userLoading"
                :disabled="mode === 'edit'"
                placeholder="请输入用户名/昵称搜索"
                style="width: 100%"
              >
                <ElOption
                  v-for="opt in userOptions"
                  :key="opt.value"
                  :label="opt.label"
                  :value="opt.value"
                />
              </ElSelect>
              <div class="notice-send-side-tip">单次发送只支持一个用户。批量请使用「批量发送」。</div>
            </div>

            <!-- 类型 + 级别 -->
            <div class="notice-send-side-block">
              <div class="notice-send-side-grid">
                <div>
                  <div class="notice-send-side-label">类型 <span class="required">*</span></div>
                  <ElSelect v-model="form.type" placeholder="请选择" style="width: 100%">
                    <ElOption :label="'系统通知'" :value="1" />
                    <ElOption :label="'待办'" :value="2" />
                    <ElOption :label="'预警'" :value="3" />
                    <ElOption :label="'个人消息'" :value="4" />
                  </ElSelect>
                </div>
                <div>
                  <div class="notice-send-side-label">级别 <span class="required">*</span></div>
                  <ElSelect v-model="form.level" placeholder="请选择" style="width: 100%">
                    <ElOption :label="'普通'" value="info" />
                    <ElOption :label="'成功'" value="success" />
                    <ElOption :label="'警告'" value="warning" />
                    <ElOption :label="'严重'" value="danger" />
                  </ElSelect>
                </div>
              </div>
            </div>

            <!-- 高级 -->
            <div class="notice-send-side-block">
              <div class="notice-send-side-label">高级设置</div>
              <ElFormItem label="业务类型" prop="biz_type" label-position="top" class="!mb-3">
                <ElInput
                  v-model="form.biz_type"
                  placeholder="可选，如 order / contract"
                  maxlength="50"
                  clearable
                />
              </ElFormItem>
              <ElFormItem label="业务ID" prop="biz_id" label-position="top" class="!mb-3">
                <ElInput
                  v-model="form.biz_id"
                  placeholder="可选，关联业务记录ID"
                  maxlength="100"
                  clearable
                />
              </ElFormItem>
              <ElFormItem label="跳转链接" prop="link" label-position="top" class="!mb-3">
                <ElInput
                  v-model="form.link"
                  placeholder="可选，PC 端相对或绝对路径"
                  maxlength="500"
                  clearable
                />
              </ElFormItem>
              <ElFormItem label="过期时间" prop="expire_time" label-position="top" class="!mb-0">
                <ElDatePicker
                  v-model="form.expire_time"
                  type="datetime"
                  placeholder="为空则永不过期"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  format="YYYY-MM-DD HH:mm:ss"
                  style="width: 100%"
                />
              </ElFormItem>
            </div>
          </div>
        </ElCol>
      </ElRow>
    </ElForm>

    <template #footer>
      <ElButton @click="handleClose">取消</ElButton>
      <ElButton @click="handleReset">重置</ElButton>
      <ElButton type="primary" :loading="submitting" @click="handleSubmit">
        {{ mode === 'edit' ? '保存修改' : '立即发送' }}
      </ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
  import { fetchCreateNotice, fetchUpdateNotice, fetchGetUserList, fetchGetNotice } from '@/api/system-manage'
  import ArtWangEditor from '@/components/core/forms/art-wang-editor/index.vue'

  defineOptions({ name: 'NoticeSendDialog' })

  type NoticeSubmitParams = Api.SystemManage.NoticeSubmitParams
  type NoticeListItem = Api.SystemManage.NoticeListItem

  interface NoticeForm {
    user_id: number | undefined
    type: number
    level: 'info' | 'success' | 'warning' | 'danger'
    title: string
    content: string
    biz_type: string
    biz_id: string
    link: string
    expire_time: string
  }

  const props = defineProps<{
    visible: boolean
    editId?: number | null
  }>()

  const emit = defineEmits<{
    'update:visible': [value: boolean]
    success: []
  }>()

  const innerVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
  })

  const mode = computed<'add' | 'edit'>(() => (props.editId ? 'edit' : 'add'))

  const formRef = ref<FormInstance>()
  const submitting = ref(false)

  const form = reactive<NoticeForm>({
    user_id: undefined,
    type: 1,
    level: 'info',
    title: '',
    content: '',
    biz_type: '',
    biz_id: '',
    link: '',
    expire_time: ''
  })

  const rules = reactive<FormRules>({
    user_id: [{ required: true, message: '请选择接收用户', trigger: 'change' }],
    type: [{ required: true, message: '请选择通知类型', trigger: 'change' }],
    level: [{ required: true, message: '请选择级别', trigger: 'change' }],
    title: [
      { required: true, message: '请输入通知标题', trigger: 'blur' },
      { max: 200, message: '标题不能超过 200 字', trigger: 'blur' }
    ],
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

  // 用户选项
  const userOptions = ref<Array<{ label: string; value: number }>>([])
  const userLoading = ref(false)

  const handleSearchUsers = async (query: string): Promise<void> => {
    userLoading.value = true
    try {
      const res = await fetchGetUserList({
        page: 1,
        limit: 30,
        keyword: query || ''
      })
      userOptions.value = (res.list || []).map((u) => ({
        label: u.nickname ? `${u.username}（${u.nickname}）` : u.username,
        value: u.id
      }))
    } catch (e) {
      console.error('搜索用户失败', e)
    } finally {
      userLoading.value = false
    }
  }

  /**
   * 重置表单
   */
  const handleReset = (): void => {
    if (mode.value === 'edit') return
    formRef.value?.resetFields()
    Object.assign(form, {
      user_id: undefined,
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
  }

  /**
   * 加载编辑数据
   */
  const loadEditData = async (id: number): Promise<void> => {
    try {
      const data: NoticeListItem = await fetchGetNotice(id)
      Object.assign(form, {
        user_id: data.user_id,
        type: data.type ?? 1,
        level: (data.level ?? 'info') as 'info' | 'success' | 'warning' | 'danger',
        title: data.title ?? '',
        content: data.content ?? '',
        biz_type: data.biz_type ?? '',
        biz_id: data.biz_id ?? '',
        link: data.link ?? '',
        expire_time: data.expire_time ?? ''
      })
      if (data.user_id) {
        userOptions.value = [
          {
            label:
              (data.user_nickname && data.username && data.user_nickname !== data.username
                ? `${data.username}（${data.user_nickname}）`
                : data.username) || `#${data.user_id}`,
            value: data.user_id
          }
        ]
      }
    } catch (e) {
      console.error('加载通知详情失败', e)
    }
  }

  /**
   * 提交
   */
  const handleSubmit = async (): Promise<void> => {
    if (!formRef.value) return
    try {
      await formRef.value.validate()
    } catch {
      ElMessage.error('请检查表单填写')
      return
    }
    submitting.value = true
    try {
      if (mode.value === 'edit' && props.editId) {
        const payload: NoticeSubmitParams = {
          type: form.type,
          level: form.level,
          title: form.title,
          content: form.content,
          biz_type: form.biz_type,
          biz_id: form.biz_id,
          link: form.link,
          expire_time: form.expire_time
        }
        await fetchUpdateNotice(props.editId, payload)
        ElMessage.success('保存成功')
      } else {
        const payload: NoticeSubmitParams = {
          user_id: form.user_id,
          type: form.type,
          level: form.level,
          title: form.title,
          content: form.content,
          biz_type: form.biz_type,
          biz_id: form.biz_id,
          link: form.link,
          expire_time: form.expire_time
        }
        await fetchCreateNotice(payload)
        ElMessage.success('发送成功')
      }
      emit('success')
      handleClose()
    } catch (e) {
      if (import.meta.env.DEV) {
        console.error('保存通知失败', e)
      }
    } finally {
      submitting.value = false
    }
  }

  const handleClose = (): void => {
    innerVisible.value = false
  }

  watch(
    () => props.visible,
    (val) => {
      if (val) {
        handleReset()
        if (mode.value === 'edit' && props.editId) {
          loadEditData(props.editId)
        } else {
          // 预热空选项
          handleSearchUsers('')
        }
      }
    },
    { immediate: true }
  )
</script>

<style scoped lang="scss">
  .notice-send-dialog {
    :deep(.el-dialog__body) {
      padding-top: 16px;
    }
  }

  .notice-send-side {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .notice-send-side-block {
    padding: 14px 14px 4px;
    background: var(--el-fill-color-blank);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 6px;
  }

  .notice-send-side-label {
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--el-text-color-regular);

    .required {
      color: var(--el-color-danger);
      margin-left: 2px;
    }
  }

  .notice-send-side-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .notice-send-side-tip {
    margin-top: 6px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  :deep(.el-form-item__label) {
    font-weight: 500;
  }
</style>
