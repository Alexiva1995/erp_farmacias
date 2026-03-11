import { useAuthStore } from '@/stores/auth'; // <-- 1. IMPORTA TU AUTH STORE
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
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }
    
    return { top: 0 }
  },

  extendRoutes: pages => {
    const publicRoutes = ['/login', '/p/suppliers/upload/:token']

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

    const pagesWithAuth = addAuthMeta(pages)
    
    // Agregar ruta manual para renuncias (siguiendo el patrón del proyecto)
    const manualRoutes = [
      {
        path: '/rrhh/resignations',
        name: 'rrhh-resignations',
        component: () => import('@/pages/rrhh/resignations/index.vue'),
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
  
  // Timeout de seguridad: si el guard tarda más de 3 segundos, permitir navegación
  const safetyTimeout = setTimeout(() => {
    console.warn('[ROUTER] Timeout de seguridad alcanzado, permitiendo navegación')
    safeNext()
  }, 3000)
  
  try {
    const authStore = useAuthStore()
    console.log('[ROUTER] AuthStore estado:', { isLoaded: authStore.isLoaded, hasUser: !!authStore.user })
    
    // Solo intentar obtener el usuario si la ruta requiere autenticación y no está cargado aún
    const requiresAuth = to.meta?.requiresAuth
    if (requiresAuth && !authStore.isLoaded && !authStore.user && !isFetchingUser) {
      console.log('[ROUTER] Intentando obtener usuario...')
      isFetchingUser = true
      
      try {
        fetchUserPromise = authStore.fetchUser()
        const timeoutPromise = new Promise((resolve) => setTimeout(resolve, 1500))
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
        const timeoutPromise = new Promise((resolve) => setTimeout(resolve, 1500))
        await Promise.race([fetchUserPromise, timeoutPromise])
      } catch (error) {
        console.warn('[ROUTER] Error esperando fetchUser:', error)
      }
    }
    
    clearTimeout(safetyTimeout)
    
    const isAuthenticated = authStore.isAuthenticated
    console.log('[ROUTER] Verificación:', { isAuthenticated, requiresAuth })
    
    if (requiresAuth && !isAuthenticated) {
      console.log('[ROUTER] Redirigiendo a login')
      return safeNext({ path: '/login' })
    }
    
    if (to.path === '/login' && isAuthenticated) {
      console.log('[ROUTER] Redirigiendo a invoices')
      return safeNext({ path: '/invoice/invoices' })
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
