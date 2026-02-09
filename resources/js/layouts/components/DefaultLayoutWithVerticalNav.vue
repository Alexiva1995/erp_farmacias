<script setup>
import navItems from "@/navigation/vertical";
import { useAuthStore } from "@/stores/auth";
import { themeConfig } from "@themeConfig";
import { computed } from "vue";

// Components
import Footer from "@/layouts/components/Footer.vue";
import NavbarThemeSwitcher from "@/layouts/components/NavbarThemeSwitcher.vue";
import UserProfile from "@/layouts/components/UserProfile.vue";
import NavBarI18n from "@core/components/I18n.vue";

// @layouts plugin
import { VerticalNavLayout } from "@layouts";

const authStore = useAuthStore();

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
