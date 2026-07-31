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

const isSupervisor = computed(() => authStore.user?.role_id === 2);

// Procesar el menú dinámicamente según el rol del usuario
// Usar computed con dependencia específica para evitar re-evaluaciones innecesarias
const processedNavItems = computed(() => {
  let items = [...navItems];
  const enabledModules = brandingStore.settings.enabled_modules || ['pharmacy'];

  // Filtrado de alto nivel basado en módulos dinámicos configurados
  items = items.filter(item => {
    const title = (item.title || '').toLowerCase();
    const to = (item.to || '').toLowerCase();

    if (title === 'reservas' || to === 'reservations') {
      return enabledModules.includes('reservation');
    }
    if (title === 'lotería' || to === 'lottery' || title === 'loteria') {
      return enabledModules.includes('lottery');
    }
    if (title === 'operativa' || to === 'restaurant-process-audit' || to === 'inventory-dishes') {
      return enabledModules.includes('restaurant');
    }
    if (title === 'facturas' || to === 'invoice' || to.startsWith('invoice-')) {
      return brandingStore.settings.enable_invoices ?? true;
    }
    return true;
  });

  const isSimpleCyclic = brandingStore.settings.cyclic_inventory_mode === 'simple';
  const enableLots = brandingStore.settings.enable_lots ?? true;
  
  const filterNav = (navItemsList) => {
    return navItemsList.map((item) => {
      let copy = { ...item };

      if (copy.children && Array.isArray(copy.children)) {
        let childs = filterNav([...copy.children]);
        
        // Filtrado dinámico de submenús de Promociones según enabled_offer_types
        if (copy.title === 'Promociones') {
          const enabledOffers = brandingStore.settings.enabled_offer_types || ['general', 'individual', 'category', 'pack', 'company', 'doctor', 'prescription', 'expiration'];
          const offerPathMap = {
            'general': '/tpv/generalOffer',
            'individual': '/tpv/individualOffer',
            'category': '/tpv/categoryOffer',
            'pack': '/tpv/packOffer',
            'company': '/tpv/companyOffer',
            'doctor': '/tpv/doctorOffer',
            'prescription': '/tpv/prescriptionOffer',
            'expiration': '/tpv/expirationOffer',
          };
          childs = childs.filter(c => {
            const path = typeof c.to === 'object' ? c.to.path : c.to;
            for (const [type, typePath] of Object.entries(offerPathMap)) {
              if (path === typePath) {
                return enabledOffers.includes(type);
              }
            }
            return true;
          });
          if (childs.length === 0) return null;
        }

        // Filtrado dinámico de submenús de CRM según enabled_crm_views
        if (copy.title === 'CRM') {
          const enabledCrm = brandingStore.settings.enabled_crm_views || ['clients', 'companies', 'doctors', 'lottery'];
          const crmRouteMap = {
            'clients': 'crm-clients',
            'companies': 'crm-companies',
            'doctors': 'crm-doctors',
            'lottery': 'crm-lottery',
          };
          childs = childs.filter(c => {
            for (const [key, name] of Object.entries(crmRouteMap)) {
              if (c.to === name) {
                return enabledCrm.includes(key);
              }
            }
            return true;
          });
          if (childs.length === 0) return null;
        }

        // Filtrado dinámico de submenús de RRHH según enabled_rrhh_views
        if (copy.title === 'RRHH') {
          const enabledRrhh = brandingStore.settings.enabled_rrhh_views || ['employees', 'social_benefits', 'resignations', 'cleaning', 'laboratory', 'product', 'employee_task', 'employee_month'];
          const rrhhRouteMap = {
            'employees': 'rrhh-employees',
            'social_benefits': 'rrhh-social-benefits',
            'resignations': 'rrhh-resignations',
          };
          const productivityRouteMap = {
            'cleaning': 'productivity-cleaning',
            'laboratory': 'productivity-laboratory',
            'product': 'productivity-product',
            'employee_task': 'productivity-employee-task',
            'employee_task_review': 'productivity-supervisor-cleaning-activities',
            'employee_month': 'productivity-employee-month',
          };

          childs = childs.filter(c => {
            for (const [key, name] of Object.entries(rrhhRouteMap)) {
              if (c.to === name) {
                return enabledRrhh.includes(key);
              }
            }
            if (c.title === 'Productividad' && Array.isArray(c.children)) {
              c.children = c.children.filter(sub => {
                for (const [key, name] of Object.entries(productivityRouteMap)) {
                  if (sub.to === name) {
                    return enabledRrhh.includes(key);
                  }
                }
                return true;
              });
              return c.children.length > 0;
            }
            return true;
          });
          if (childs.length === 0) return null;
        }

        // Filtrado dinámico de submenús de Proveedores según enabled_supplier_views
        if (copy.title === 'Proveedores') {
          const enabledSuppliers = brandingStore.settings.enabled_supplier_views || ['list', 'purchase_orders'];
          const supplierRouteMap = {
            'list': 'suppliers-list',
            'purchase_orders': 'suppliers-purchase-orders-list',
          };
          childs = childs.filter(c => {
            for (const [key, name] of Object.entries(supplierRouteMap)) {
              if (c.to === name) {
                return enabledSuppliers.includes(key);
              }
            }
            return true;
          });
          if (childs.length === 0) return null;
        }

        // Filtrado dinámico de submenús de IA Assistence según enabled_ia_assistant_views
        if (copy.title === 'IA Assistence') {
          const enabledIaAssistant = brandingStore.settings.enabled_ia_assistant_views || ['pedidos', 'reporte', 'oportunidad', 'comparador', 'automatizacion'];
          const iaAssistantRouteMap = {
            'pedidos': 'suppliers-supplieriaorderassistant',
            'reporte': 'suppliers-supplieriaorderassistantreport',
            'oportunidad': 'suppliers-market-opportunities',
            'comparador': 'suppliers-product-comparator-list',
            'automatizacion': 'suppliers-auto-replenishment',
          };
          childs = childs.filter(c => {
            for (const [key, name] of Object.entries(iaAssistantRouteMap)) {
              if (c.to === name) {
                return enabledIaAssistant.includes(key);
              }
            }
            return true;
          });
          if (childs.length === 0) return null;
        }

        // Filtrado dinámico de submenús de Finanzas según enabled_finance_views
        if (copy.title === 'Finanzas') {
          const enabledFinances = brandingStore.settings.enabled_finance_views || [
            'profitability', 'exchangerate', 'pending-payments', 'payment-history', 
            'cashout', 'payslips', 'cash-closure', 'cash-closure-user', 
            'income-statement', 'expense-expenses', 'balance-general', 
            'furnitures-list', 'loans-list'
          ];
          const financeRouteMap = {
            'profitability': 'finances-profitability',
            'exchangerate': 'finances-exchangerate',
            'pending-payments': 'finances-pending-payments',
            'payment-history': 'finances-payment-history',
            'cashout': 'finances-cashout',
            'payslips': 'finances-payslips',
            'cash-closure': 'finances-cash-closure',
            'cash-closure-user': 'finances-cash-closure-user',
            'income-statement': 'finances-income-statement',
            'expense-expenses': 'finances-expense-expenses',
            'balance-general': 'balance-general',
            'furnitures-list': 'furnitures-list',
            'loans-list': 'loans-list',
          };
          childs = childs.filter(c => {
            for (const [key, name] of Object.entries(financeRouteMap)) {
              if (c.to === name) {
                if (isSupervisor.value && c.to === 'finances-cash-closure-user' && c.subject === 'user') {
                  return false;
                }
                return enabledFinances.includes(key);
              }
            }
            return true;
          });
          if (childs.length === 0) return null;
        }

        // Filtrado dinámico de submenús de BI según enabled_bi_views
        if (copy.title === 'BI') {
          const enabledBi = brandingStore.settings.enabled_bi_views || ['abc', 'dead-stock', 'sku', 'products', 'expiry', 'laboratories', 'pos', 'cyclic', 'customer', 'performance'];
          const biRouteMap = {
            'abc': 'bi-report-abc',
            'dead-stock': 'bi-report-dead-stock',
            'sku': 'bi-report-sku',
            'products': 'bi-report-products',
            'expiry': 'bi-report-expiry',
            'laboratories': 'bi-report-laboratories',
            'pos': 'bi-analytics-pos',
            'cyclic': 'bi-inventory-cyclic',
            'customer': 'bi-customer-analytics',
            'performance': 'bi-employee-performance',
          };
          childs = childs.filter(c => {
            for (const [key, name] of Object.entries(biRouteMap)) {
              if (c.to === name) {
                return enabledBi.includes(key);
              }
            }
            return true;
          });
          if (childs.length === 0) return null;
        }

        // Ocultar Pendientes en modo simple
        if (isSimpleCyclic) {
          childs = childs.filter((c) => c.to !== 'cyclics-cyclic');
        }
        
        // Filtrar Platos / Menú usando el setting enable_dishes
        const enableDishesSetting = brandingStore.settings.enable_dishes ?? true;
        if (!enableDishesSetting) {
          childs = childs.filter((c) => c.to !== 'inventory-dishes');
        }

        // Renombrar Laboratorios a Marcas de forma universal
        childs = childs.map((c) => {
          if (c.to === 'inventory-laboratories') {
            return { ...c, title: 'Marcas' };
          }
          return { ...c };
        });

        const enableQuotationsSetting = brandingStore.settings.enable_quotations ?? true;
        if (!enableQuotationsSetting) {
          childs = childs.filter((c) => c.to !== 'tpv-quotation');
        }

        const enableReservationsSetting = brandingStore.settings.enable_reservations ?? true;
        if (!enableReservationsSetting) {
          childs = childs.filter((c) => c.to !== 'reservations');
        }

        if (!enableLots) {
          childs = childs.filter((c) => 
            c.to !== 'lot-list' &&
            c.to !== 'inventory-lotificacion' &&
            c.to !== 'inventory-lots-without-location'
          );
          childs = childs.map((c) => {
            if (c.children && Array.isArray(c.children)) {
              return {
                ...c,
                children: c.children.filter((sub) => 
                  sub.to !== 'inventory-lotificacion' &&
                  sub.to !== 'inventory-lots-without-location'
                )
              };
            }
            return c;
          });
        }

        // Filtrar Caducidad si está desactivado en la configuración
        const enableExpirations = brandingStore.settings.enable_expirations ?? true;
        if (!enableExpirations) {
          childs = childs.filter((c) => 
            c.to !== 'inventory-expirations'
          );
        }

        // Filtrar Grupos de Productos si está desactivado en la configuración
        const enableGroups = brandingStore.settings.enable_groups ?? true;
        if (!enableGroups) {
          childs = childs.filter((c) => 
            c.to !== 'inventory-group-products'
          );
          childs = childs.map((c) => {
            if (c.children && Array.isArray(c.children)) {
              return {
                ...c,
                children: c.children.filter((sub) => 
                  sub.to !== 'inventory-products-without-group'
                )
              };
            }
            return c;
          });
        }

        // Filtrar Control de Stock si está desactivado en la configuración
        const enableStockControl = brandingStore.settings.enable_stock_control ?? true;
        if (!enableStockControl) {
          childs = childs.filter((c) => 
            c.to !== 'inventory-stock'
          );
        }

        // Filtrar Ubicaciones si está desactivado en la configuración
        const enableLocations = brandingStore.settings.enable_locations ?? true;
        if (!enableLocations) {
          childs = childs.filter((c) => c.to !== 'inventory-locations');
        }

        // Filtrar Optimización si está desactivado en la configuración
        const enableOptimization = brandingStore.settings.enable_optimization ?? true;
        if (!enableOptimization) {
          childs = childs.filter((c) => c.title !== 'Optimización');
        }

        copy.children = childs;
      }
      return copy;
    });
  };
 
  items = filterNav(items).filter(Boolean);
 
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
          class="app-logo d-flex align-center justify-start px-2"
          style="gap: 8px;"
        >
          <!-- Logo Expandido (SVG de TOVA) -->
          <div class="logo-expanded-wrapper">
            <div
              class="logo-expanded-svg text-primary"
              v-html="logoSvg"
            />
          </div>

          <!-- Logo Colapsado (Favicon Isotipo mini de TOVA) -->
          <img
            src="/favicon-96x96.png"
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
  justify-content: flex-start;
  flex: 1;
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
