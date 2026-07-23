<script setup>
import { ref, onMounted, computed } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const isCategoriesLoading = ref(false)
const categories = ref([])

// Enlaces que se pueden agregar
const menuItems = ref([])

// Elementos temporales para agregar enlaces manuales
const customLabel = ref('')
const customUrl = ref('')

// Validar enlace personalizado
const isCustomLinkValid = computed(() => {
  return customLabel.value.trim().length > 0
})

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
}

// Agregar enlace personalizado
const addCustomLink = () => {
  if (!isCustomLinkValid.value) return
  menuItems.value.push({
    id: 'custom_' + Date.now(),
    label: customLabel.value.trim().toUpperCase(),
    type: 'custom',
    value: customUrl.value.trim() || '#',
    children: []
  })
  customLabel.value = ''
  customUrl.value = ''
}

// Eliminar un elemento principal del menú con confirmación
const removeItem = (index) => {
  const item = menuItems.value[index]
  toast.confirm(`¿Eliminar "${item.label}" del menú?`, () => {
    const updated = [...menuItems.value]
    updated.splice(index, 1)
    menuItems.value = updated
    toast.success('Elemento eliminado del menú')
  })
}

// Eliminar un elemento hijo con confirmación
const removeChildItem = (parentIndex, childIndex) => {
  const parent = menuItems.value[parentIndex]
  const child = parent.children[childIndex]
  toast.confirm(`¿Eliminar "${child.label}" de los submenús de "${parent.label}"?`, () => {
    const updated = [...menuItems.value]
    updated[parentIndex].children.splice(childIndex, 1)
    menuItems.value = updated
    toast.success('Submenú eliminado')
  })
}

// Mover elemento arriba
const moveUp = (index) => {
  if (index === 0) return
  const updated = [...menuItems.value]
  const temp = updated[index]
  updated[index] = updated[index - 1]
  updated[index - 1] = temp
  menuItems.value = updated
}

// Mover elemento abajo
const moveDown = (index) => {
  if (index === menuItems.value.length - 1) return
  const updated = [...menuItems.value]
  const temp = updated[index]
  updated[index] = updated[index + 1]
  updated[index + 1] = temp
  menuItems.value = updated
}

// Anidar elemento (WordPress style: hacerlo hijo del elemento anterior)
const makeChild = (index) => {
  if (index === 0) return
  const updated = [...menuItems.value]
  const item = updated[index]
  if (!updated[index - 1].children) {
    updated[index - 1].children = []
  }
  updated[index - 1].children.push(item)
  updated.splice(index, 1)
  menuItems.value = updated
}

// Desanidar (sacar al nivel principal)
const extractChild = (parentIndex, childIndex) => {
  const updated = [...menuItems.value]
  const child = updated[parentIndex].children[childIndex]
  updated.splice(parentIndex + 1, 0, child)
  updated[parentIndex].children.splice(childIndex, 1)
  menuItems.value = updated
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
  await brandingStore.fetchSettings()
  if (Array.isArray(brandingStore.settings.ecommerce_menu)) {
    menuItems.value = JSON.parse(JSON.stringify(brandingStore.settings.ecommerce_menu))
  }
  await fetchCategories()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="rounded-0 border-0" variant="flat">
        <VCardItem class="px-0 pt-0 pb-6">
          <VCardTitle class="text-h4 font-weight-light text-uppercase tracking-wider">Menú del E-commerce</VCardTitle>
          <VCardSubtitle class="text-muted text-caption mt-1">
            Diseña y estructura la barra de navegación de tu tienda online estilo editorial
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="px-0">
          <VRow>
            <!-- Lado Izquierdo: Selector de Enlaces y Categorías -->
            <VCol cols="12" md="4" class="d-flex flex-column gap-6">
              <!-- Agregar Categorías -->
              <div class="border pa-6 rounded-0 bg-white">
                <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-4 d-flex align-center gap-2">
                  <VIcon icon="tabler-tags" size="18" class="text-primary" />
                  Categorías
                </h3>
                <p class="text-caption text-muted mb-4">Selecciona categorías de productos para añadirlas directamente a tu menú principal.</p>
                
                <div v-if="isCategoriesLoading" class="d-flex flex-column gap-2 py-4">
                  <VSkeletonLoader type="list-item" v-for="n in 3" :key="n" class="border" />
                </div>
                
                <div v-else class="d-flex flex-column gap-2 max-h-60 overflow-y-auto pr-1">
                  <div
                    v-for="cat in categories"
                    :key="cat.id"
                    class="d-flex align-center justify-space-between border pa-2 hover-bg-light cursor-pointer transition-all"
                    :class="{ 'opacity-50 pointer-events-none': isLoading }"
                    @click="!isLoading && addCategoryToMenu(cat)"
                  >
                    <span class="text-body-2 font-weight-medium">{{ cat.name }}</span>
                    <VIcon icon="tabler-plus" size="16" class="text-primary" />
                  </div>
                  <div v-if="!categories.length" class="text-caption text-center text-muted py-4">
                    No hay categorías registradas.
                  </div>
                </div>
              </div>

              <!-- Enlaces Personalizados -->
              <div class="border pa-6 rounded-0 bg-white">
                <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-4 d-flex align-center gap-2">
                  <VIcon icon="tabler-link" size="18" class="text-primary" />
                  Enlace Personalizado
                </h3>
                <VForm @submit.prevent="addCustomLink" class="d-flex flex-column gap-4">
                  <VTextField
                    v-model="customLabel"
                    label="Etiqueta del enlace"
                    placeholder="Ej: OFERTAS, NOSOTROS"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    :disabled="isLoading"
                  />
                  <VTextField
                    v-model="customUrl"
                    label="URL / Enlace"
                    placeholder="Ej: #catalog, /contacto"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    :disabled="isLoading"
                  />
                  <VBtn
                    type="submit"
                    variant="outlined"
                    color="primary"
                    class="rounded-0 mt-2 text-uppercase tracking-wider"
                    block
                    :disabled="!isCustomLinkValid || isLoading"
                  >
                    Añadir al Menú
                  </VBtn>
                </VForm>
              </div>
            </VCol>

            <!-- Lado Derecho: Estructura del Menú -->
            <VCol cols="12" md="8">
              <div class="border pa-6 rounded-0 bg-white h-100 d-flex flex-column">
                <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-4 d-flex align-center gap-2">
                  <VIcon icon="tabler-layout-navbar" size="18" class="text-primary" />
                  Estructura del Menú
                </h3>
                <p class="text-caption text-muted mb-6">
                  Organiza y estructura tus enlaces de navegación. Puedes cambiar el orden y anidarlos para crear submenús desplegables de alta gama.
                </p>

                <!-- Lista de Enlaces -->
                <div class="flex-grow-1 d-flex flex-column gap-3 mb-6" style="min-height: 200px;">
                  <div v-if="!menuItems.length" class="border border-dashed d-flex align-center justify-center py-12 rounded-0 bg-grey-lighten-5">
                    <span class="text-caption text-muted text-uppercase tracking-wider">El menú está vacío. Agrega elementos desde el panel izquierdo.</span>
                  </div>

                  <TransitionGroup name="list" tag="div" class="d-flex flex-column gap-3">
                    <!-- Iteración de items principales -->
                    <div v-for="(item, idx) in menuItems" :key="item.id" class="d-flex flex-column gap-2 transition-all">
                      <!-- Item Principal -->
                      <div class="border pa-3 bg-white d-flex align-center justify-space-between hover-shadow">
                        <div class="d-flex align-center gap-3">
                          <VIcon icon="tabler-menu-2" class="text-muted cursor-move" size="18" />
                          <div>
                            <span class="text-body-2 font-weight-bold tracking-wide">{{ item.label }}</span>
                            <span class="text-xxs text-uppercase px-2 py-0.5 ml-2 border bg-light text-muted">
                              {{ item.type === 'category' ? 'Categoría' : 'Enlace' }}
                            </span>
                          </div>
                        </div>

                        <!-- Acciones -->
                        <div class="d-flex align-center gap-1">
                          <VBtn icon variant="text" size="small" :disabled="idx === 0 || isLoading" @click="moveUp(idx)">
                            <VIcon icon="tabler-arrow-up" size="16" />
                          </VBtn>
                          <VBtn icon variant="text" size="small" :disabled="idx === menuItems.length - 1 || isLoading" @click="moveDown(idx)">
                            <VIcon icon="tabler-arrow-down" size="16" />
                          </VBtn>
                          <VBtn icon variant="text" size="small" :disabled="idx === 0 || isLoading" @click="makeChild(idx)" title="Anidar en el anterior">
                            <VIcon icon="tabler-indent-increase" size="16" />
                          </VBtn>
                          <VBtn icon variant="text" size="small" color="error" :disabled="isLoading" @click="removeItem(idx)">
                            <VIcon icon="tabler-trash" size="16" />
                          </VBtn>
                        </div>
                      </div>

                      <!-- Items Hijos (Submenús) -->
                      <div v-if="item.children && item.children.length" class="pl-8 d-flex flex-column gap-2 border-left ml-4 py-1">
                        <TransitionGroup name="list" tag="div" class="d-flex flex-column gap-2">
                          <div
                            v-for="(child, childIdx) in item.children"
                            :key="child.id"
                            class="border pa-3 bg-grey-lighten-5 d-flex align-center justify-space-between hover-shadow transition-all"
                          >
                            <div class="d-flex align-center gap-3">
                              <VIcon icon="tabler-corner-down-right" class="text-muted" size="18" />
                              <div>
                                <span class="text-body-2 font-weight-medium tracking-wide">{{ child.label }}</span>
                                <span class="text-xxs text-uppercase px-2 py-0.5 ml-2 border bg-white text-muted">
                                  {{ child.type === 'category' ? 'Categoría' : 'Enlace' }}
                                </span>
                              </div>
                            </div>

                            <!-- Acciones de Hijos -->
                            <div class="d-flex align-center gap-1">
                              <VBtn icon variant="text" size="small" :disabled="isLoading" @click="extractChild(idx, childIdx)" title="Mover al menú principal">
                                <VIcon icon="tabler-indent-decrease" size="16" />
                              </VBtn>
                              <VBtn icon variant="text" size="small" color="error" :disabled="isLoading" @click="removeChildItem(idx, childIdx)">
                                <VIcon icon="tabler-trash" size="16" />
                              </VBtn>
                            </div>
                          </div>
                        </TransitionGroup>
                      </div>
                    </div>
                  </TransitionGroup>
                </div>

                <!-- Botón Guardar -->
                <div class="d-flex justify-end border-top pt-4 mt-auto">
                  <VBtn
                    color="primary"
                    size="large"
                    class="rounded-0 px-10 text-uppercase tracking-wider"
                    :loading="isLoading"
                    :disabled="!menuItems.length || isLoading"
                    @click="saveMenu"
                  >
                    Guardar Menú
                  </VBtn>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.hover-bg-light:hover {
  background-color: #f8f9fa;
}
.hover-shadow:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}
.cursor-move {
  cursor: grab;
}
.border-dashed {
  border-style: dashed !important;
}

/* Transiciones animadas para ordenar elementos */
.list-move,
.list-enter-active,
.list-leave-active {
  transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
.list-leave-active {
  position: absolute;
  width: 100%;
}
</style>
