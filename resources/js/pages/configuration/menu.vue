<script setup>
import { ref, onMounted } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

import MenuCategorySelector from '@/components/configuration/MenuCategorySelector.vue'
import MenuCustomLinkBuilder from '@/components/configuration/MenuCustomLinkBuilder.vue'
import MenuItemList from '@/components/configuration/MenuItemList.vue'

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const isCategoriesLoading = ref(false)
const isInitialLoading = ref(true)
const categories = ref([])
const menuItems = ref([])

// Obtener categorías desde backend
const fetchCategories = async () => {
  isCategoriesLoading.value = true
  try {
    const { data } = await axios.get('/categories')
    categories.value = Array.isArray(data) ? data : (data.data || [])
  } catch (e) {
    console.error('Error al obtener categorías:', e)
    toast.error('Error al obtener las categorías')
  } finally {
    isCategoriesLoading.value = false
  }
}

// Agregar categoría al menú
const addCategoryToMenu = (category) => {
  menuItems.value.push({
    id: 'cat_' + category.id + '_' + Date.now(),
    label: category.name.toUpperCase(),
    type: 'category',
    value: category.id,
    children: []
  })
  toast.success(`Categoría "${category.name}" añadida al menú`)
}

// Agregar enlace personalizado
const addCustomLink = ({ label, url }) => {
  menuItems.value.push({
    id: 'custom_' + Date.now(),
    label: label,
    type: 'custom',
    value: url,
    children: []
  })
  toast.success(`Enlace "${label}" añadido al menú`)
}

// Eliminar un elemento principal del menú con confirmación
const removeItem = (index) => {
  const item = menuItems.value[index]
  toast.confirm(`¿Eliminar "${item.label}" del menú?`, () => {
    menuItems.value.splice(index, 1)
    toast.success('Elemento eliminado del menú')
  })
}

// Eliminar un elemento hijo con confirmación
const removeChildItem = ({ parentIndex, childIndex }) => {
  const parent = menuItems.value[parentIndex]
  const child = parent.children[childIndex]
  toast.confirm(`¿Eliminar "${child.label}" de los submenús de "${parent.label}"?`, () => {
    menuItems.value[parentIndex].children.splice(childIndex, 1)
    toast.success('Submenú eliminado')
  })
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

// Anidar elemento (WordPress style: hacerlo hijo del elemento anterior)
const makeChild = (index) => {
  if (index === 0) return
  const item = menuItems.value[index]
  if (!menuItems.value[index - 1].children) {
    menuItems.value[index - 1].children = []
  }
  menuItems.value[index - 1].children.push(item)
  menuItems.value.splice(index, 1)
  toast.info(`"${item.label}" anidado como submenú`)
}

// Desanidar (sacar al nivel principal)
const extractChild = ({ parentIndex, childIndex }) => {
  const child = menuItems.value[parentIndex].children[childIndex]
  menuItems.value[parentIndex].children.splice(childIndex, 1)
  menuItems.value.splice(parentIndex + 1, 0, child)
  toast.info(`"${child.label}" movido al nivel principal`)
}

// Guardar el menú en el backend
const saveMenu = async () => {
  isLoading.value = true
  try {
    await axios.post('/general-settings', {
      ecommerce_menu: menuItems.value
    })
    toast.success('Menú del e-commerce actualizado correctamente')
    await brandingStore.fetchSettings()
  } catch (e) {
    console.error('Error al guardar menú:', e)
    toast.error('Error al guardar el menú')
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  try {
    await brandingStore.fetchSettings()
    if (Array.isArray(brandingStore.settings.ecommerce_menu)) {
      menuItems.value = JSON.parse(JSON.stringify(brandingStore.settings.ecommerce_menu))
    }
    await fetchCategories()
  } finally {
    isInitialLoading.value = false
  }
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="rounded-0 border-0" variant="flat">
        <VCardItem class="px-0 pt-0 pb-6">
          <VCardTitle class="text-h4 font-weight-light text-uppercase tracking-wider">
            Menú del E-commerce
          </VCardTitle>
          <VCardSubtitle class="text-muted text-caption mt-1">
            Diseña y estructura la barra de navegación de tu tienda online estilo editorial
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="px-0">
          <VRow>
            <!-- Panel Izquierdo: Selección de Categorías y Enlaces -->
            <VCol cols="12" md="4" class="d-flex flex-column gap-6">
              <MenuCategorySelector
                :categories="categories"
                :loading="isCategoriesLoading || isInitialLoading"
                :disabled="isLoading"
                @add-category="addCategoryToMenu"
              />

              <MenuCustomLinkBuilder
                :disabled="isLoading || isInitialLoading"
                @add-custom-link="addCustomLink"
              />
            </VCol>

            <!-- Panel Derecho: Estructura y Gestión del Menú -->
            <VCol cols="12" md="8">
              <MenuItemList
                :menu-items="menuItems"
                :loading="isLoading"
                :disabled="isInitialLoading"
                @move-up="moveUp"
                @move-down="moveDown"
                @make-child="makeChild"
                @extract-child="extractChild"
                @remove-item="removeItem"
                @remove-child="removeChildItem"
                @save="saveMenu"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.gap-6 {
  gap: 24px;
}
</style>
