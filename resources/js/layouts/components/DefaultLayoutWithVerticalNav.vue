<script setup>
import navItems from '@/navigation/vertical'
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@/stores/auth'
import { computed } from 'vue'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const authStore = useAuthStore()

// Procesar el menú dinámicamente según el rol del usuario
const processedNavItems = computed(() => {
  const isUser = authStore.user?.role_id === 3
  
  if (!isUser) {
    return navItems
  }
  
  // Para usuarios tipo "usuario", modificar el menú de Inventario Ciclicos
  return navItems.map(item => {
    if (item.title === 'Inventario') {
      return {
        ...item,
        children: item.children.map(child => {
          if (child.title === 'Inventario Ciclicos') {
            // Convertir el item con children en un item directo que apunta a closing
            return {
              title: 'Inventario Ciclicos',
              to: 'cyclics-closing',
              action: 'manage',
              subject: 'closing-cyclics',
              icon: child.icon || item.icon
            }
          }
          return child
        })
      }
    }
    return item
  })
})

</script>

<template>
<template v-if="authStore.isLoaded">
  <VerticalNavLayout :nav-items="processedNavItems">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <NavbarThemeSwitcher />

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
  </template>
  <div v-else class="d-flex justify-center align-center" style="height: 100vh;">
    Cargando aplicación...
  </div>
</template>
