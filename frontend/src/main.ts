import App from './App.vue'
import { createApp } from 'vue'
import { initStore } from './store'                 // Store
import { initRouter } from './router'               // Router
import language from './locales'                    // 国际化
import '@styles/core/tailwind.css'                  // tailwind
import '@styles/index.scss'                         // 样式
import '@utils/sys/console.ts'                      // 控制台输出内容
import { setupGlobDirectives } from './directives'
import { setupErrorHandle } from './utils/sys/error-handle'
import '@utils/ui/iconify-loader'                   // 离线图标加载（避免请求 api.unisvg.com）
import { useUserStore } from './store/modules/user'

document.addEventListener(
  'touchstart',
  function () {},
  { passive: false }
)

const app = createApp(App)
initStore(app)
// pinia 注册后立刻从持久化态里解析 token 过期时间（FE-6）：
// accessTokenExpire 不持久化，每次启动现算才能让 http 拦截器的"临近过期自动刷新"生效
useUserStore().restoreTokenExpire()
initRouter(app)
setupGlobDirectives(app)
setupErrorHandle(app)

app.use(language)
app.mount('#app')