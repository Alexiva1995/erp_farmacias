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

