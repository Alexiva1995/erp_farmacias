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
    const publicRoutes = ['/login']

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
      }
    ]
    
    return [...pagesWithAuth.map(route => recursiveLayouts(route)), ...manualRoutes.map(route => recursiveLayouts(route))]
  },
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Solo intentar obtener el usuario si no está cargado aún
  if (!authStore.isLoaded && !authStore.user) {
    await authStore.fetchUser()
  }
  
  const isAuthenticated = authStore.isAuthenticated
  const requiresAuth = to.meta.requiresAuth
  
  if (requiresAuth && !isAuthenticated) {
    return next({ path: '/login' })
  }
  
  if (to.path === '/login' && isAuthenticated) {
    return next({ path: '/invoice/invoices' })
  }
  
  return next()
})

export { router };
export default function (app) {
  app.use(router)
}
