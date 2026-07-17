import { useAuthStore } from '@/stores/auth'; // <-- 1. IMPORTA TU AUTH STORE
import { useBrandingStore } from '@/stores/useBrandingStore';
import { setupLayouts } from 'virtual:meta-layouts';
import { createRouter, createWebHistory } from 'vue-router/auto';

function recursiveLayouts(route) {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])
    
    return route
  }
  
  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory('/'),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }
    
    return { top: 0 }
  },

  extendRoutes: pages => {
    const publicRoutes = ['/', '/login', '/p/suppliers/upload/:token', '/p/orders/confirm/:hash', '/reservar', '/tova-store', '/restaurant-store']

    function addAuthMeta(routes) {
      return routes.map(route => {
        if (!publicRoutes.includes(route.path)) {
          route.meta = { ...route.meta, requiresAuth: true }
        }

        if (route.children) {
          route.children = addAuthMeta(route.children)
        }

        return route
      })
    }

    const filteredPages = pages.filter(p => p.path !== '/public/booking' && p.path !== '/tova-store' && p.path !== '/restaurant-store')
    
    // Cambiar la raíz '/' para usar el componente de tova-store con layout blank
    const indexRoute = filteredPages.find(p => p.path === '/')
    if (indexRoute) {
      indexRoute.component = () => import('@/pages/tova-store.vue')
      indexRoute.meta = { ...indexRoute.meta, requiresAuth: false, layout: 'blank' }
    }

    const pagesWithAuth = addAuthMeta(filteredPages)
    
    // Agregar ruta manual para renuncias (siguiendo el patrón del proyecto)
    const manualRoutes = [
      {
        path: '/rrhh/resignations',
        name: 'rrhh-resignations',
        component: () => import('@/pages/rrhh/resignations/index.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/restaurant/process-audit',
        name: 'restaurant-process-audit',
        component: () => import('@/pages/restaurant/process-audit.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/p/suppliers/upload/:token',
        name: 'public-supplier-upload',
        component: () => import('@/pages/public/SupplierUpload.vue'),
        meta: { 
          requiresAuth: false,
          layout: 'blank'
        }
      },
      {
        path: '/p/orders/confirm/:hash',
        name: 'public-order-confirm',
        component: () => import('@/pages/public/OrderConfirmation.vue'),
        meta: { 
          requiresAuth: false,
          layout: 'blank'
        }
      },
      {
        path: '/reservar',
        name: 'public-booking',
        component: () => import('@/pages/public/booking.vue'),
        meta: {
          requiresAuth: false,
          layout: 'blank'
        }
      },
      {
        path: '/tova-store',
        name: 'tova-store',
        component: () => import('@/pages/tova-store.vue'),
        meta: {
          requiresAuth: false,
          layout: 'blank'
        }
      },
      {
        path: '/restaurant-store',
        name: 'restaurant-store',
        component: () => import('@/pages/restaurant-store.vue'),
        meta: {
          requiresAuth: false,
          layout: 'blank'
        }
      }
    ]
    
    return [...pagesWithAuth.map(route => recursiveLayouts(route)), ...manualRoutes.map(route => recursiveLayouts(route))]
  },
})

// Flag para evitar múltiples llamadas simultáneas a fetchUser
let isFetchingUser = false
let fetchUserPromise = null

router.beforeEach(async (to, from, next) => {
  console.log('[ROUTER] Iniciando navegación a:', to.path, to.name)
  
  let nextCalled = false
  const safeNext = (...args) => {
    if (!nextCalled) {
      nextCalled = true
      next(...args)
    }
  }
  
  // Timeout de seguridad: si el guard tarda más de 10 segundos, permitir navegación
  const safetyTimeout = setTimeout(() => {
    console.warn('[ROUTER] Timeout de seguridad alcanzado, permitiendo navegación')
    safeNext()
  }, 10000)
  
  try {
    const authStore = useAuthStore()
    const brandingStore = useBrandingStore()
    const requiresAuth = to.meta?.requiresAuth

    // Cargar settings del backend para tener el business_type real antes de evaluar rutas
    if (!brandingStore.settings.app_rif) {
      try {
        await brandingStore.fetchSettings()
      } catch (brandingError) {
        console.warn('[ROUTER] Error al cargar configuración de marca:', brandingError)
      }
    }

    console.log('[ROUTER] AuthStore estado:', { isLoaded: authStore.isLoaded, hasUser: !!authStore.user })
    
    // Intentar obtener el usuario en la carga inicial si no está cargado aún
    if (!authStore.isLoaded && !authStore.user && !isFetchingUser) {
      console.log('[ROUTER] Intentando obtener usuario...')
      isFetchingUser = true
      
      try {
        fetchUserPromise = authStore.fetchUser()
        const timeoutPromise = new Promise((resolve) => setTimeout(resolve, 8000))
        await Promise.race([fetchUserPromise, timeoutPromise])
        console.log('[ROUTER] Usuario obtenido o timeout alcanzado')
      } catch (fetchError) {
        console.warn('[ROUTER] Error al obtener usuario:', fetchError)
        // Continuar aunque falle para no bloquear la navegación
      } finally {
        isFetchingUser = false
        fetchUserPromise = null
      }
    } else if (isFetchingUser && fetchUserPromise) {
      // Si ya hay una llamada en curso, esperar a que termine (con timeout)
      console.log('[ROUTER] Esperando a que termine fetchUser en curso...')
      try {
        const timeoutPromise = new Promise((resolve) => setTimeout(resolve, 8000))
        await Promise.race([fetchUserPromise, timeoutPromise])
      } catch (error) {
        console.warn('[ROUTER] Error esperando fetchUser:', error)
      }
    }
    
    clearTimeout(safetyTimeout)
    // La raíz '/' siempre renderiza la tienda directamente (tova-store.vue) sin redirecciones
    if (to.path === '/') {
      console.log('[ROUTER] Raíz /: Sirviendo la tienda directamente por defecto')
      return safeNext()
    }

    const isFarmacia = brandingStore.settings.business_type === 'farmacia' || brandingStore.settings.business_type === 'pharmacy'

    if (isFarmacia && (to.path === '/tova-store' || to.path === '/restaurant-store' || to.path === '/')) {
      const isAuthenticated = authStore.isAuthenticated
      if (isAuthenticated) {
        if (authStore.isAdmin) {
          console.log('[ROUTER] Farmacia: Redirigiendo a /dashboard')
          return safeNext({ path: '/dashboard' })
        } else {
          console.log('[ROUTER] Farmacia: Redirigiendo a /tpv/orderUser')
          return safeNext({ path: '/tpv/orderUser' })
        }
      } else {
        console.log('[ROUTER] Farmacia: Redirigiendo a /login')
        return safeNext({ path: '/login' })
      }
    }

    if (to.path === '/tova-store') {
      const brandingStore = useBrandingStore()
      if (brandingStore.settings.business_type === 'restaurant') {
        console.log('[ROUTER] Ecommerce no disponible para restaurante, redirigiendo a restaurant-store')
        return safeNext({ path: '/restaurant-store' })
      }
    }

    if (to.path === '/restaurant-store') {
      const brandingStore = useBrandingStore()
      if (brandingStore.settings.business_type !== 'restaurant') {
        console.log('[ROUTER] Restaurant store no disponible, redirigiendo a tova-store')
        return safeNext({ path: '/tova-store' })
      }
    }

    const isAuthenticated = authStore.isAuthenticated
    console.log('[ROUTER] Verificación:', { isAuthenticated, requiresAuth })
    
    if (requiresAuth && !isAuthenticated) {
      console.log('[ROUTER] Redirigiendo a login')
      return safeNext({ path: '/login' })
    }
    
    if (to.path === '/login' && isAuthenticated) {
      console.log('[ROUTER] Redirigiendo según rol')
      const brandingStore = useBrandingStore()

      if (brandingStore.settings?.business_type === 'minimarket') {
        console.log('[ROUTER] Minimarket: Redirigiendo a raíz / para usuario logueado')
        return safeNext({ path: '/' })
      }

      const isSportsRental = brandingStore.settings?.business_type === 'sports_rental'
      
      if (isSportsRental) {
        return safeNext({ path: '/reservations' })
      }
      
      if (authStore.isAdmin) {
        return safeNext({ path: '/dashboard' })
      } else {
        return safeNext({ path: '/tpv/orderUser' })
      }
    }
    
    // Para minimarket, permitir navegación en raíz / sin redirigir
    if (to.path === '/') {
      const brandingStore = useBrandingStore()
      if (brandingStore.settings?.business_type === 'minimarket') {
        console.log('[ROUTER] Minimarket: Sirviendo tienda en / directamente')
        return safeNext()
      }
    }

    if (to.path === '/' && !isAuthenticated) {
      const brandingStore = useBrandingStore()
      if (brandingStore.settings.business_type === 'restaurant') {
        console.log('[ROUTER] Redirigiendo cliente de restaurante a restaurant-store')
        return safeNext({ path: '/restaurant-store' })
      } else if (brandingStore.settings.business_type === 'farmacia' || brandingStore.settings.business_type === 'pharmacy') {
        console.log('[ROUTER] Redirigiendo cliente de farmacia a login')
        return safeNext({ path: '/login' })
      }
    }

    if (to.path === '/' && isAuthenticated) {
      const brandingStore = useBrandingStore()

      if (brandingStore.settings?.business_type === 'minimarket') {
        console.log('[ROUTER] Minimarket: Manteniendo en raíz / para usuario logueado')
        return safeNext()
      }

      const isSportsRental = brandingStore.settings?.business_type === 'sports_rental'
      
      if (isSportsRental) {
        return safeNext({ path: '/reservations' })
      }
      
      if (authStore.isAdmin) {
        return safeNext({ path: '/dashboard' })
      } else {
        return safeNext({ path: '/tpv/orderUser' })
      }
    }
    
    if ((to.path.startsWith('/finances/pending-payments') || to.path.startsWith('/finances/cashout')) && authStore.isVendedor) {
      console.log('[ROUTER] Empleado intentó acceder a sección financiera restringida, redirigiendo')
      return safeNext({ path: '/invoice/invoices' })
    }
    
    const isMiniMarket = brandingStore.settings?.business_type === 'minimarket'
    if (isMiniMarket) {
      const path = to.path.toLowerCase()
      if (
        path.startsWith('/fiscal') ||
        path.startsWith('/iva') ||
        path.startsWith('/islr') ||
        path.startsWith('/restaurant') ||
        path.includes('-offer') ||
        path === '/bi/discounts' ||
        path === '/crm/doctors' ||
        path === '/crm/companies'
      ) {
        console.log('[ROUTER] Ruta no disponible para minimarket, redirigiendo a Home')
        return safeNext({ path: '/' })
      }
    }
    
    console.log('[ROUTER] Permitiendo navegación')
    return safeNext()
  } catch (error) {
    clearTimeout(safetyTimeout)
    console.error('[ROUTER] Error en router guard:', error)
    // En caso de error, permitir la navegación para evitar bloqueos
    return safeNext()
  }
})

export { router };
export default function (app) {
  app.use(router)
}
