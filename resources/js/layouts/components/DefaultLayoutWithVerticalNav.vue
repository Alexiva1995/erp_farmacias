<script setup>
import navItems from "@/navigation/vertical";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { themeConfig } from "@themeConfig";
import { computed } from "vue";
import { VNodeRenderer } from "@layouts/components/VNodeRenderer";

// Components
import Footer from "@/layouts/components/Footer.vue";
import NavbarThemeSwitcher from "@/layouts/components/NavbarThemeSwitcher.vue";
import UserProfile from "@/layouts/components/UserProfile.vue";
import NavBarI18n from "@core/components/I18n.vue";

// @layouts plugin
import { VerticalNavLayout } from "@layouts";
import { useLayoutConfigStore } from "@layouts/stores/config";

const authStore = useAuthStore();
const brandingStore = useBrandingStore();
const configStore = useLayoutConfigStore();

// Procesar el menú dinámicamente según el rol del usuario
// Usar computed con dependencia específica para evitar re-evaluaciones innecesarias
const processedNavItems = computed(() => {
  // Solo procesar si el usuario está cargado
  if (!authStore.isLoaded || !authStore.user) {
    return navItems;
  }

  const currentRoleId = authStore.user?.role_id;
  const isUser = currentRoleId === 3;

  if (!isUser) {
    return navItems;
  }

  // Para usuarios tipo "usuario", mostrar Inventario Ciclicos solo con Pendientes e Inventario de Usuario
  try {
    return navItems.map((item) => {
      if (
        item.title === "Inventario" &&
        item.children &&
        Array.isArray(item.children)
      ) {
        return {
          ...item,
          children: item.children.map((child) => {
            if (child.title === "Inventario Ciclicos" && child.children) {
              // Filtrar solo Pendientes e Inventario de Usuario para usuarios
              const allowedSubjects = ["pending-cyclics", "cycli-user"];
              const userCyclicChildren = child.children.filter(
                (c) => c.subject && allowedSubjects.includes(c.subject)
              );
              return {
                ...child,
                children: userCyclicChildren,
              };
            }
            return { ...child };
          }),
        };
      }
      return { ...item };
    });
  } catch (error) {
    console.error("Error procesando menú:", error);
    return navItems;
  }
});
</script>

<template>
  <template v-if="authStore.isLoaded">
    <VerticalNavLayout :nav-items="processedNavItems">
      <!-- 👉 vertical-nav-header -->
      <template #vertical-nav-header="{ toggleIsOverlayNavActive }">
        <RouterLink
          to="/"
          class="app-logo d-flex align-center gap-x-3"
        >
          <img
            v-if="brandingStore.settings.app_logo"
            :src="brandingStore.settings.app_logo"
            alt="logo"
            height="30"
          >
          <VNodeRenderer
            v-else
            :nodes="themeConfig.app.logo"
          />

          <h1 class="app-title font-weight-bold leading-normal text-xl text-capitalize">
            {{ brandingStore.settings.app_name || themeConfig.app.title }}
          </h1>
        </RouterLink>

        <IconBtn
          class="d-block d-md-none"
          @click="toggleIsOverlayNavActive(false)"
        >
          <VIcon icon="tabler-x" />
        </IconBtn>

        <div class="header-action d-none d-md-block">
          <IconBtn
            @click="configStore.isVerticalNavCollapsed = !configStore.isVerticalNavCollapsed"
          >
            <VIcon :icon="configStore.isVerticalNavCollapsed ? 'tabler-circle' : 'tabler-circle-dot'" />
          </IconBtn>
        </div>
      </template>
      <!-- 👉 navbar -->
      <template #navbar="{ toggleVerticalOverlayNavActive }">
        <div class="d-flex h-100 align-center">
          <IconBtn
            id="vertical-nav-toggle-btn"
            class="ms-n3 d-lg-none"
            @click="toggleVerticalOverlayNavActive(true)"
          >
            <VIcon size="26" icon="tabler-menu-2" />
          </IconBtn>

          <NavbarThemeSwitcher />

          <VSpacer />

          <NavBarI18n
            v-if="
              themeConfig.app.i18n.enable &&
              themeConfig.app.i18n.langConfig?.length
            "
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
  <div v-else class="d-flex justify-center align-center" style="height: 100vh">
    Cargando aplicación...
  </div>
</template>

<style lang="scss">
.layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) .app-title {
  display: none !important;
}
</style>
