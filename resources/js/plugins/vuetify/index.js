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
  // Si la cookie del color primario tiene el valor anterior por defecto, la forzamos al nuevo color corporativo #E20074
  const lightColor = cookieRef('lightThemePrimaryColor', staticPrimaryColor)
  if (lightColor.value === '#7367F0' || lightColor.value === '#7367f0' || !lightColor.value) {
    lightColor.value = staticPrimaryColor
  }
  const darkColor = cookieRef('darkThemePrimaryColor', staticPrimaryColor)
  if (darkColor.value === '#7367F0' || darkColor.value === '#7367f0' || !darkColor.value) {
    darkColor.value = staticPrimaryColor
  }

  const lightDarken = cookieRef('lightThemePrimaryDarkenColor', staticPrimaryDarkenColor)
  if (lightDarken.value === '#685dd8' || !lightDarken.value) {
    lightDarken.value = staticPrimaryDarkenColor
  }
  const darkDarken = cookieRef('darkThemePrimaryDarkenColor', staticPrimaryDarkenColor)
  if (darkDarken.value === '#685dd8' || !darkDarken.value) {
    darkDarken.value = staticPrimaryDarkenColor
  }

  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
    themes: {
      light: {
        colors: {
          'primary': lightColor.value,
          'primary-darken-1': lightDarken.value,
        },
      },
      dark: {
        colors: {
          'primary': darkColor.value,
          'primary-darken-1': darkDarken.value,
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

