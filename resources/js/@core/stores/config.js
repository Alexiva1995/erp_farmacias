import { storeToRefs } from 'pinia'
import { useTheme } from 'vuetify'
import { cookieRef, useLayoutConfigStore } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

// SECTION Store
export const useConfigStore = defineStore('config', () => {
  // 👉 Theme (siempre light)
  const cookieColorScheme = ref('light')
  const theme = ref('light')

  // 👉 isVerticalNavSemiDark
  const isVerticalNavSemiDark = ref(false)

  // 👉 skin
  const skin = cookieRef('skin', themeConfig.app.skin)

  // ℹ️ We need to use `storeToRefs` to forward the state
  const { isLessThanOverlayNavBreakpoint, appContentWidth, navbarType, isNavbarBlurEnabled, appContentLayoutNav, isVerticalNavCollapsed, footerType, isAppRTL } = storeToRefs(useLayoutConfigStore())
  
  return {
    theme,
    isVerticalNavSemiDark,
    skin,

    // @layouts exports
    isLessThanOverlayNavBreakpoint,
    appContentWidth,
    navbarType,
    isNavbarBlurEnabled,
    appContentLayoutNav,
    isVerticalNavCollapsed,
    footerType,
    isAppRTL,
  }
})
// !SECTION
// SECTION Init
export const initConfigStore = () => {
  const vuetifyTheme = useTheme()
  const configStore = useConfigStore()

  // Forzar siempre modo claro y purgar configuraciones oscuras
  configStore.theme = 'light'
  configStore.isVerticalNavSemiDark = false
  vuetifyTheme.global.name.value = 'light'

  if (typeof window !== 'undefined') {
    try {
      localStorage.removeItem('theme')
      localStorage.removeItem('color-scheme')
      document.documentElement.setAttribute('data-theme', 'light')
      document.cookie = 'theme=light; path=/;'
      document.cookie = 'color-scheme=light; path=/;'
      document.cookie = 'isVerticalNavSemiDark=false; path=/;'
    } catch (e) {
      // silenciar
    }
  }

  watch(() => configStore.theme, () => {
    vuetifyTheme.global.name.value = 'light'
    configStore.theme = 'light'
  }, { immediate: true })
  
  onMounted(() => {
    vuetifyTheme.global.name.value = 'light'
  })
}
// !SECTION
