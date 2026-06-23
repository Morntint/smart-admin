<!-- 服务器监控 -->
<template>
  <div class="server-monitor art-full-height">
    <!-- 顶部操作栏 -->
    <div class="art-card mb-5 flex items-center justify-between px-5 py-3.5 max-sm:mb-4">
      <div class="flex items-center">
        <div
          class="mr-2.5 size-2.5 rounded-full"
          :class="serverStatus.running ? 'bg-success animate-pulse' : 'bg-danger'"
        />
        <span class="text-base font-medium text-g-900">
          {{ serverStatus.running ? '服务运行中' : '服务已停止' }}
        </span>
        <ElTag class="ml-3" :type="serverStatus.running ? 'success' : 'danger'" effect="light" size="small">
          {{ serverStatus.hostname }}
        </ElTag>
        <span class="ml-3 text-xs text-g-500">最后更新：{{ lastUpdateText }}</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs text-g-500 max-sm:hidden">自动刷新</span>
        <ElSwitch v-model="autoRefresh" inline-prompt active-text="开" inactive-text="关" />
        <ElButton
          :icon="loading ? undefined : 'ri:refresh-line'"
          :loading="loading"
          @click="fetchData"
          v-ripple
        >
          刷新
        </ElButton>
      </div>
    </div>

    <!-- 核心指标卡片 -->
    <ElRow :gutter="20" class="flex">
      <ElCol v-for="item in statCards" :key="item.key" :sm="12" :md="12" :lg="6">
        <div
          class="art-card relative mb-5 flex h-30 flex-col justify-center px-5 transition-transform duration-200 hover:-translate-y-0.5 max-sm:mb-4"
        >
          <span class="text-g-700 text-sm">{{ item.label }}</span>
          <div class="mt-2 flex items-baseline">
            <ArtCountTo
              class="text-[28px] font-medium leading-none"
              :target="item.value"
              :duration="800"
              :decimals="item.decimals ?? 1"
            />
            <span class="ml-1 text-base text-g-500">{{ item.unit }}</span>
          </div>
          <div class="mt-2 flex items-center text-xs">
            <span class="text-g-500">总容量：</span>
            <span class="font-medium text-g-700">{{ item.total }}</span>
            <span
              class="ml-3"
              :class="item.value > 80 ? 'text-danger' : item.value > 60 ? 'text-warning' : 'text-success'"
            >
              {{ item.value > 80 ? '高负载' : item.value > 60 ? '中等' : '正常' }}
            </span>
          </div>
          <div
            class="absolute top-0 bottom-0 right-5 m-auto flex size-12.5 items-center justify-center rounded-xl"
            :class="item.iconBg"
          >
            <ArtSvgIcon :icon="item.icon" class="text-xl" :class="item.iconColor" />
          </div>
        </div>
      </ElCol>
    </ElRow>

    <!-- CPU / 内存 实时折线 -->
    <ElRow :gutter="20">
      <ElCol :sm="24" :md="24" :lg="12">
        <div class="art-card mb-5 h-95 p-5 max-sm:mb-4">
          <div class="art-card-header">
            <div class="title">
              <h4>CPU 使用率</h4>
              <p>实时监控，{{ history.cpu.length }} 个采样点</p>
            </div>
            <ArtLineChart
              height="calc(100% - 50px)"
              :data="cpuChartData"
              :xAxisData="history.cpu.map((_, i) => String(i + 1))"
              :showAreaColor="true"
              color="#409EFF"
              :showAxisLine="false"
            />
          </div>
        </div>
      </ElCol>
      <ElCol :sm="24" :md="24" :lg="12">
        <div class="art-card mb-5 h-95 p-5 max-sm:mb-4">
          <div class="art-card-header">
            <div class="title">
              <h4>内存使用率</h4>
              <p>实时监控，已用 {{ formatBytes(memoryInfo.used) }} / {{ formatBytes(memoryInfo.total) }}</p>
            </div>
            <ArtLineChart
              height="calc(100% - 50px)"
              :data="memoryChartData"
              :xAxisData="history.memory.map((_, i) => String(i + 1))"
              :showAreaColor="true"
              color="#67C23A"
              :showAxisLine="false"
            />
          </div>
        </div>
      </ElCol>
    </ElRow>

    <!-- 网络 / 磁盘 -->
    <ElRow :gutter="20">
      <ElCol :sm="24" :md="24" :lg="12">
        <div class="art-card mb-5 h-95 p-5 max-sm:mb-4">
          <div class="art-card-header">
            <div class="title">
              <h4>网络流量</h4>
              <p>实时上下行速率（KB/s）</p>
            </div>
            <ArtLineChart
              height="calc(100% - 50px)"
              :data="networkChartData"
              :xAxisData="history.networkLabels"
              :showAreaColor="true"
              :showLegend="true"
              :showAxisLine="false"
            />
          </div>
        </div>
      </ElCol>
      <ElCol :sm="24" :md="24" :lg="12">
        <div class="art-card mb-5 h-95 p-5 max-sm:mb-4">
          <div class="art-card-header">
            <div class="title">
              <h4>磁盘使用</h4>
              <p>各挂载点占用情况</p>
            </div>
            <div class="space-y-3 overflow-y-auto" style="max-height: calc(100% - 50px)">
              <div
                v-for="disk in diskList"
                :key="disk.mount"
                class="rounded-lg border border-g-300 px-4 py-3"
              >
                <div class="mb-2 flex items-center justify-between">
                  <div class="flex items-center">
                    <ArtSvgIcon icon="ri:hard-drive-2-line" class="mr-2 text-lg text-theme" />
                    <span class="text-sm font-medium text-g-900">{{ disk.mount }}</span>
                    <ElTag class="ml-2" size="small" type="info" effect="plain">{{ disk.fs }}</ElTag>
                  </div>
                  <span
                    class="text-sm font-semibold"
                    :class="
                      disk.usedPercent > 80
                        ? 'text-danger'
                        : disk.usedPercent > 60
                          ? 'text-warning'
                          : 'text-success'
                    "
                  >
                    {{ disk.usedPercent }}%
                  </span>
                </div>
                <ElProgress
                  :percentage="disk.usedPercent"
                  :stroke-width="8"
                  :show-text="false"
                  :color="disk.usedPercent > 80 ? '#F56C6C' : disk.usedPercent > 60 ? '#E6A23C' : '#67C23A'"
                />
                <div class="mt-1.5 flex justify-between text-xs text-g-500">
                  <span>已用：{{ formatBytes(disk.used) }}</span>
                  <span>总量：{{ formatBytes(disk.total) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </ElCol>
    </ElRow>

    <!-- 系统信息表格 -->
    <ElRow :gutter="20">
      <ElCol :sm="24" :md="24" :lg="12">
        <div class="art-card mb-5 p-5 max-sm:mb-4">
          <div class="art-card-header">
            <div class="title">
              <h4>系统信息</h4>
              <p>操作系统与运行环境</p>
            </div>
          </div>
          <ElDescriptions :column="1" border class="mt-3">
            <ElDescriptionsItem label="主机名">{{ systemInfo.hostname }}</ElDescriptionsItem>
            <ElDescriptionsItem label="操作系统">{{ systemInfo.os }}</ElDescriptionsItem>
            <ElDescriptionsItem label="系统架构">{{ systemInfo.arch }}</ElDescriptionsItem>
            <ElDescriptionsItem label="内核版本">{{ systemInfo.kernel }}</ElDescriptionsItem>
            <ElDescriptionsItem label="运行时间">{{ systemInfo.uptime }}</ElDescriptionsItem>
            <ElDescriptionsItem label="当前时间">{{ systemInfo.now }}</ElDescriptionsItem>
          </ElDescriptions>
        </div>
      </ElCol>
      <ElCol :sm="24" :md="24" :lg="12">
        <div class="art-card mb-5 p-5 max-sm:mb-4">
          <div class="art-card-header">
            <div class="title">
              <h4>运行时信息</h4>
              <p>Web 服务与进程</p>
            </div>
          </div>
          <ElDescriptions :column="1" border class="mt-3">
            <ElDescriptionsItem label="PHP 版本">{{ runtimeInfo.phpVersion }}</ElDescriptionsItem>
            <ElDescriptionsItem label="Webman 版本">{{ runtimeInfo.webmanVersion }}</ElDescriptionsItem>
            <ElDescriptionsItem label="CPU 核心数">{{ runtimeInfo.cpuCores }} 核</ElDescriptionsItem>
            <ElDescriptionsItem label="进程数">{{ runtimeInfo.processCount }}</ElDescriptionsItem>
            <ElDescriptionsItem label="应用版本">{{ runtimeInfo.appVersion }}</ElDescriptionsItem>
            <ElDescriptionsItem label="环境">{{ runtimeInfo.env }}</ElDescriptionsItem>
          </ElDescriptions>
        </div>
      </ElCol>
    </ElRow>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
  import type { LineDataItem } from '@/types/component/chart'

  defineOptions({ name: 'ServerMonitor' })

  // -------------------- 类型定义 --------------------
  interface ServerStatus {
    running: boolean
    hostname: string
  }

  interface SystemInfo {
    hostname: string
    os: string
    arch: string
    kernel: string
    uptime: string
    now: string
  }

  interface RuntimeInfo {
    phpVersion: string
    webmanVersion: string
    cpuCores: number
    processCount: number
    appVersion: string
    env: string
  }

  interface MemoryInfo {
    total: number
    used: number
    free: number
  }

  interface DiskInfo {
    mount: string
    fs: string
    used: number
    total: number
    usedPercent: number
  }

  // -------------------- 状态 --------------------
  const loading = ref(false)
  const autoRefresh = ref(true)
  const lastUpdateAt = ref<number>(Date.now())

  const serverStatus = reactive<ServerStatus>({
    running: true,
    hostname: 'smart-admin-server'
  })

  const cpuUsage = ref(0)
  const memoryInfo = reactive<MemoryInfo>({ total: 0, used: 0, free: 0 })
  const diskList = ref<DiskInfo[]>([])
  const networkUp = ref(0)
  const networkDown = ref(0)

  const systemInfo = reactive<SystemInfo>({
    hostname: '-',
    os: '-',
    arch: '-',
    kernel: '-',
    uptime: '-',
    now: '-'
  })

  const runtimeInfo = reactive<RuntimeInfo>({
    phpVersion: '-',
    webmanVersion: '-',
    cpuCores: 0,
    processCount: 0,
    appVersion: '-',
    env: '-'
  })

  // 历史数据：保留最近 30 个采样点
  const HISTORY_LEN = 30
  const history = reactive<{
    cpu: number[]
    memory: number[]
    networkLabels: string[]
    networkUp: number[]
    networkDown: number[]
  }>({
    cpu: [],
    memory: [],
    networkLabels: [],
    networkUp: [],
    networkDown: []
  })

  let timer: ReturnType<typeof setInterval> | null = null

  // -------------------- 派生 --------------------
  const memPercent = computed(() =>
    memoryInfo.total > 0 ? Math.round((memoryInfo.used / memoryInfo.total) * 1000) / 10 : 0
  )

  const lastUpdateText = computed(() => {
    const d = new Date(lastUpdateAt.value)
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
  })

  const statCards = computed(() => [
    {
      key: 'cpu',
      label: 'CPU 使用率',
      value: cpuUsage.value,
      unit: '%',
      decimals: 1,
      total: `${runtimeInfo.cpuCores} 核`,
      icon: 'ri:cpu-line',
      iconBg: 'bg-theme/10',
      iconColor: 'text-theme'
    },
    {
      key: 'memory',
      label: '内存使用率',
      value: memPercent.value,
      unit: '%',
      decimals: 1,
      total: formatBytes(memoryInfo.total),
      icon: 'ri:rAM-2-line',
      iconBg: 'bg-success/10',
      iconColor: 'text-success'
    },
    {
      key: 'netUp',
      label: '网络上行',
      value: networkUp.value,
      unit: 'KB/s',
      decimals: 1,
      total: '↑ 发送',
      icon: 'ri:upload-2-line',
      iconBg: 'bg-warning/10',
      iconColor: 'text-warning'
    },
    {
      key: 'netDown',
      label: '网络下行',
      value: networkDown.value,
      unit: 'KB/s',
      decimals: 1,
      total: '↓ 接收',
      icon: 'ri:download-2-line',
      iconBg: 'bg-danger/10',
      iconColor: 'text-danger'
    }
  ])

  const cpuChartData = computed<LineDataItem[]>(() => [{ name: 'CPU', data: history.cpu }])
  const memoryChartData = computed<LineDataItem[]>(() => [{ name: '内存', data: history.memory }])
  const networkChartData = computed<LineDataItem[]>(() => [
    { name: '上行', data: history.networkUp },
    { name: '下行', data: history.networkDown }
  ])

  // -------------------- 工具 --------------------
  function formatBytes(bytes: number): string {
    if (!bytes || bytes <= 0) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`
  }

  function pushHistory(value: number, arr: number[]) {
    arr.push(value)
    if (arr.length > HISTORY_LEN) arr.shift()
  }

  /**
   * 拉取数据。
   *
   * 当前为前端模拟：等后端 /admin/monitor/server 接口就绪后，
   * 把 fetchMonitor() 替换为真实接口调用即可，其他全部代码无需改动。
   */
  async function fetchData() {
    loading.value = true
    try {
      // TODO: 替换为真实接口
      // const data = await fetchMonitor()
      // applyData(data)

      // ----- 前端模拟数据 -----
      await mockFetch()
    } catch (e) {
      console.error('[ServerMonitor] 刷新失败', e)
    } finally {
      loading.value = false
      lastUpdateAt.value = Date.now()
    }
  }

  function applyData(data: Partial<MonitorPayload>) {
    if (data.serverStatus) Object.assign(serverStatus, data.serverStatus)
    if (data.systemInfo) Object.assign(systemInfo, data.systemInfo)
    if (data.runtimeInfo) Object.assign(runtimeInfo, data.runtimeInfo)
    if (typeof data.cpuUsage === 'number') {
      cpuUsage.value = data.cpuUsage
      pushHistory(Math.round(data.cpuUsage * 10) / 10, history.cpu)
    }
    if (data.memory) {
      Object.assign(memoryInfo, data.memory)
      pushHistory(memPercent.value, history.memory)
    }
    if (Array.isArray(data.diskList)) {
      diskList.value = data.diskList
    }
    if (typeof data.networkUp === 'number') {
      networkUp.value = data.networkUp
      pushHistory(data.networkUp, history.networkUp)
    }
    if (typeof data.networkDown === 'number') {
      networkDown.value = data.networkDown
      pushHistory(data.networkDown, history.networkDown)
    }
    if (typeof data.cpuUsage === 'number') {
      const label = new Date(lastUpdateAt.value).toLocaleTimeString('zh-CN', { hour12: false })
      history.networkLabels.push(label)
      if (history.networkLabels.length > HISTORY_LEN) history.networkLabels.shift()
    }
  }

  interface MonitorPayload {
    serverStatus: ServerStatus
    systemInfo: SystemInfo
    runtimeInfo: RuntimeInfo
    cpuUsage: number
    memory: MemoryInfo
    diskList: DiskInfo[]
    networkUp: number
    networkDown: number
  }

  function mockFetch(): Promise<void> {
    return new Promise((resolve) => {
      // 模拟网络延迟
      setTimeout(() => {
        // 第一次填充系统/运行时信息（只填一次）
        if (runtimeInfo.phpVersion === '-') {
          Object.assign(systemInfo, {
            hostname: 'smart-admin-server',
            os: 'Linux Ubuntu 22.04 LTS',
            arch: 'x86_64',
            kernel: '5.15.0-91-generic',
            uptime: '15 天 6 小时 32 分钟',
            now: new Date().toLocaleString('zh-CN', { hour12: false })
          })
          Object.assign(runtimeInfo, {
            phpVersion: '8.2.10',
            webmanVersion: '2.1.0',
            cpuCores: 4,
            processCount: 8,
            appVersion: 'v1.0.0',
            env: 'production'
          })
          memoryInfo.total = 8 * 1024 * 1024 * 1024 // 8GB
          diskList.value = [
            { mount: '/', fs: '/dev/sda1', used: 32 * 1024 * 1024 * 1024, total: 100 * 1024 * 1024 * 1024, usedPercent: 32 },
            { mount: '/data', fs: '/dev/sdb1', used: 380 * 1024 * 1024 * 1024, total: 500 * 1024 * 1024 * 1024, usedPercent: 76 }
          ]

          // 预填历史数据，让折线图在首屏就能呈现趋势
          const baseCpu = 30 + Math.random() * 40
          for (let i = 0; i < HISTORY_LEN; i++) {
            history.cpu.push(Math.max(0, Math.min(100, baseCpu + (Math.random() - 0.5) * 20)))
            history.memory.push(40 + Math.random() * 30)
            history.networkUp.push(Math.random() * 400)
            history.networkDown.push(Math.random() * 1200)
            history.networkLabels.push(
              new Date(Date.now() - (HISTORY_LEN - i) * 3000).toLocaleTimeString('zh-CN', { hour12: false })
            )
          }
        }

        // 每次刷新随机生成实时数据
        const cpu = Math.round((20 + Math.random() * 60) * 10) / 10
        const memUsed = memoryInfo.total * (0.4 + Math.random() * 0.3)
        memoryInfo.used = memUsed
        memoryInfo.free = memoryInfo.total - memUsed
        const up = Math.round(Math.random() * 800 * 10) / 10
        const down = Math.round(Math.random() * 2000 * 10) / 10

        applyData({
          cpuUsage: cpu,
          networkUp: up,
          networkDown: down
        })
        resolve()
      }, 200)
    })
  }

  // -------------------- 生命周期 --------------------
  onMounted(() => {
    fetchData()
  })

  watch(autoRefresh, (val) => {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
    if (val) {
      timer = setInterval(fetchData, 3000)
    }
  })

  onBeforeUnmount(() => {
    if (timer) clearInterval(timer)
  })
</script>

<style scoped lang="scss">
  .server-monitor {
    overflow-y: auto;
  }
</style>
