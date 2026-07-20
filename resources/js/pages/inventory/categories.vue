<script setup>
import { onMounted, onUnmounted, ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'
import AppFilterBase from "@/components/AppFilterBase.vue"
import AppMobilePagination from "@/components/AppMobilePagination.vue"
import { toast } from "@/plugins/sweetalert"
import Swal from "sweetalert2"
import { useAbility } from "@casl/vue"

import { useBrandingStore } from "@/stores/useBrandingStore"

const { can } = useAbility()
const router = useRouter()
const brandingStore = useBrandingStore()

const isMiniMarket = computed(() => brandingStore.settings.business_type === 'minimarket')
const isSportsRental = computed(() => brandingStore.settings.business_type === 'sports_rental')

// --- Estados ---
const categories = ref([])
const loading = ref(false)
const totalCategories = ref(0)
const searchQuery = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('name')
const orderBy = ref('asc')

let debounceTimer = null

const isDialogOpen = ref(false)
const categoryForm = ref({ id: null, name: '' })

// Cabeceras de la tabla
const headers = computed(() => {
  const list = [
    { title: "ID", key: "id", sortable: true, cellClass: 'font-weight-black text-primary', width: '80px' },
    { title: "Categoría de Inventario", key: "name", sortable: true },
    { title: "Productos", key: "products_count", sortable: true, align: 'center', width: '120px' },
  ]
  
  if (!isMiniMarket.value && !isSportsRental.value) {
    list.push({ title: "Platos / Menú", key: "dishes_count", sortable: true, align: 'center', width: '140px' })
  }
  
  list.push({ title: "Acciones", key: "actions", sortable: false, align: 'right', width: '140px' })
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
  } finally {
    loading.value = false
  }
}

// Abrir diálogo de creación/edición
const openEdit = (category = null) => {
  categoryForm.value = category ? { id: category.id, name: category.name } : { id: null, name: '' }
  isDialogOpen.value = true
}

// Guardar categoría
const saveCategory = async () => {
  if (!categoryForm.value.name.trim()) {
    toast.error("El nombre de la categoría es obligatorio")
    return
  }
  try {
    await axios.post('/inventory/categories-manage', categoryForm.value)
    toast.success("Categoría guardada correctamente")
    isDialogOpen.value = false
    fetchCategories()
  } catch (error) {
    toast.error('Error al guardar la categoría')
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
    customClass: {
      confirmButton: 'v-btn v-btn--variant-flat bg-error text-white h-auto py-2 px-6 rounded-lg font-weight-black uppercase ms-3',
      cancelButton: 'v-btn v-btn--variant-tonal text-secondary h-auto py-2 px-6 rounded-lg font-weight-black uppercase'
    }
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
  <div>
    <!-- Encabezado con Filtros -->
    <AppFilterBase
      v-model:search="searchQuery"
      :show-add="true"
      add-button-text="Añadir Categoría"
      @clear="searchQuery = ''; fetchCategories()"
      @add="openEdit()"
    />

    <!-- Card de la Tabla -->
    <VCard class="rounded-lg border shadow-sm mt-4 overflow-hidden">
      <!-- Versión Escritorio -->
      <div class="d-none d-md-block">
        <VDataTableServer
          :headers="headers"
          :items="categories"
          :items-length="totalCategories"
          :loading="loading"
          @update:options="updateTableOptions"
          density="compact"
        >
          <template #item.products_count="{ item }">
            <VChip size="x-small" color="info" variant="flat" class="font-weight-bold">
              {{ item.products_count }}
            </VChip>
          </template>

          <template #item.dishes_count="{ item }">
            <VChip size="x-small" color="success" variant="flat" class="font-weight-bold">
              {{ item.dishes_count }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1 px-2">
              <IconBtn @click="openEdit(item)" color="primary" v-tooltip="'Editar'">
                <VIcon icon="tabler-edit" size="18" />
              </IconBtn>
              <IconBtn v-if="can('manage', 'admin')" @click="deleteCategory(item.id)" color="error" v-tooltip="'Eliminar'">
                <VIcon icon="tabler-trash" size="18" />
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Versión Móvil -->
      <div class="d-block d-md-none pa-2">
        <div class="d-flex flex-column gap-2">
          <VCard v-for="item in categories" :key="item.id" variant="flat" class="border mb-1 rounded-lg">
            <div class="pa-3">
              <div class="d-flex justify-space-between align-start mb-2">
                <div>
                  <div class="text-xs font-weight-black text-primary mb-1">ID: {{ item.id }}</div>
                  <h3 class="text-sm font-weight-black text-uppercase leading-tight">{{ item.name }}</h3>
                </div>
              </div>
              <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded">
                <div class="d-flex gap-2">
                  <span class="text-xs font-weight-bold">Prod: <b>{{ item.products_count }}</b></span>
                  <span v-if="!isMiniMarket" class="text-xs font-weight-bold">Platos: <b>{{ item.dishes_count }}</b></span>
                </div>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-edit" color="primary" variant="tonal" size="small" @click="openEdit(item)" />
                  <VBtn v-if="can('manage', 'admin')" icon="tabler-trash" color="error" variant="tonal" size="small" @click="deleteCategory(item.id)" />
                </div>
              </div>
            </div>
          </VCard>
        </div>
        <AppMobilePagination :page="page" :items-per-page="itemsPerPage" :total-items="totalCategories" @change="updateTableOptions" />
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
