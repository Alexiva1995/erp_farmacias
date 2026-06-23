<script setup>
import { ref, onMounted } from 'vue'
import { useBrandingStore } from '@/stores/useBrandingStore'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const categories = ref([])

// Enlaces que se pueden agregar
const menuItems = ref([])

// Elementos temporales para agregar enlaces manuales
const customLabel = ref('')
const customUrl = ref('')

const fetchCategories = async () => {
  try {
    const { data } = await axios.get('/categories')
    // Asumimos que data es un array o viene envuelto
    categories.value = Array.isArray(data) ? data : (data.data || [])
  } catch (e) {
    console.error('Error al obtener categorías:', e)
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
  if (!customLabel.value.trim()) return
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

// Eliminar un elemento del menú
const removeItem = (index) => {
  menuItems.value.splice(index, 1)
}

// Mover elemento arriba
const moveUp = (index) => {
  if (index === 0) return
  const temp = menuItems.value[index]
  menuItems.value[index] = menuItems.value[index - 1]
  menuItems.value[index - 1] = temp
}

// Mover elemento abajo
const moveDown = (index) => {
  if (index === menuItems.value.length - 1) return
  const temp = menuItems.value[index]
  menuItems.value[index] = menuItems.value[index + 1]
  menuItems.value[index + 1] = temp
}

// Anidar elemento (WordPress style: hacerlo hijo del elemento anterior)
const makeChild = (index) => {
  if (index === 0) return
  const item = menuItems.value[index]
  menuItems.value[index - 1].children.push(item)
  menuItems.value.splice(index, 1)
}

// Desanidar (sacar al nivel principal)
const extractChild = (parentIndex, childIndex) => {
  const child = menuItems.value[parentIndex].children[childIndex]
  menuItems.value.splice(parentIndex + 1, 0, child)
  menuItems.value[parentIndex].children.splice(childIndex, 1)
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
                <div class="d-flex flex-column gap-2 max-h-60 overflow-y-auto pr-1">
                  <div
                    v-for="cat in categories"
                    :key="cat.id"
                    class="d-flex align-center justify-space-between border pa-2 hover-bg-light cursor-pointer"
                    @click="addCategoryToMenu(cat)"
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
                  />
                  <VTextField
                    v-model="customUrl"
                    label="URL / Enlace"
                    placeholder="Ej: #catalog, /contacto"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                  />
                  <VBtn
                    type="submit"
                    variant="outlined"
                    color="primary"
                    class="rounded-0 mt-2 text-uppercase tracking-wider"
                    block
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
                  Ordena tus enlaces arrastrándolos o usando los controles. Puedes anidarlos para crear submenús desplegables de alta gama.
                </p>

                <!-- Lista de Enlaces -->
                <div class="flex-grow-1 d-flex flex-column gap-3 mb-6" style="min-height: 200px;">
                  <div v-if="!menuItems.length" class="border border-dashed d-flex align-center justify-center py-12 rounded-0 bg-grey-lighten-5">
                    <span class="text-caption text-muted text-uppercase tracking-wider">El menú está vacío. Agrega elementos desde el panel izquierdo.</span>
                  </div>

                  <!-- Iteración de items principales -->
                  <template v-for="(item, idx) in menuItems" :key="item.id">
                    <div class="d-flex flex-column gap-2">
                      <!-- Item Principal -->
                      <div class="border pa-3 bg-white d-flex align-center justify-space-between">
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
                          <VBtn icon variant="text" size="small" :disabled="idx === 0" @click="moveUp(idx)">
                            <VIcon icon="tabler-arrow-up" size="16" />
                          </VBtn>
                          <VBtn icon variant="text" size="small" :disabled="idx === menuItems.length - 1" @click="moveDown(idx)">
                            <VIcon icon="tabler-arrow-down" size="16" />
                          </VBtn>
                          <VBtn icon variant="text" size="small" :disabled="idx === 0" @click="makeChild(idx)" title="Anidar en el anterior">
                            <VIcon icon="tabler-indent-increase" size="16" />
                          </VBtn>
                          <VBtn icon variant="text" size="small" color="error" @click="removeItem(idx)">
                            <VIcon icon="tabler-trash" size="16" />
                          </VBtn>
                        </div>
                      </div>

                      <!-- Items Hijos (Submenús) -->
                      <div v-if="item.children && item.children.length" class="pl-8 d-flex flex-column gap-2 border-left ml-4 py-1">
                        <div
                          v-for="(child, childIdx) in item.children"
                          :key="child.id"
                          class="border pa-3 bg-grey-lighten-5 d-flex align-center justify-space-between"
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
                            <VBtn icon variant="text" size="small" @click="extractChild(idx, childIdx)" title="Mover al menú principal">
                              <VIcon icon="tabler-indent-decrease" size="16" />
                            </VBtn>
                            <VBtn icon variant="text" size="small" color="error" @click="item.children.splice(childIdx, 1)">
                              <VIcon icon="tabler-trash" size="16" />
                            </VBtn>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>

                <!-- Botón Guardar -->
                <div class="d-flex justify-end border-top pt-4 mt-auto">
                  <VBtn
                    color="primary"
                    size="large"
                    class="rounded-0 px-10 text-uppercase tracking-wider"
                    :loading="isLoading"
                    :disabled="!menuItems.length"
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
.cursor-move {
  cursor: grab;
}
.border-dashed {
  border-style: dashed !important;
}
</style>
