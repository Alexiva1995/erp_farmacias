import navItems from '@/navigation/vertical'
import axios from '@/plugins/axios'
import { computed, ref } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'

export function useRolePermissions() {
  const brandingStore = useBrandingStore()
  const roles = ref([])
  const isLoading = ref(false)
  const isSaving = ref(false)
  const errorMessage = ref('')

  /**
   * Generar una clave única y consistente para cada ítem del menú de navegación.
   */
  function getItemKey(item, parentKey = '') {
    if (typeof item.to === 'string') {
      return item.to
    }
    if (item.to && typeof item.to === 'object') {
      return item.to.name || item.to.path || item.title
    }
    const cleanTitle = (item.title || 'item').toLowerCase().replace(/\s+/g, '_')
    return parentKey ? `${parentKey}::${cleanTitle}` : `module::${cleanTitle}`
  }

  /**
   * Construir el árbol de permisos a partir de los navItems del sistema.
   */
  function buildMenuPermissionTree(items, parentKey = '') {
    return items.map(item => {
      const key = getItemKey(item, parentKey)
      const hasChildren = Array.isArray(item.children) && item.children.length > 0
      
      let children = []
      let allDescendantKeys = [key]

      if (hasChildren) {
        children = buildMenuPermissionTree(item.children, key)
        children.forEach(child => {
          allDescendantKeys.push(...child.allDescendantKeys)
        })
      }

      return {
        key,
        title: item.title || 'Sin Título',
        to: item.to || null,
        icon: item.icon || null,
        hasChildren,
        children,
        allDescendantKeys: Array.from(new Set(allDescendantKeys)),
      }
    })
  }

  const filteredNavItems = computed(() => {
    const currentVertical = (brandingStore.settings.vertical || brandingStore.settings.business_type || localStorage.getItem('vertical') || localStorage.getItem('business_type') || 'pharmacy').toLowerCase()
    const isRestaurantVertical = currentVertical === 'restaurant' || currentVertical === 'fast_food'
    const isDrogueriaVertical = currentVertical === 'drogueria'
    const currentHost = typeof window !== 'undefined' ? window.location.hostname.toLowerCase() : ''
    const isMasterAllowedDomain = currentHost.includes('farmaciabs.com') || currentHost === 'localhost' || currentHost === '127.0.0.1' || currentHost.endsWith('.test')

    return navItems.filter(item => {
      const title = (item.title || '').toLowerCase()
      const to = (item.to || '').toLowerCase()

      if (title === 'master' || to.startsWith('master-')) {
        return isMasterAllowedDomain
      }
      if (title === 'restaurante' || title === 'operativa' || to.startsWith('restaurant-')) {
        return isRestaurantVertical
      }
      if (title === 'droguería b2b' || title === 'drogueria b2b' || to.startsWith('wholesale-')) {
        return isDrogueriaVertical
      }
      return true
    })
  })

  const menuTree = computed(() => buildMenuPermissionTree(filteredNavItems.value))

  /**
   * Obtener todas las claves posibles del menú del sistema.
   */
  const allAvailableKeys = computed(() => {
    const keys = []
    function collect(nodes) {
      nodes.forEach(node => {
        keys.push(node.key)
        if (node.children?.length) {
          collect(node.children)
        }
      })
    }
    collect(menuTree.value)
    return Array.from(new Set(keys))
  })

  /**
   * Cargar listado de roles desde la API.
   */
  async function fetchRoles() {
    isLoading.value = true
    errorMessage.value = ''
    try {
      const response = await axios.get('/roles')
      // La API puede devolver { data: [...] } o [...] directamente
      roles.value = response.data?.data || response.data || []
    } catch (error) {
      console.error('Error al cargar roles:', error)
      errorMessage.value = error.response?.data?.message || 'Error al cargar los roles del sistema.'
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Crear un nuevo rol.
   */
  async function createRole(roleData) {
    isSaving.value = true
    try {
      const response = await axios.post('/roles', roleData)
      await fetchRoles()
      return { success: true, data: response.data }
    } catch (error) {
      console.error('Error al crear rol:', error)
      return {
        success: false,
        message: error.response?.data?.message || error.response?.data?.errors?.name?.[0] || 'Error al crear el rol.',
      }
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Actualizar el nombre o datos de un rol existente.
   */
  async function updateRole(roleId, roleData) {
    isSaving.value = true
    try {
      const response = await axios.put(`/roles/${roleId}`, roleData)
      await fetchRoles()
      return { success: true, data: response.data }
    } catch (error) {
      console.error('Error al actualizar rol:', error)
      return {
        success: false,
        message: error.response?.data?.message || error.response?.data?.errors?.name?.[0] || 'Error al actualizar el rol.',
      }
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Guardar permisos de menú seleccionados para un rol.
   */
  async function saveRoleMenuPermissions(roleId, menuPermissions) {
    isSaving.value = true
    try {
      const response = await axios.put(`/roles/${roleId}/menu-permissions`, {
        menu_permissions: menuPermissions,
      })
      await fetchRoles()
      return { success: true, data: response.data }
    } catch (error) {
      console.error('Error al guardar permisos de menú:', error)
      return {
        success: false,
        message: error.response?.data?.message || 'Error al guardar los permisos de menú.',
      }
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Eliminar un rol.
   */
  async function deleteRole(roleId) {
    isSaving.value = true
    try {
      await axios.delete(`/roles/${roleId}`)
      await fetchRoles()
      return { success: true }
    } catch (error) {
      console.error('Error al eliminar rol:', error)
      return {
        success: false,
        message: error.response?.data?.message || error.response?.data?.errors?.id?.[0] || 'Error al eliminar el rol.',
      }
    } finally {
      isSaving.value = false
    }
  }

  return {
    roles,
    isLoading,
    isSaving,
    errorMessage,
    menuTree,
    allAvailableKeys,
    getItemKey,
    fetchRoles,
    createRole,
    updateRole,
    saveRoleMenuPermissions,
    deleteRole,
  }
}
