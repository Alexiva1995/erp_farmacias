<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'

// Estado de listado
const categories = ref([])
const isLoading = ref(true)
const searchQuery = ref('')

// Estado de diálogo para crear / editar
const isDialogOpen = ref(false)
const isSubmitting = ref(false)
const isEditing = ref(false)
const categoryFormRef = ref(null)

const form = reactive({
  id: null,
  name: '',
  error: '',
})

// Columnas de la tabla
const headers = [
  { title: 'ID', key: 'id', width: '80px', align: 'start' },
  { title: 'NOMBRE DE CATEGORÍA', key: 'name', align: 'start' },
  { title: 'USO EN GASTOS', key: 'total_usage_count', align: 'center', width: '160px' },
  { title: 'FECHA CREACIÓN', key: 'created_at', align: 'start', width: '180px' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center', width: '120px' },
]

// Categorías filtradas por búsqueda en cliente para velocidad instantánea
const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) return categories.value

  const query = searchQuery.value.toLowerCase().trim()
  return categories.value.filter(cat =>
    cat.name.toLowerCase().includes(query) || String(cat.id).includes(query)
  )
})

// Cargar categorías desde la API
const fetchCategories = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/expenses/category')
    categories.value = response.data?.data || []
  } catch (error) {
    console.error('Error al cargar categorías de gastos:', error)
    toast.error('No se pudieron cargar las categorías de gastos')
  } finally {
    isLoading.value = false
  }
}

// Abrir diálogo para nueva categoría
const openCreateDialog = () => {
  isEditing.value = false
  form.id = null
  form.name = ''
  form.error = ''
  isDialogOpen.value = true
}

// Abrir diálogo para editar categoría
const openEditDialog = (item) => {
  isEditing.value = true
  form.id = item.id
  form.name = item.name
  form.error = ''
  isDialogOpen.value = true
}

// Guardar categoría (crear o editar)
const submitForm = async () => {
  if (!form.name || !form.name.trim()) {
    form.error = 'El nombre de la categoría es obligatorio.'
    return
  }

  isSubmitting.value = true
  form.error = ''

  try {
    if (isEditing.value) {
      const response = await axios.put(`/expenses/category/${form.id}`, {
        name: form.name.trim(),
      })
      toast.success(response.data?.message || 'Categoría actualizada exitosamente')
    } else {
      const response = await axios.post('/expenses/category', {
        name: form.name.trim(),
      })
      toast.success(response.data?.message || 'Categoría creada exitosamente')
    }

    isDialogOpen.value = false
    await fetchCategories()
  } catch (error) {
    console.error('Error guardando categoría:', error)
    const apiErrors = error.response?.data?.errors
    if (apiErrors?.name?.[0]) {
      form.error = apiErrors.name[0]
    } else if (error.response?.data?.message) {
      form.error = error.response.data.message
    } else {
      form.error = 'Ocurrió un error inesperado al procesar la categoría.'
    }
  } finally {
    isSubmitting.value = false
  }
}

// Confirmar y eliminar categoría
const confirmDelete = async (item) => {
  if (item.total_usage_count > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Categoría en uso',
      text: `La categoría "${item.name}" está asignada a ${item.total_usage_count} gasto(s). No es posible eliminarla por consistencia contable.`,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#7367F0',
    })
    return
  }

  const result = await Swal.fire({
    title: '¿Eliminar categoría?',
    text: `¿Estás seguro de que deseas eliminar la categoría "${item.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EA5455',
    cancelButtonColor: '#828689',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`/expenses/category/${item.id}`)
      toast.success('Categoría eliminada exitosamente')
      await fetchCategories()
    } catch (error) {
      console.error('Error eliminando categoría:', error)
      const errorMsg = error.response?.data?.message || 'No se pudo eliminar la categoría'
      toast.error(errorMsg)
    }
  }
}

// Formatear fecha legible
const formatDate = (dateString) => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
}

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <VCard class="rounded-lg border shadow-sm mt-6">
    <VCardItem class="py-5">
      <!-- Encabezado de la Sección -->
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
        <div>
          <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2 mb-1">
            <VIcon icon="tabler-category-2" color="primary" size="24" />
            Categorías de Gastos
          </VCardTitle>
          <VCardSubtitle class="text-caption text-medium-emphasis px-0">
            Administra las categorías disponibles para clasificar y organizar los gastos de la empresa.
          </VCardSubtitle>
        </div>

        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          class="font-weight-medium"
          @click="openCreateDialog"
        >
          Nueva Categoría
        </VBtn>
      </div>

      <!-- Barra de Filtro / Búsqueda -->
      <div class="mb-4 d-flex justify-space-between align-center flex-wrap gap-4">
        <VTextField
          v-model="searchQuery"
          placeholder="Buscar categoría por nombre o ID..."
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-search"
          hide-details
          clearable
          style="max-width: 320px;"
        />

        <VBtn
          variant="tonal"
          color="secondary"
          size="small"
          prepend-icon="tabler-refresh"
          :loading="isLoading"
          @click="fetchCategories"
        >
          Actualizar
        </VBtn>
      </div>

      <!-- Tabla de Categorías -->
      <VDataTable
        :headers="headers"
        :items="filteredCategories"
        :loading="isLoading"
        items-per-page="10"
        hover
        class="border rounded-lg"
      >
        <!-- Estado de carga -->
        <template #loading>
          <VSkeletonLoader type="table-row@5" />
        </template>

        <!-- Columna ID -->
        <template #item.id="{ item }">
          <span class="font-weight-semibold text-caption text-medium-emphasis">#{{ item.id }}</span>
        </template>

        <!-- Columna Nombre -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar size="30" color="primary" variant="tonal" class="rounded">
              <VIcon icon="tabler-tag" size="16" />
            </VAvatar>
            <span class="font-weight-medium text-body-2">{{ item.name }}</span>
          </div>
        </template>

        <!-- Columna Uso -->
        <template #item.total_usage_count="{ item }">
          <VChip
            :color="item.total_usage_count > 0 ? 'info' : 'secondary'"
            size="small"
            variant="tonal"
            class="font-weight-medium"
          >
            {{ item.total_usage_count }} gasto(s)
          </VChip>
        </template>

        <!-- Columna Fecha de Creación -->
        <template #item.created_at="{ item }">
          <span class="text-caption text-medium-emphasis">{{ formatDate(item.created_at) }}</span>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center align-center gap-1">
            <VBtn
              icon
              variant="text"
              size="x-small"
              color="info"
              title="Editar categoría"
              @click="openEditDialog(item)"
            >
              <VIcon icon="tabler-pencil" size="18" />
            </VBtn>
            <VBtn
              icon
              variant="text"
              size="x-small"
              color="error"
              title="Eliminar categoría"
              @click="confirmDelete(item)"
            >
              <VIcon icon="tabler-trash" size="18" />
            </VBtn>
          </div>
        </template>

        <!-- Estado Vacío -->
        <template #no-data>
          <div class="text-center py-6">
            <VIcon icon="tabler-folder-off" size="40" color="secondary" class="mb-2" />
            <p class="text-subtitle-2 text-medium-emphasis mb-0">
              No se encontraron categorías de gastos
            </p>
          </div>
        </template>
      </VDataTable>
    </VCardItem>

    <!-- Diálogo Modal: Crear / Editar Categoría -->
    <VDialog v-model="isDialogOpen" max-width="480px" persistent>
      <VCard class="rounded-lg">
        <VCardItem class="pb-2">
          <div class="d-flex align-center justify-space-between">
            <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2">
              <VIcon
                :icon="isEditing ? 'tabler-pencil' : 'tabler-plus'"
                :color="isEditing ? 'info' : 'primary'"
                size="22"
              />
              {{ isEditing ? 'Editar Categoría' : 'Nueva Categoría de Gasto' }}
            </VCardTitle>
            <VBtn
              icon
              variant="text"
              size="small"
              :disabled="isSubmitting"
              @click="isDialogOpen = false"
            >
              <VIcon icon="tabler-x" size="20" />
            </VBtn>
          </div>
        </VCardItem>

        <VDivider />

        <VForm ref="categoryFormRef" @submit.prevent="submitForm">
          <VCardText class="pt-4 pb-2">
            <VTextField
              v-model="form.name"
              label="Nombre de la categoría *"
              placeholder="Ej. Servicios Públicos, Mantenimiento..."
              variant="outlined"
              density="comfortable"
              :error="!!form.error"
              :error-messages="form.error"
              autofocus
              :disabled="isSubmitting"
              @keydown.enter.prevent="submitForm"
            />
          </VCardText>

          <VCardActions class="px-6 pb-4 pt-0 d-flex justify-end gap-2">
            <VBtn
              variant="outlined"
              color="secondary"
              :disabled="isSubmitting"
              @click="isDialogOpen = false"
            >
              Cancelar
            </VBtn>
            <VBtn
              type="submit"
              color="primary"
              variant="flat"
              :loading="isSubmitting"
              :disabled="isSubmitting || !form.name.trim()"
            >
              {{ isEditing ? 'Guardar Cambios' : 'Crear Categoría' }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>
  </VCard>
</template>
