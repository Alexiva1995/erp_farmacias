// Composable para encapsular la lógica de manipulación y persistencia del menú e-commerce
import { ref, computed } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

export const useMenuSettings = () => {
  const brandingStore = useBrandingStore()
  
  const isLoading = ref(false)
  const isCategoriesLoading = ref(false)
  const isInitialLoading = ref(true)
  const categories = ref([])
  const menuItems = ref([])
  const initialSnapshot = ref('[]')

  // Detección de estado modificado (dirty state)
  const isDirty = computed(() => {
    return JSON.stringify(menuItems.value) !== initialSnapshot.value
  })

  // Obtener categorías desde backend
  const fetchCategories = async () => {
    isCategoriesLoading.value = true
    try {
      const { data } = await axios.get('/categories')
      categories.value = Array.isArray(data) ? data : (data.data || [])
    } catch (error) {
      console.error('Error al obtener categorías:', error)
      toast.error('Error al cargar las categorías de productos')
    } finally {
      isCategoriesLoading.value = false
    }
  }

  // Inicializar estado del menú
  const initMenu = async () => {
    isInitialLoading.value = true
    try {
      await brandingStore.fetchSettings()
      if (Array.isArray(brandingStore.settings?.ecommerce_menu)) {
        menuItems.value = JSON.parse(JSON.stringify(brandingStore.settings.ecommerce_menu))
        initialSnapshot.value = JSON.stringify(menuItems.value)
      }
      await fetchCategories()
    } catch (error) {
      console.error('Error al inicializar menú:', error)
      toast.error('Error al inicializar la configuración del menú')
    } finally {
      isInitialLoading.value = false
    }
  }

  // Añadir categoría
  const addCategory = (category) => {
    menuItems.value.push({
      id: `cat_${category.id}_${Date.now()}`,
      label: category.name.toUpperCase(),
      type: 'category',
      value: category.id,
      children: []
    })
    toast.success(`Categoría "${category.name}" añadida`)
  }

  // Añadir enlace personalizado
  const addCustomLink = ({ label, url }) => {
    menuItems.value.push({
      id: `custom_${Date.now()}`,
      label: label.trim().toUpperCase(),
      type: 'custom',
      value: url.trim() || '#',
      children: []
    })
    toast.success(`Enlace "${label}" añadido`)
  }

  // Mover elemento arriba
  const moveUp = (index) => {
    if (index === 0) return
    const item = menuItems.value[index]
    menuItems.value.splice(index, 1)
    menuItems.value.splice(index - 1, 0, item)
  }

  // Mover elemento abajo
  const moveDown = (index) => {
    if (index === menuItems.value.length - 1) return
    const item = menuItems.value[index]
    menuItems.value.splice(index, 1)
    menuItems.value.splice(index + 1, 0, item)
  }

  // Anidar elemento (máximo 1 nivel de profundidad)
  const makeChild = (index) => {
    if (index === 0) return
    const targetParent = menuItems.value[index - 1]
    const item = menuItems.value[index]

    if (!targetParent.children) {
      targetParent.children = []
    }
    
    // Traspasar hijos del elemento al nivel principal para evitar 3+ niveles
    if (item.children && item.children.length > 0) {
      menuItems.value.splice(index + 1, 0, ...item.children)
      item.children = []
    }

    targetParent.children.push(item)
    menuItems.value.splice(index, 1)
    toast.info(`"${item.label}" anidado como submenú`)
  }

  // Desanidar elemento
  const extractChild = ({ parentIndex, childIndex }) => {
    const child = menuItems.value[parentIndex].children[childIndex]
    menuItems.value[parentIndex].children.splice(childIndex, 1)
    menuItems.value.splice(parentIndex + 1, 0, child)
    toast.info(`"${child.label}" promovido al nivel principal`)
  }

  // Eliminar elemento principal
  const removeItem = (index) => {
    const item = menuItems.value[index]
    toast.confirm(`¿Desea eliminar "${item.label}" del menú?`, () => {
      menuItems.value.splice(index, 1)
      toast.success('Elemento eliminado')
    })
  }

  // Eliminar subelemento
  const removeChild = ({ parentIndex, childIndex }) => {
    const parent = menuItems.value[parentIndex]
    const child = parent.children[childIndex]
    toast.confirm(`¿Desea eliminar "${child.label}" de "${parent.label}"?`, () => {
      menuItems.value[parentIndex].children.splice(childIndex, 1)
      toast.success('Submenú eliminado')
    })
  }

  // Guardar menú en backend
  const saveMenu = async () => {
    isLoading.value = true
    try {
      await axios.post('/general-settings', {
        ecommerce_menu: menuItems.value
      })
      initialSnapshot.value = JSON.stringify(menuItems.value)
      toast.success('Menú de navegación actualizado correctamente')
      await brandingStore.fetchSettings()
    } catch (error) {
      console.error('Error al guardar menú:', error)
      toast.error('Error al guardar la configuración del menú')
    } finally {
      isLoading.value = false
    }
  }

  return {
    isLoading,
    isCategoriesLoading,
    isInitialLoading,
    isDirty,
    categories,
    menuItems,
    initMenu,
    addCategory,
    addCustomLink,
    moveUp,
    moveDown,
    makeChild,
    extractChild,
    removeItem,
    removeChild,
    saveMenu
  }
}
