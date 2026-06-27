<script setup>
import navItems from "@/navigation/vertical";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { themeConfig } from "@themeConfig";
import { computed } from "vue";
import logoSvg from '@images/logo.svg?raw';

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
  let items = [...navItems];
  
  const isRestaurant = brandingStore.settings.business_type === 'restaurant';
  
  const filterRestaurantNav = (navItemsList) => {
    const isMiniMarket = brandingStore.settings.business_type === 'minimarket';
    
    return navItemsList.map((item) => {
      let copy = { ...item };
      
      // Si el item padre es el de IA Assistence, renombrarlo a Pedidos en restaurante
      if (isRestaurant && copy.title === 'IA Assistence') {
        copy.title = 'Pedidos';
        // Asignarle 'to' directo para que redirija inmediatamente al hacer click
        copy.to = 'suppliers-supplieriaorderassistant';
        // Eliminar los hijos para evitar el desplegable y que entre directo
        delete copy.children;
        return copy;
      }

      if (copy.children && Array.isArray(copy.children)) {
        // Primero procesamos recursivamente los hijos
        let childs = filterRestaurantNav([...copy.children]);
        
        if (!isRestaurant && !isMiniMarket) {
          childs = childs.filter((c) => c.to !== 'inventory-dishes');
        } else if (isMiniMarket) {
          // Si es minimarket, ocultar Optimización por completo, Origen, Grupo, Ubicaciones y filtrar platos de restaurante
          childs = childs.filter((c) => 
            c.title !== 'Optimización' && 
            c.to !== 'inventory-dishes' &&
            c.to !== 'inventory-lots-without-location' &&
            c.to !== 'inventory-incomplete-products' &&
            c.to !== 'inventory-products-without-group' &&
            c.to !== 'inventory-lotificacion' &&
            c.to !== 'inventory-locations'
          );
          childs = childs.map((c) => {
            if (c.to === 'inventory-laboratories') {
              return { ...c, title: 'Marcas' };
            }
            return { ...c };
          });
        } else {
          childs = childs.filter((c) => 
            c.title !== 'Devoluciones' && 
            c.title !== 'Medico' && 
            c.title !== 'Recipe' && 
            c.to !== 'crm-doctors' &&
            c.to !== 'productivity-laboratory' &&
            c.title !== 'Laboratorios Empleados' &&
            c.to !== 'fiscal-retenciones'
          );
          childs = childs.map((c) => {
            if (c.to === 'inventory-laboratories') {
              return { ...c, title: 'Marcas' };
            }
            return { ...c };
          });
        }
        copy.children = childs;
      }
      return copy;
    });
  };
 
  items = filterRestaurantNav(items);
 
  // 2. Si es modo restaurante, ocultar Reservas para TODOS los roles
  if (isRestaurant) {
    items = items.filter(item => {
      const title = (item.title || '').toLowerCase();
      const to = (item.to || '').toLowerCase();
      return title !== 'reservas' && to !== 'reservations';
    });
    
    // Y habilitar "Operativa" para Admin y Empleado. Quitamos el subject de CASL dinámicamente
    // para que no requiera privilegios exclusivos de 'admin' en la evaluación de CASL del layout.
    items = items.map(item => {
      if (item.title === 'Operativa') {
        let copy = { ...item };
        delete copy.action;
        delete copy.subject;
        if (copy.children) {
          copy.children = copy.children.map(child => {
            let childCopy = { ...child };
            delete childCopy.action;
            delete childCopy.subject;
            return childCopy;
          });
        }
        return copy;
      }
      return item;
    });
  }
 
  // 3. Si el negocio es de Alquiler de Canchas/Reservas (sports_rental),
  // reordenamos el menú para colocar 'Reservas' en el primer lugar (arriba del Home)
  const isSportsRental = brandingStore.settings.business_type === 'sports_rental';
  if (isSportsRental) {
    const reservationsItem = items.find(item => {
      const title = (item.title || '').toLowerCase();
      const toVal = item.to;
      let toStr = '';
      if (typeof toVal === 'string') {
        toStr = toVal.toLowerCase();
      } else if (toVal && typeof toVal === 'object' && toVal.name) {
        toStr = String(toVal.name).toLowerCase();
      }
      return title === 'reservas' || toStr === 'reservations';
    });
    if (reservationsItem) {
      // Filtrar el ítem de su posición original
      items = items.filter(item => item !== reservationsItem);
      // Colocarlo al puro principio del array (arriba de Home)
      items.unshift(reservationsItem);
    }
  }
 
  // Solo procesar si el usuario está cargado
  if (!authStore.isLoaded || !authStore.user) {
    return items;
  }
 
  const currentRoleId = authStore.user?.role_id;
  const isUser = currentRoleId === 3;
 
  if (!isUser) {
    return items;
  }
 
  // Para usuarios tipo "usuario" (empleado), mostrar Inventario Ciclicos solo con Pendientes e Inventario de Usuario
  try {
    return items.map((item) => {
      if (
        item.title === "Inventario Ciclicos" &&
        item.children &&
        Array.isArray(item.children)
      ) {
        const allowedSubjects = ["pending-cyclics", "cycli-user"];
        const userCyclicChildren = item.children.filter(
          (c) => c.subject && allowedSubjects.includes(c.subject)
        );
        return {
          ...item,
          children: userCyclicChildren,
        };
      }
      return { ...item };
    });
  } catch (error) {
    console.error("Error procesando menú:", error);
    return items;
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
          class="app-logo d-flex align-center justify-center w-100 px-2"
        >
          <!-- Logo Expandido (SVG de TOVA o Logo personalizado de base de datos) -->
          <div class="logo-expanded-wrapper">
            <img
              v-if="brandingStore.settings.app_logo"
              :src="brandingStore.settings.app_logo"
              alt="logo"
              class="logo-expanded"
            >
            <div
              v-else
              class="logo-expanded-svg text-primary"
              v-html="logoSvg"
            />
          </div>

          <!-- Logo Colapsado (Favicon Isotipo mini) -->
          <img
            :src="brandingStore.settings.app_favicon || '/favicon-96x96.png'"
            alt="logo-collapsed"
            class="logo-collapsed"
            width="32"
            height="32"
          >
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
.app-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.logo-expanded-wrapper {
  display: block;
  width: 100%;
  max-width: 150px;
  
  .logo-expanded {
    max-height: 38px;
    object-fit: contain;
  }
  
  .logo-expanded-svg svg {
    width: 100%;
    height: auto;
    max-height: 38px;
  }
}

.logo-collapsed {
  display: none !important;
}

// Cuando el menú lateral vertical está colapsado y no se le pasa el mouse
.layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) {
  .logo-expanded-wrapper {
    display: none !important;
  }
  
  .logo-collapsed {
    display: block !important;
    margin: 0 auto;
  }
}
</style>
