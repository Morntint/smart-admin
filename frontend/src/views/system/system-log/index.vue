<!-- 系统日志 - 包含操作日志和登录日志两个选项卡 -->
<template>
  <div class="system-log art-full-height">
    <div class="system-log-tabs-wrapper">
      <ElTabs v-model="activeTab" class="system-log-tabs">
        <ElTabPane :name="operationName">
          <template #label>
            <span class="tab-label">
              <ArtSvgIcon icon="ri:file-list-2-line" :size="16" />
              <span>{{ t('menus.system.operationLog') }}</span>
            </span>
          </template>
        </ElTabPane>
        <ElTabPane :name="loginName">
          <template #label>
            <span class="tab-label">
              <ArtSvgIcon icon="ri:login-circle-line" :size="16" />
              <span>{{ t('menus.system.loginLog') }}</span>
            </span>
          </template>
        </ElTabPane>
      </ElTabs>
    </div>

    <div class="system-log-body">
      <OperationLogPanel v-show="activeTab === 'operation'" />
      <LoginLogPanel v-show="activeTab === 'login'" />
    </div>
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from 'vue-i18n'
  import OperationLogPanel from './modules/operation-log-panel.vue'
  import LoginLogPanel from './modules/login-log-panel.vue'
  import ArtSvgIcon from '@/components/core/base/art-svg-icon/index.vue'

  defineOptions({ name: 'SystemLog' })

  const { t } = useI18n()

  /** 选项卡名称常量（避免魔法字符串） */
  const operationName = 'operation'
  const loginName = 'login'

  /**
   * 当前激活的选项卡
   * - operation: 操作日志
   * - login: 登录日志
   */
  const activeTab = ref<'operation' | 'login'>(operationName)
</script>

<style scoped lang="scss">
  .system-log {
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .system-log-tabs-wrapper {
    flex-shrink: 0;
    padding: 0 4px;
    background-color: var(--default-box-color);

    .system-log-tabs {
      :deep(.el-tabs__header) {
        margin: 0;
      }

      :deep(.el-tabs__nav-wrap)::after {
        background-color: transparent;
      }

      :deep(.el-tabs__item) {
        height: 44px;
        padding: 0 20px;
        font-size: 14px;
        line-height: 44px;
        color: var(--el-text-color-regular);
        transition: color 0.2s ease;

        &:hover {
          color: var(--el-color-primary);
        }
      }

      :deep(.el-tabs__item.is-active) {
        color: var(--el-color-primary);
        font-weight: 600;
      }

      :deep(.el-tabs__active-bar) {
        height: 2px;
        background-color: var(--el-color-primary);
        border-radius: 2px;
      }
    }
  }

  .system-log-body {
    flex: 1 1 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .tab-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
</style>
