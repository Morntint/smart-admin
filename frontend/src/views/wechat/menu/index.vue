<!-- 微信菜单管理页面 -->
<template>
  <div class="wechat-menu-page art-full-height">
    <ElRow :gutter="20">
      <!-- 左侧：菜单预览 -->
      <ElCol :span="8">
        <ElCard class="menu-preview-card">
          <template #header>
            <div class="card-header">
              <span>菜单预览</span>
              <ElSelect
                v-model="previewAppType"
                size="small"
                style="width: 120px"
                @change="loadMenuConfig"
              >
                <ElOption label="公众号" value="official_account" />
                <ElOption label="小程序" value="mini_program" />
              </ElSelect>
            </div>
          </template>

          <!-- 手机模拟器 -->
          <div class="phone-simulator">
            <div class="phone-screen">
              <div class="phone-header">
                <div class="phone-camera"></div>
                <div class="phone-speaker"></div>
              </div>
              <div class="screen-content">
                <div class="menu-area">
                  <div class="menu-header-bar">
                    <span class="menu-title">{{ appName }}</span>
                  </div>
                </div>
              </div>
              <div class="menu-bottom-bar">
                <div
                  v-for="(button, index) in menuConfig.button"
                  :key="index"
                  class="menu-main-button"
                  :class="{ 'has-sub': button.sub_button && button.sub_button.length > 0 }"
                  @click="selectMainButton(index)"
                >
                  <el-icon v-if="button.type === 'view'" class="menu-icon"><Link /></el-icon>
                  <el-icon v-else-if="button.type === 'miniprogram'" class="menu-icon"
                    ><Promotion
                  /></el-icon>
                  <el-icon v-else class="menu-icon"><Grid /></el-icon>
                  <span class="menu-name">{{ button.name }}</span>

                  <!-- 子菜单弹出层 -->
                  <div
                    v-if="button.sub_button && button.sub_button.length > 0"
                    class="sub-menu-popup"
                    :class="{ show: selectedMainIndex === index && showSubMenu }"
                  >
                    <div
                      v-for="(subButton, subIndex) in button.sub_button"
                      :key="subIndex"
                      class="sub-menu-item"
                      @click.stop="selectSubButton(index, subIndex)"
                    >
                      <el-icon v-if="subButton.type === 'view'" class="sub-menu-icon"
                        ><Link
                      /></el-icon>
                      <el-icon v-else-if="subButton.type === 'miniprogram'" class="sub-menu-icon"
                        ><Promotion
                      /></el-icon>
                      <el-icon v-else class="sub-menu-icon"><Grid /></el-icon>
                      <span>{{ subButton.name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="preview-tips">
            <el-icon><InfoFilled /></el-icon>
            <span>点击左侧菜单按钮进行编辑，最多支持 3 个主菜单，每个主菜单最多 5 个子菜单</span>
          </div>
        </ElCard>
      </ElCol>

      <!-- 右侧：菜单编辑 -->
      <ElCol :span="16">
        <ElCard class="menu-edit-card">
          <template #header>
            <div class="card-header">
              <span>菜单编辑</span>
              <ElSpace>
                <ElButton
                  @click="addMainMenu"
                  :disabled="menuConfig.button.length >= 3"
                  v-auth="'wechat:menu:edit'"
                >
                  <el-icon class="mr-1"><Plus /></el-icon>添加主菜单
                </ElButton>
                <ElButton @click="loadMenuConfig" :loading="loading">
                  <el-icon class="mr-1"><Refresh /></el-icon>刷新
                </ElButton>
                <ElButton
                  type="primary"
                  @click="publishMenu"
                  :loading="publishing"
                  v-auth="'wechat:menu:publish'"
                >
                  <el-icon class="mr-1"><Upload /></el-icon>发布菜单
                </ElButton>
              </ElSpace>
            </div>
          </template>

          <!-- 空状态 -->
          <div v-if="!selectedButton && menuConfig.button.length === 0" class="empty-state">
            <el-empty description="点击右上角 + 添加菜单，或点击预览区菜单按钮编辑" />
          </div>

          <!-- 编辑表单 -->
          <ElForm
            v-if="selectedButton"
            ref="formRef"
            :model="selectedButton"
            :rules="formRules"
            label-width="100px"
          >
            <ElFormItem label="菜单名称" prop="name">
              <ElInput
                v-model="selectedButton.name"
                placeholder="请输入菜单名称，不超过16个字"
                maxlength="16"
              />
            </ElFormItem>

            <ElFormItem label="菜单类型">
              <ElRadioGroup v-model="selectedButton.type">
                <ElRadio value="click">点击推事件</ElRadio>
                <ElRadio value="view">跳转URL</ElRadio>
                <ElRadio value="miniprogram">跳转小程序</ElRadio>
                <ElRadio value="scancode_push">扫码推事件</ElRadio>
                <ElRadio value="scancode_waitmsg">扫码推事件且弹出"消息接收中"</ElRadio>
                <ElRadio value="pic_sysphoto">弹出系统拍照发图</ElRadio>
                <ElRadio value="pic_photo_or_album">弹出拍照或者相册发图</ElRadio>
                <ElRadio value="pic_weixin">弹出微信相册发图器</ElRadio>
                <ElRadio value="location_select">弹出地理位置选择器</ElRadio>
                <ElRadio value="media_id">下发消息（除文本消息）</ElRadio>
                <ElRadio value="view_limited">跳转图文消息</ElRadio>
              </ElRadioGroup>
            </ElFormItem>

            <ElFormItem v-if="selectedButton.type === 'view'" label="跳转URL" prop="url">
              <ElInput v-model="selectedButton.url" placeholder="必须以 http:// 或 https:// 开头" />
            </ElFormItem>

            <ElFormItem v-if="selectedButton.type === 'click'" label="事件KEY" prop="key">
              <ElInput
                v-model="selectedButton.key"
                placeholder="字母、数字、下划线，用于消息接口推送"
              />
            </ElFormItem>

            <ElFormItem
              v-if="selectedButton.type === 'miniprogram'"
              label="小程序AppID"
              prop="appid"
            >
              <ElInput v-model="selectedButton.appid" placeholder="请输入小程序AppID" />
            </ElFormItem>

            <ElFormItem
              v-if="selectedButton.type === 'miniprogram'"
              label="页面路径"
              prop="pagepath"
            >
              <ElInput v-model="selectedButton.pagepath" placeholder="如：pages/index/index" />
            </ElFormItem>

            <ElFormItem
              v-if="['media_id', 'view_limited'].includes(selectedButton.type!)"
              label="素材ID"
              prop="media_id"
            >
              <ElInput v-model="selectedButton.media_id" placeholder="请输入永久素材ID" />
            </ElFormItem>

            <ElFormItem
              v-if="selectedSubIndex === null && (selectedButton.sub_button?.length ?? 0) < 5"
            >
              <ElButton size="small" @click="addSubMenu" v-auth="'wechat:menu:edit'">
                <el-icon class="mr-1"><Plus /></el-icon>添加子菜单
              </ElButton>
            </ElFormItem>

            <ElFormItem>
              <ElButton
                type="primary"
                @click="saveMenuConfig"
                :loading="saving"
                v-auth="'wechat:menu:edit'"
                >保存配置</ElButton
              >
              <ElButton type="danger" @click="deleteButton" v-auth="'wechat:menu:edit'"
                >删除菜单</ElButton
              >
              <ElButton @click="cancelEdit">取消编辑</ElButton>
            </ElFormItem>
          </ElForm>

          <!-- 菜单结构列表 -->
          <div v-if="menuConfig.button.length > 0" class="menu-structure">
            <h4>菜单结构</h4>
            <ElTree
              :data="menuTreeData"
              :props="{ label: 'name', children: 'sub_button' }"
              node-key="id"
              @node-click="handleTreeNodeClick"
              default-expand-all
            />
          </div>
        </ElCard>
      </ElCol>
    </ElRow>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, onMounted, nextTick } from 'vue'
  import { fetchWeChatMenuConfig, publishWeChatMenu, saveWeChatMenu } from '@/api/wechat'
  import { ElMessage, ElMessageBox, ElEmpty, ElTree } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import { Refresh, Upload, Grid, Link, Promotion, InfoFilled, Plus } from '@element-plus/icons-vue'

  defineOptions({ name: 'WeChatMenu' })

  type MenuButton = {
    id?: string
    name: string
    type?: string
    key?: string
    url?: string
    appid?: string
    pagepath?: string
    media_id?: string
    sub_button?: MenuButton[]
  }

  const loading = ref(false)
  const publishing = ref(false)
  const saving = ref(false)
  const previewAppType = ref<'official_account' | 'mini_program'>('official_account')
  const appName = computed(() =>
    previewAppType.value === 'mini_program' ? '微信小程序' : '微信公众号'
  )

  const formRef = ref<FormInstance>()
  const showSubMenu = ref(false)
  const selectedMainIndex = ref<number | null>(null)
  const selectedSubIndex = ref<number | null>(null)
  const selectedButton = ref<MenuButton | null>(null)

  // 初始为空数组——避免在 loadMenuConfig 返回前向 publish 接口提交模拟数据
  const menuConfig = reactive<{ button: MenuButton[] }>({ button: [] })

  // 表单校验规则
  const formRules = computed<FormRules>(() => {
    const b = selectedButton.value
    const rules: FormRules = {
      name: [{ required: true, message: '请输入菜单名称', trigger: 'blur' }]
    }
    if (b?.type === 'view') {
      rules.url = [
        { required: true, message: '请输入跳转 URL', trigger: 'blur' },
        { pattern: /^https?:\/\//, message: '必须以 http:// 或 https:// 开头', trigger: 'blur' }
      ]
    }
    if (b?.type === 'click') {
      rules.key = [
        { required: true, message: '请输入事件 KEY', trigger: 'blur' },
        { pattern: /^[A-Za-z0-9_]+$/, message: '仅支持字母、数字、下划线', trigger: 'blur' }
      ]
    }
    if (b?.type === 'miniprogram') {
      rules.appid = [{ required: true, message: '请输入小程序 AppID', trigger: 'blur' }]
      rules.pagepath = [{ required: true, message: '请输入页面路径', trigger: 'blur' }]
    }
    if (b?.type === 'media_id' || b?.type === 'view_limited') {
      rules.media_id = [{ required: true, message: '请输入素材 ID', trigger: 'blur' }]
    }
    return rules
  })

  const menuTreeData = computed(() => {
    return menuConfig.button.map((btn, index) => ({
      id: `btn_${index}`,
      name: btn.name,
      sub_button: btn.sub_button?.map((subBtn, subIndex) => ({
        id: `btn_${index}_${subIndex}`,
        name: subBtn.name
      }))
    }))
  })

  onMounted(() => {
    loadMenuConfig()
  })

  /**
   * 加载菜单配置
   */
  const loadMenuConfig = async (): Promise<void> => {
    loading.value = true
    cancelEdit()
    try {
      const config: any = await fetchWeChatMenuConfig({ app_type: previewAppType.value })
      // 后端返回的是 wechat_menu 树（含 parent_id），需转成微信发布格式
      menuConfig.button = normalizeFromBackend(config)
    } catch (error) {
      console.error('加载菜单配置失败:', error)
      ElMessage.error('加载菜单配置失败')
    } finally {
      loading.value = false
    }
  }

  /**
   * 后端菜单树 → 微信 button 结构
   */
  function normalizeFromBackend(raw: any): MenuButton[] {
    if (!raw) return []
    const list = Array.isArray(raw) ? raw : (raw.button ?? raw.list ?? [])
    return (list as any[]).map((node) => {
      const item: MenuButton = {
        name: node.name,
        type: node.type ?? undefined,
        key: node.key ?? undefined,
        url: node.url ?? undefined,
        appid: node.appid ?? undefined,
        pagepath: node.pagepath ?? undefined
      }
      const children = node.children ?? node.sub_button ?? []
      if (Array.isArray(children) && children.length > 0) {
        item.sub_button = normalizeFromBackend(children)
        // 父菜单不带 type
        item.type = undefined
      }
      return item
    })
  }

  /**
   * 保存菜单配置（仅入库，不发布）
   */
  const saveMenuConfig = async (): Promise<void> => {
    if (formRef.value) {
      try {
        await formRef.value.validate()
      } catch {
        return
      }
    }
    saving.value = true
    try {
      await saveWeChatMenu({ app_type: previewAppType.value, button: menuConfig.button })
      ElMessage.success('配置已保存')
      cancelEdit()
    } catch (error: any) {
      console.error('保存失败:', error)
      ElMessage.error('保存失败：' + (error?.message ?? '请稍后重试'))
    } finally {
      saving.value = false
    }
  }

  /**
   * 发布菜单
   */
  const publishMenu = async (): Promise<void> => {
    if (menuConfig.button.length === 0) {
      ElMessage.warning('请先添加至少一个菜单')
      return
    }

    try {
      await ElMessageBox.confirm(
        '确定要发布当前菜单配置到微信服务器吗？发布后用户将看到新菜单。',
        '发布确认',
        {
          confirmButtonText: '确定发布',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
    } catch {
      ElMessage.info('已取消发布')
      return
    }

    publishing.value = true
    try {
      await publishWeChatMenu({ app_type: previewAppType.value, button: menuConfig.button })
      ElMessage.success('菜单发布成功')
    } catch (error: any) {
      console.error('发布失败:', error)
      ElMessage.error('发布失败：' + (error?.message ?? '请稍后重试'))
    } finally {
      publishing.value = false
    }
  }

  /**
   * 添加主菜单
   */
  const addMainMenu = (): void => {
    if (menuConfig.button.length >= 3) {
      ElMessage.warning('最多 3 个主菜单')
      return
    }
    menuConfig.button.push({
      name: '新菜单',
      type: 'click',
      key: 'NEW_KEY_' + (menuConfig.button.length + 1)
    })
    selectMainButton(menuConfig.button.length - 1)
  }

  /**
   * 添加子菜单
   */
  const addSubMenu = (): void => {
    if (selectedMainIndex.value === null) return
    const main = menuConfig.button[selectedMainIndex.value]
    main.sub_button = main.sub_button ?? []
    if (main.sub_button.length >= 5) {
      ElMessage.warning('每个主菜单最多 5 个子菜单')
      return
    }
    main.sub_button.push({ name: '新子菜单', type: 'click', key: 'SUB_KEY' })
    // 父菜单转为容器型，清掉单条配置字段
    main.type = undefined
    main.key = undefined
    main.url = undefined
  }

  /**
   * 选择主菜单
   */
  const selectMainButton = (index: number): void => {
    const button = menuConfig.button[index]
    selectedMainIndex.value = index
    selectedSubIndex.value = null
    selectedButton.value = button
    if (button.sub_button && button.sub_button.length > 0) {
      showSubMenu.value = true
    } else {
      showSubMenu.value = false
    }
  }

  /**
   * 选择子菜单
   */
  const selectSubButton = (mainIndex: number, subIndex: number): void => {
    selectedMainIndex.value = mainIndex
    selectedSubIndex.value = subIndex
    selectedButton.value = menuConfig.button[mainIndex].sub_button![subIndex]
    nextTick(() => {
      showSubMenu.value = false
    })
  }

  /**
   * 树节点点击
   */
  const handleTreeNodeClick = (data: any): void => {
    const id = data.id as string
    if (id.startsWith('btn_')) {
      const parts = id.split('_')
      if (parts.length === 2) {
        const index = parseInt(parts[1])
        selectMainButton(index)
      } else if (parts.length === 3) {
        const mainIndex = parseInt(parts[1])
        const subIndex = parseInt(parts[2])
        selectSubButton(mainIndex, subIndex)
      }
    }
  }

  /**
   * 删除当前选中的按钮
   */
  const deleteButton = (): void => {
    if (selectedMainIndex.value === null) return

    ElMessageBox.confirm('确定要删除此菜单吗？', '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(() => {
        if (selectedSubIndex.value !== null) {
          menuConfig.button[selectedMainIndex.value!].sub_button?.splice(selectedSubIndex.value, 1)
        } else {
          menuConfig.button.splice(selectedMainIndex.value!, 1)
        }
        cancelEdit()
        ElMessage.success('已删除')
      })
      .catch(() => {})
  }

  /**
   * 取消编辑
   */
  const cancelEdit = (): void => {
    selectedButton.value = null
    selectedMainIndex.value = null
    selectedSubIndex.value = null
    showSubMenu.value = false
  }
</script>

<style scoped lang="scss">
  .wechat-menu-page {
    display: flex;
    flex-direction: column;
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  /* 手机模拟器 */
  .phone-simulator {
    display: flex;
    justify-content: center;
    padding: 20px;

    .phone-screen {
      width: 280px;
      height: 500px;
      background: linear-gradient(180deg, #f7f7f7 0%, #fff 100%);
      border-radius: 36px;
      border: 8px solid #333;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);

      .phone-header {
        position: absolute;
        top: 12px;
        left: 0;
        right: 0;
        height: 36px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 2;

        .phone-camera {
          width: 8px;
          height: 8px;
          border-radius: 50%;
          background: #333;
        }

        .phone-speaker {
          width: 50px;
          height: 5px;
          border-radius: 3px;
          background: #333;
          margin-top: 6px;
        }
      }

      .screen-content {
        height: calc(100% - 50px);
        overflow: hidden;
      }

      .menu-header-bar {
        height: 44px;
        background: #07c160;
        display: flex;
        align-items: center;
        padding: 0 16px;

        .menu-title {
          color: #fff;
          font-size: 17px;
          font-weight: 500;
        }
      }

      .menu-bottom-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50px;
        background: #fff;
        border-top: 1px solid #e5e5e5;
        display: flex;
        align-items: center;

        .menu-main-button {
          flex: 1;
          height: 100%;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          border-right: 1px solid #e5e5e5;
          cursor: pointer;
          position: relative;
          transition: background 0.2s;

          &:last-child {
            border-right: none;
          }

          &:hover {
            background: #f5f5f5;
          }

          .menu-icon {
            font-size: 16px;
            color: #333;
            margin-bottom: 2px;
          }

          .menu-name {
            font-size: 11px;
            color: #333;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }

          .sub-menu-popup {
            position: absolute;
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateX(-50%) translateY(10px);
            transition: all 0.25s ease;

            &.show {
              opacity: 1;
              visibility: visible;
              transform: translateX(-50%) translateY(0);
            }

            .sub-menu-item {
              padding: 12px 16px;
              border-bottom: 1px solid #f0f0f0;
              display: flex;
              align-items: center;
              gap: 8px;
              cursor: pointer;
              transition: background 0.2s;

              &:last-child {
                border-bottom: none;
              }

              &:hover {
                background: #f5f5f5;
              }

              .sub-menu-icon {
                font-size: 14px;
                color: #666;
              }

              span {
                font-size: 13px;
                color: #333;
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
              }
            }

            &::after {
              content: '';
              position: absolute;
              bottom: -6px;
              left: 50%;
              transform: translateX(-50%);
              border-left: 6px solid transparent;
              border-right: 6px solid transparent;
              border-top: 6px solid #fff;
            }
          }
        }
      }
    }
  }

  .preview-tips {
    display: flex;
    gap: 8px;
    padding: 12px;
    background: var(--el-fill-color-light);
    border-radius: 6px;
    margin-top: 16px;
    font-size: 12px;
    color: var(--el-text-color-secondary);

    .el-icon {
      flex-shrink: 0;
      color: var(--el-color-primary);
    }
  }

  .empty-state {
    padding: 60px 20px;
  }

  .menu-structure {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--el-border-color-lighter);

    h4 {
      margin: 0 0 12px 0;
      font-size: 14px;
      font-weight: 500;
      color: var(--el-text-color-primary);
    }
  }
</style>
