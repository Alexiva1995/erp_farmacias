<script setup>
import { onMounted, onUnmounted, ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'
import AppFilterBase from "@/components/AppFilterBase.vue"
import AppMobilePagination from "@/components/AppMobilePagination.vue"
import AppEmptyState from "@/components/AppEmptyState.vue"
import { toast } from "@/plugins/sweetalert"
import Swal from "sweetalert2"
import { useAbility } from "@casl/vue"
import { useBrandingStore } from "@/stores/useBrandingStore"

const { can } = useAbility()
const router = useRouter()
const brandingStore = useBrandingStore()

const showDishesColumn = computed(() => {
  const enableDishes = brandingStore.settings.enable_dishes ?? false
  const types = brandingStore.settings.enabled_product_types || []
  const hasIngredients = Array.isArray(types) && types.includes('ingredients')
  return Boolean(enableDishes && hasIngredients)
})

// --- Estados ---
const categories = ref([])
const loading = ref(false)
const isSaving = ref(false)
const totalCategories = ref(0)
const searchQuery = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('name')
const orderBy = ref('asc')

let debounceTimer = null

const isDialogOpen = ref(false)
const categoryForm = ref({ id: null, name: '' })
const isRunningAi = ref(false)

// Ejecutar categorización con IA manualmente
const runAiCategorize = async () => {
  const result = await Swal.fire({
    title: "¿Categorizar productos con IA?",
    text: "La Inteligencia Artificial (Gemini) analizará los productos que aún no tienen categoría y los asignará automáticamente.",
    icon: "info",
    showCancelButton: true,
    confirmButtonText: "Sí, analizar y categorizar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "v-theme-primary",
    reverseButtons: true,
  })

  if (result.isConfirmed) {
    isRunningAi.value = true
    try {
      const { data } = await axios.post('/inventory/categories-manage/ai-categorize')
      toast.success(data.message || "Categorización con IA completada exitosamente.")
      await fetchCategories()
    } catch (error) {
      console.error("Error al categorizar con IA:", error)
      toast.error(error.response?.data?.message || "Ocurrió un error al categorizar con IA.")
    } finally {
      isRunningAi.value = false
    }
  }
}

// Cabeceras de la tabla
const headers = computed(() => {
  const list = [
    {
      title: "ID",
      key: "id",
      sortable: true,
      cellClass: 'font-weight-black text-primary d-none d-sm-table-cell',
      headerClass: 'd-none d-sm-table-cell',
      width: '80px',
    },
    { title: "Categoría de Inventario", key: "name", sortable: true, width: '40%' },
    { title: "Productos", key: "products_count", sortable: true, align: 'center', width: '120px' },
  ]
  
  if (showDishesColumn.value) {
    list.push({ title: "Platos / Menú", key: "dishes_count", sortable: true, align: 'center', width: '140px' })
  }
  
  list.push({ title: "Acciones", key: "actions", sortable: false, align: 'center', width: '140px' })
  return list
})

// Obtener categorías paginadas
const fetchCategories = async () => {
  loading.value = true
  try {
    const params = {
      search: searchQuery.value,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value
    }
    const { data } = await axios.get('/inventory/categories-manage', { params })
    categories.value = data.data
    totalCategories.value = data.total
  } catch (error) {
    console.error('Error al cargar categorías:', error)
    toast.error('Error al cargar las categorías')
  } finally {
    loading.value = false
  }
}

// Abrir diálogo de creación/edición
const openEdit = (category = null) => {
  categoryForm.value = category ? { id: category.id, name: category.name } : { id: null, name: '' }
  isDialogOpen.value = true
}

const goToProducts = (category) => {
  router.push({ path: '/inventory/products', query: { categoryId: category.id } })
}

// Guardar categoría
const saveCategory = async () => {
  if (!categoryForm.value.name.trim()) {
    toast.error("El nombre de la categoría es obligatorio")
    return
  }
  isSaving.value = true
  try {
    await axios.post('/inventory/categories-manage', categoryForm.value)
    toast.success("Categoría guardada correctamente")
    isDialogOpen.value = false
    fetchCategories()
  } catch (error) {
    toast.error('Error al guardar la categoría')
  } finally {
    isSaving.value = false
  }
}

// Eliminar categoría
const deleteCategory = async (id) => {
  const result = await Swal.fire({
    title: "¿Eliminar categoría?",
    text: "Los productos y platos asociados quedarán sin categoría asignada.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "v-theme-error",
    reverseButtons: true,
  })
  if (result.isConfirmed) {
    try {
      await axios.delete(`/inventory/categories-manage/${id}`)
      toast.success("Categoría eliminada con éxito")
      fetchCategories()
    } catch (error) {
      toast.error("Error al eliminar la categoría")
    }
  }
}

const updateTableOptions = o => {
  page.value = o.page
  itemsPerPage.value = o.itemsPerPage
  if (o.sortBy?.length) {
    sortBy.value = o.sortBy[0].key
    orderBy.value = o.sortBy[0].order
  }
}

onMounted(() => {
  brandingStore.fetchSettings()
  fetchCategories()
})

watch([searchQuery], () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    page.value = 1
    fetchCategories()
  }, 300)
})

watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchCategories()
})

onUnmounted(() => clearTimeout(debounceTimer))
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Encabezado con Filtros -->
    <AppFilterBase
      v-model:search="searchQuery"
      :show-add="true"
      add-button-text="Añadir Categoría"
      @clear="searchQuery = ''; fetchCategories()"
      @add="openEdit()"
    >
      <template #actions-extra>
        <VBtn
          icon
          color="purple"
          variant="tonal"
          size="38"
          rounded="circle"
          :loading="isRunningAi"
          :disabled="isRunningAi"
          @click="runAiCategorize"
        >
          <VIcon icon="tabler-sparkles" />
          <VTooltip activator="parent" location="top">Categorizar con IA (Gemini)</VTooltip>
        </VBtn>
      </template>
    </AppFilterBase>

    <!-- Card de la Tabla -->
    <VCard class="rounded-lg border shadow-sm mt-4 overflow-hidden">
      <!-- Cabecera Estándar -->
      <VCardTitle class="d-flex align-center pa-4">
        <span class="text-h6 font-weight-bold">Listado de Categorías de Inventario</span>
        <VSpacer />
        <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
          {{ totalCategories }} CATEGORÍAS
        </VChip>
      </VCardTitle>

      <VDivider />

      <!-- Versión Escritorio -->
      <div class="d-none d-md-block">
        <VDataTableServer
          :headers="headers"
          :items="categories"
          :items-length="totalCategories"
          :loading="loading"
          @update:options="updateTableOptions"
          density="compact"
          hover
          class="text-no-wrap"
        >
          <template #item.id="{ item }">
            <span class="font-weight-black text-primary">
              {{ item.id }}
            </span>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center gap-2 py-2">
              <div class="header-indicator success rounded-pill"></div>
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ item.name }}</span>
            </div>
          </template>

          <template #item.products_count="{ item }">
            <div class="text-center">
              <VChip
                :color="item.products_count > 0 ? 'primary' : 'secondary'"
                size="x-small"
                variant="tonal"
                label
                class="font-weight-black"
              >
                {{ item.products_count }}
              </VChip>
            </div>
          </template>

          <template #item.dishes_count="{ item }">
            <div class="text-center">
              <VChip
                :color="item.dishes_count > 0 ? 'success' : 'secondary'"
                size="x-small"
                variant="tonal"
                label
                class="font-weight-black"
              >
                {{ item.dishes_count }}
              </VChip>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center gap-1 px-2">
              <VTooltip text="Ver productos" location="top">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn v-bind="tooltipProps" @click="goToProducts(item)" color="info" size="small">
                    <VIcon icon="tabler-eye" size="18" />
                  </IconBtn>
                </template>
              </VTooltip>
              <VTooltip text="Editar" location="top">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn v-bind="tooltipProps" @click="openEdit(item)" color="warning" size="small">
                    <VIcon icon="tabler-edit" size="18" />
                  </IconBtn>
                </template>
              </VTooltip>
              <VTooltip v-if="can('manage', 'admin')" text="Eliminar" location="top">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn v-bind="tooltipProps" @click="deleteCategory(item.id)" color="error" size="small">
                    <VIcon icon="tabler-trash" size="18" />
                  </IconBtn>
                </template>
              </VTooltip>
            </div>
          </template>

          <template #no-data>
            <AppEmptyState
              title="No se encontraron categorías"
              message="Registra categorías para clasificar tus productos y facilitar su búsqueda en el inventario."
              icon="tabler-category"
            >
              <template #actions>
                <VBtn
                  color="primary"
                  variant="flat"
                  prepend-icon="tabler-plus"
                  @click="openEdit()"
                >
                  Crear Categoría
                </VBtn>
              </template>
            </AppEmptyState>
          </template>
        </VDataTableServer>
      </div>

      <!-- Versión Móvil -->
      <div class="d-block d-md-none pa-2">
        <div v-if="loading" class="d-flex flex-column gap-2">
          <VProgressLinear indeterminate color="primary" class="mb-2" />
          <VSkeletonLoader v-for="i in 3" :key="i" type="list-item-two-line" class="mb-2 rounded-lg border" />
        </div>

        <div v-else-if="categories.length" class="d-flex flex-column gap-2">
          <VCard
            v-for="item in categories"
            :key="item.id"
            variant="flat"
            class="border mb-1 rounded-lg pa-3"
          >
            <div class="d-flex justify-space-between align-center mb-2">
              <div class="d-flex align-center gap-2">
                <span class="text-xs font-weight-black text-primary">#{{ item.id }}</span>
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ item.name }}</span>
              </div>
              <div class="d-flex gap-1">
                <IconBtn size="small" color="info" @click="goToProducts(item)">
                  <VIcon icon="tabler-eye" size="18" />
                </IconBtn>
                <IconBtn size="small" color="warning" @click="openEdit(item)">
                  <VIcon icon="tabler-edit" size="18" />
                </IconBtn>
                <IconBtn v-if="can('manage', 'admin')" size="small" color="error" @click="deleteCategory(item.id)">
                  <VIcon icon="tabler-trash" size="18" />
                </IconBtn>
              </div>
            </div>

            <VDivider class="my-2" />

            <div class="d-flex justify-space-between align-center bg-var-theme-background px-3 py-2 rounded">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-medium-emphasis text-uppercase font-weight-bold">Productos</span>
                <VChip
                  :color="item.products_count > 0 ? 'primary' : 'secondary'"
                  size="x-small"
                  variant="tonal"
                  label
                  class="font-weight-black mt-1"
                >
                  {{ item.products_count }}
                </VChip>
              </div>

              <div v-if="showDishesColumn" class="d-flex flex-column align-end">
                <span class="text-super-xs text-medium-emphasis text-uppercase font-weight-bold">Platos / Menú</span>
                <VChip
                  :color="item.dishes_count > 0 ? 'success' : 'secondary'"
                  size="x-small"
                  variant="tonal"
                  label
                  class="font-weight-black mt-1"
                >
                  {{ item.dishes_count }}
                </VChip>
              </div>
            </div>
          </VCard>
          <AppMobilePagination :page="page" :items-per-page="itemsPerPage" :total-items="totalCategories" @change="updateTableOptions" />
        </div>

        <div v-else>
          <AppEmptyState
            title="No hay categorías registradas"
            message="Registra tu primera categoría para comenzar."
            icon="tabler-category"
          >
            <template #actions>
              <VBtn
                color="primary"
                variant="flat"
                size="small"
                prepend-icon="tabler-plus"
                @click="openEdit()"
              >
                Crear Categoría
              </VBtn>
            </template>
          </AppEmptyState>
        </div>
      </div>
    </VCard>

    <!-- Diálogo para crear/editar categoría -->
    <VDialog v-model="isDialogOpen" max-width="500">
      <VCard class="rounded-xl shadow-xl border-0 overflow-hidden">
        <!-- Cabecera Premium con gradiente -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" class="me-3 elevation-1" size="40">
              <VIcon icon="tabler-category" size="24" color="primary" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                {{ categoryForm.id ? 'Editar' : 'Nueva' }} Categoría
              </h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold mt-1">
                Clasificación de productos e ingredientes
              </span>
            </div>
            <VSpacer />
            <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="isDialogOpen = false" />
          </div>
        </VCardTitle>

        <VCardText class="pa-6 pt-6">
          <VRow>
            <VCol cols="12">
              <p class="text-xs font-weight-black text-primary text-uppercase mb-2 ls-1">Información General</p>
              <AppTextField
                v-model="categoryForm.name"
                label="Nombre de la Categoría"
                placeholder="Ej: Waffles, Bebidas, Helados..."
                persistent-placeholder
                class="mb-4"
                @keyup.enter="saveCategory"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-light border-t">
          <VRow no-gutters class="w-100">
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="tonal"
                size="large"
                block
                height="50"
                class="font-weight-black rounded-lg text-button uppercase"
                :disabled="isSaving"
                @click="isDialogOpen = false"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="primary"
                variant="flat"
                size="large"
                block
                height="50"
                class="font-weight-black rounded-lg shadow-primary text-button uppercase"
                :loading="isSaving"
                :disabled="isSaving"
                @click="saveCategory"
              >
                Guardar Categoría
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.bg-var-theme-background { background-color: rgba(var(--v-border-color), 0.05); }
.text-super-xs { font-size: 0.65rem !important; }
:deep(.v-data-table th) { font-size: 0.75rem !important; font-weight: 700 !important; text-transform: uppercase; }

.header-gradient {
  background: var(--brand-gradient) !important;
}
.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}
.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
