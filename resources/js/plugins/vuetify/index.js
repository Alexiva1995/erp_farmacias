import { deepMerge } from '@antfu/utils'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import defaults from './defaults'
import { icons } from './icons'
import { staticPrimaryColor, staticPrimaryDarkenColor, themes } from './theme'
import { themeConfig } from '@themeConfig'

// Styles
import { cookieRef } from '@/@layouts/stores/config'
import '@core-scss/template/libs/vuetify/index.scss'
import 'vuetify/styles'

export default function (app) {
  // Si la cookie del color primario en el navegador tiene el valor del morado antiguo, la eliminamos físicamente
  if (typeof document !== 'undefined') {
    try {
      const appTitle = themeConfig.app.title || 'ERP'
      const lightCookieName = `${appTitle}-lightThemePrimaryColor`
      const darkCookieName = `${appTitle}-darkThemePrimaryColor`
      const lightDarkenName = `${appTitle}-lightThemePrimaryDarkenColor`
      const darkDarkenName = `${appTitle}-darkThemePrimaryDarkenColor`

      const deleteCookie = (name) => {
        document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`
      }

      const rawCookies = document.cookie.split(';')
      for (let i = 0; i < rawCookies.length; i++) {
        const c = rawCookies[i].trim()
        if (c.startsWith(lightCookieName) || c.startsWith(darkCookieName)) {
          if (c.includes('%237367F0') || c.includes('#7367F0') || c.includes('7367f0') || c.includes('%237367f0')) {
            deleteCookie(lightCookieName)
            deleteCookie(darkCookieName)
            deleteCookie(lightDarkenName)
            deleteCookie(darkDarkenName)
          }
        }
      }
    } catch (e) {
      console.error('Error clearing old color cookies from document.cookie:', e)
    }
  }

  // Sincronizar cookies por si acaso se leen en otro lado, pero forzar estáticamente el color corporativo #E20074
  try {
    const lightColor = cookieRef('lightThemePrimaryColor', staticPrimaryColor)
    lightColor.value = staticPrimaryColor
    const darkColor = cookieRef('darkThemePrimaryColor', staticPrimaryColor)
    darkColor.value = staticPrimaryColor

    const lightDarken = cookieRef('lightThemePrimaryDarkenColor', staticPrimaryDarkenColor)
    lightDarken.value = staticPrimaryDarkenColor
    const darkDarken = cookieRef('darkThemePrimaryDarkenColor', staticPrimaryDarkenColor)
    darkDarken.value = staticPrimaryDarkenColor
  } catch (e) {
    console.error('Error overriding color cookies:', e)
  }

  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
    themes: {
      light: {
        colors: {
          'primary': staticPrimaryColor,
          'primary-darken-1': staticPrimaryDarkenColor,
        },
      },
      dark: {
        colors: {
          'primary': staticPrimaryColor,
          'primary-darken-1': staticPrimaryDarkenColor,
        },
      },
    },
  }

  const optionTheme = deepMerge({ themes }, cookieThemeValues)

  const vuetify = createVuetify({
    aliases: {
      IconBtn: VBtn,
    },
    defaults,
    icons,
    theme: optionTheme,
  })

  app.use(vuetify)
}

