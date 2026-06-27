<!-- 系统公告发布弹窗 -->
<template>
  <ElDialog
    v-model="innerVisible"
    :title="mode === 'edit' ? '编辑系统公告' : '发布系统公告'"
    width="1100px"
    :close-on-click-modal="false"
    destroy-on-close
    class="ann-publish-dialog"
    @close="handleClose"
  >
    <ElForm
      ref="formRef"
      :model="form"
      :rules="rules"
      label-position="top"
      class="ann-publish-form"
    >
      <ElRow :gutter="20">
        <ElCol :xs="24" :md="16">
          <!-- 标题 -->
          <ElFormItem label="公告标题" prop="title">
            <ElInput
              v-model="form.title"
              placeholder="请输入公告标题（最多 200 字）"
              maxlength="200"
              show-word-limit
              clearable
              size="large"
            />
          </ElFormItem>

          <!-- 内容 -->
          <ElFormItem label="公告内容" prop="content">
            <ArtWangEditor
              v-model="form.content"
              :height="'380px'"
              placeholder="请输入公告正文（支持富文本、图片等）"
              :exclude-keys="['fontFamily', 'codeBlock', 'video']"
            />
          </ElFormItem>
        </ElCol>

        <ElCol :xs="24" :md="8">
          <div class="ann-publish-side">
            <!-- 基础属性 -->
            <div class="ann-publish-side-block">
              <div class="ann-publish-side-label">基础属性</div>
              <div class="ann-publish-side-grid">
                <div>
                  <div class="ann-publish-side-sublabel">分类 <span class="required">*</span></div>
                  <ElSelect v-model="form.category" placeholder="请选择" style="width: 100%">
                    <ElOption :label="'通知'" value="notice" />
                    <ElOption :label="'公告'" value="announcement" />
                    <ElOption :label="'活动'" value="activity" />
                    <ElOption :label="'维护'" value="maintenance" />
                  </ElSelect>
                </div>
                <div>
                  <div class="ann-publish-side-sublabel">级别 <span class="required">*</span></div>
                  <ElSelect v-model="form.level" placeholder="请选择" style="width: 100%">
                    <ElOption :label="'普通'" value="info" />
                    <ElOption :label="'重要'" value="important" />
                    <ElOption :label="'紧急'" value="urgent" />
                  </ElSelect>
                </div>
              </div>
              <div class="ann-publish-side-row">
                <span>显示排序</span>
                <ElInputNumber
                  v-model="form.sort"
                  :min="0"
                  :max="9999"
                  controls-position="right"
                  placeholder="数字越小越靠前"
                  style="width: 100%"
                />
              </div>
              <div class="ann-publish-side-row">
                <span>是否置顶</span>
                <ElSwitch
                  v-model="form.is_top"
                  :active-value="1"
                  :inactive-value="0"
                  inline-prompt
                  active-text="是"
                  inactive-text="否"
                />
              </div>
              <div class="ann-publish-side-row">
                <span>登录强提示</span>
                <ElSwitch
                  v-model="form.is_popup"
                  :active-value="1"
                  :inactive-value="0"
                  inline-prompt
                  active-text="是"
                  inactive-text="否"
                />
              </div>
            </div>

            <!-- 生效设置 -->
            <div class="ann-publish-side-block">
              <div class="ann-publish-side-label">生效设置</div>
              <div class="ann-publish-side-row column">
                <span>生效时间</span>
                <ElDatePicker
                  v-model="form.effective_at"
                  type="datetime"
                  placeholder="为空则立即生效"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  format="YYYY-MM-DD HH:mm:ss"
                  style="width: 100%"
                />
              </div>
              <div class="ann-publish-side-row column">
                <span>失效时间</span>
                <ElDatePicker
                  v-model="form.expire_at"
                  type="datetime"
                  placeholder="为空则长期有效"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  format="YYYY-MM-DD HH:mm:ss"
                  style="width: 100%"
                />
              </div>
            </div>

            <!-- 备注 -->
            <div class="ann-publish-side-block">
              <div class="ann-publish-side-label">备注</div>
              <ElInput
                v-model="form.remark"
                type="textarea"
                :rows="3"
                placeholder="可选，仅管理员可见"
                maxlength="500"
                show-word-limit
              />
            </div>
          </div>
        </ElCol>
      </ElRow>
    </ElForm>

    <template #footer>
      <ElButton @click="handleClose">取消</ElButton>
      <ElButton @click="handleReset">重置</ElButton>
      <ElButton @click="handleSaveDraft" :loading="saving">保存草稿</ElButton>
      <ElButton type="primary" :loading="publishing" @click="handlePublish">
        {{ mode === 'edit' ? '保存并发布' : '立即发布' }}
      </ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
  import {
    fetchCreateAnnouncement,
    fetchUpdateAnnouncement,
    fetchGetAnnouncement,
    fetchPublishAnnouncement
  } from '@/api/system-manage'
  import ArtWangEditor from '@/components/core/forms/art-wang-editor/index.vue'

  defineOptions({ name: 'AnnouncementPublishDialog' })

  type AnnouncementListItem = Api.SystemManage.AnnouncementListItem

  interface AnnouncementForm {
    title: string
    content: string
    category: 'notice' | 'announcement' | 'activity' | 'maintenance'
    level: 'info' | 'important' | 'urgent'
    is_top: 0 | 1
    is_popup: 0 | 1
    status: 0 | 1 | 2
    effective_at: string
    expire_at: string
    sort: number
    remark: string
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
  const saving = ref(false)
  const publishing = ref(false)

  const form = reactive<AnnouncementForm>({
    title: '',
    content: '',
    category: 'announcement',
    level: 'info',
    is_top: 0,
    is_popup: 0,
    status: 0,
    effective_at: '',
    expire_at: '',
    sort: 0,
    remark: ''
  })

  const rules = reactive<FormRules>({
    title: [
      { required: true, message: '请输入公告标题', trigger: 'blur' },
      { max: 200, message: '标题不能超过 200 字', trigger: 'blur' }
    ],
    content: [
      {
        validator: (_rule, value, callback) => {
          if (!value || value.replace(/<[^>]*>/g, '').trim() === '') {
            callback(new Error('请输入公告内容'))
            return
          }
          callback()
        },
        trigger: 'blur'
      }
    ],
    category: [{ required: true, message: '请选择分类', trigger: 'change' }],
    level: [{ required: true, message: '请选择级别', trigger: 'change' }],
    expire_at: [
      {
        validator: (_rule, _value, callback) => {
          if (!form.effective_at || !form.expire_at) {
            callback()
            return
          }
          if (new Date(form.effective_at).getTime() >= new Date(form.expire_at).getTime()) {
            callback(new Error('失效时间必须晚于生效时间'))
            return
          }
          callback()
        },
        trigger: 'change'
      }
    ]
  })

  /**
   * 校验
   */
  const validate = async (): Promise<boolean> => {
    if (!formRef.value) return false
    try {
      await formRef.value.validate()
    } catch {
      ElMessage.error('请检查表单填写')
      return false
    }
    return true
  }

  /**
   * 重置
   */
  const handleReset = (): void => {
    if (mode.value === 'edit') return
    formRef.value?.resetFields()
    Object.assign(form, {
      title: '',
      content: '',
      category: 'announcement',
      level: 'info',
      is_top: 0,
      is_popup: 0,
      status: 0,
      effective_at: '',
      expire_at: '',
      sort: 0,
      remark: ''
    })
  }

  /**
   * 保存草稿
   */
  const handleSaveDraft = async (): Promise<void> => {
    if (!(await validate())) return
    saving.value = true
    try {
      if (mode.value === 'edit' && props.editId) {
        await fetchUpdateAnnouncement(props.editId, { ...form, status: 0 })
        ElMessage.success('已保存草稿')
      } else {
        await fetchCreateAnnouncement({ ...form, status: 0 })
        ElMessage.success('已保存草稿')
      }
      emit('success')
      handleClose()
    } catch (e) {
      console.error('保存草稿失败', e)
    } finally {
      saving.value = false
    }
  }

  /**
   * 立即发布
   */
  const handlePublish = async (): Promise<void> => {
    if (!(await validate())) return
    publishing.value = true
    try {
      if (mode.value === 'edit' && props.editId) {
        // 编辑模式：先保存再发布
        await fetchUpdateAnnouncement(props.editId, { ...form })
        await fetchPublishAnnouncement(props.editId)
        ElMessage.success('已发布')
      } else {
        // 新建：创建后立即发布
        const res = await fetchCreateAnnouncement({ ...form })
        const newId = res.id
        if (!newId) {
          throw new Error('创建后未返回 id')
        }
        try {
          await fetchPublishAnnouncement(newId)
          ElMessage.success('已发布')
        } catch {
          ElMessage.warning('草稿已保存，但发布失败，请到列表中重试发布')
        }
      }
      emit('success')
      handleClose()
    } catch (e) {
      if (import.meta.env.DEV) {
        console.error('发布失败', e)
      }
    } finally {
      publishing.value = false
    }
  }

  const handleClose = (): void => {
    innerVisible.value = false
  }

  /**
   * 加载编辑数据
   */
  const loadEditData = async (id: number): Promise<void> => {
    try {
      const data: AnnouncementListItem = await fetchGetAnnouncement(id)
      Object.assign(form, {
        title: data.title,
        content: data.content,
        category: data.category,
        level: data.level,
        is_top: data.is_top ?? 0,
        is_popup: data.is_popup ?? 0,
        status: data.status,
        effective_at: data.effective_at ?? '',
        expire_at: data.expire_at ?? '',
        sort: data.sort ?? 0,
        remark: data.remark ?? ''
      })
    } catch (e) {
      console.error('加载公告详情失败', e)
    }
  }

  watch(
    () => props.visible,
    (val) => {
      if (val) {
        handleReset()
        if (mode.value === 'edit' && props.editId) {
          loadEditData(props.editId)
        }
      }
    },
    { immediate: true }
  )
</script>

<style scoped lang="scss">
  .ann-publish-dialog {
    :deep(.el-dialog__body) {
      padding-top: 16px;
    }
  }

  .ann-publish-side {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .ann-publish-side-block {
    padding: 14px;
    background: var(--el-fill-color-blank);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 6px;
  }

  .ann-publish-side-label {
    margin-bottom: 12px;
    font-size: 13px;
    font-weight: 600;
    color: var(--el-text-color-regular);
  }

  .ann-publish-side-sublabel {
    margin-bottom: 6px;
    font-size: 12px;
    color: var(--el-text-color-regular);

    .required {
      color: var(--el-color-danger);
      margin-left: 2px;
    }
  }

  .ann-publish-side-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 12px;
  }

  .ann-publish-side-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    font-size: 13px;
    color: var(--el-text-color-regular);

    &:last-child {
      margin-bottom: 0;
    }

    &.column {
      flex-direction: column;
      align-items: stretch;
      gap: 6px;
    }
  }

  :deep(.el-form-item) {
    margin-bottom: 18px;
  }

  :deep(.el-form-item__label) {
    font-weight: 500;
  }
</style>
