<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'

// Estado del listado
const categories = ref([])
const isLoading = ref(true)
const searchQuery = ref('')

// Estado del diálogo modal
const isDialogOpen = ref(false)
const isSubmitting = ref(false)
const isEditing = ref(false)
const formRef = ref(null)

const form = reactive({
  id: null,
  name: '',
  error: '',
})

// Reglas de validación para Vuetify Form
const nameRules = [
  v => Boolean(v && v.trim()) || 'El nombre de la categoría es obligatorio.',
  v => (v && v.trim().length >= 3) || 'Debe contener al menos 3 caracteres.',
  v => (v && v.trim().length <= 100) || 'No debe exceder los 100 caracteres.',
]

// Definición de cabeceras de la tabla
const headers = [
  { title: 'ID', key: 'id', width: '80px', align: 'start' },
  { title: 'NOMBRE DE CATEGORÍA', key: 'name', align: 'start' },
  { title: 'GASTOS ASOCIADOS', key: 'total_usage_count', align: 'center', width: '180px' },
  { title: 'FECHA DE CREACIÓN', key: 'created_at', align: 'start', width: '180px' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center', width: '120px' },
]

// Filtrado reactivo en el cliente
const filteredCategories = computed(() => {
  if (!searchQuery.value?.trim()) return categories.value

  const query = searchQuery.value.toLowerCase().trim()
  return categories.value.filter(cat =>
    cat.name.toLowerCase().includes(query) || String(cat.id).includes(query)
  )
})

// Obtener categorías desde la API
const fetchCategories = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/finances/expenses/category')
    categories.value = response.data?.data || []
  } catch (error) {
    console.error('Error al cargar categorías de gastos:', error)
    toast.error('No se pudieron obtener las categorías de gastos')
  } finally {
    isLoading.value = false
  }
}

// Apertura de modal para creación
const openCreateDialog = () => {
  isEditing.value = false
  form.id = null
  form.name = ''
  form.error = ''
  isDialogOpen.value = true
}

// Apertura de modal para edición
const openEditDialog = (item) => {
  isEditing.value = true
  form.id = item.id
  form.name = item.name
  form.error = ''
  isDialogOpen.value = true
}

// Guardar registro (Creación o Actualización)
const submitForm = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSubmitting.value = true
  form.error = ''

  try {
    if (isEditing.value) {
      const response = await axios.put(`/finances/expenses/category/${form.id}`, {
        name: form.name.trim(),
      })
      toast.success(response.data?.message || 'Categoría actualizada exitosamente')
    } else {
      const response = await axios.post('/finances/expenses/category', {
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
      form.error = 'Ocurrió un error al procesar la categoría.'
    }
  } finally {
    isSubmitting.value = false
  }
}

// Confirmar y procesar eliminación
const confirmDelete = async (item) => {
  if (item.total_usage_count > 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Operación restringida',
      text: `La categoría "${item.name}" posee ${item.total_usage_count} registro(s) contable(s) asociado(s). Por consistencia de auditoría no puede ser eliminada.`,
      confirmButtonText: 'Aceptar',
      confirmButtonColor: '#E20074',
    })
    return
  }

  const result = await Swal.fire({
    title: '¿Confirmar eliminación?',
    text: `Se eliminará permanentemente la categoría "${item.name}".`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#FF4C51',
    cancelButtonColor: '#7A0099',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`/finances/expenses/category/${item.id}`)
      toast.success('Categoría eliminada exitosamente')
      await fetchCategories()
    } catch (error) {
      console.error('Error eliminando categoría:', error)
      const errorMsg = error.response?.data?.message || 'No se pudo eliminar el registro'
      toast.error(errorMsg)
    }
  }
}

// Formateo de fecha
const formatDate = (dateString) => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return Number.isNaN(date.getTime())
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
      <!-- Encabezado del Catálogo -->
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-4">
        <div>
          <VCardTitle class="text-h6 font-weight-bold d-flex align-center gap-2 mb-1">
            <VIcon icon="tabler-category-2" color="primary" size="24" />
            Catálogo de Categorías de Gastos
          </VCardTitle>
          <VCardSubtitle class="text-caption text-medium-emphasis px-0">
            Administración y clasificación de conceptos contables para egresos operativos.
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

      <!-- Barra de Filtrado y Acciones -->
      <div class="mb-4 d-flex justify-space-between align-center flex-wrap gap-4">
        <VTextField
          v-model="searchQuery"
          placeholder="Buscar por nombre o ID..."
          density="comfortable"
          variant="outlined"
          prepend-inner-icon="tabler-search"
          hide-details="auto"
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

      <!-- Tabla de Datos -->
      <VDataTable
        :headers="headers"
        :items="filteredCategories"
        :loading="isLoading"
        items-per-page="10"
        hover
        class="border rounded-lg"
      >
        <!-- Skeleton de Carga -->
        <template #loading>
          <VSkeletonLoader type="table-row@5" />
        </template>

        <!-- Columna: ID -->
        <template #item.id="{ item }">
          <span class="font-weight-semibold text-caption text-medium-emphasis">#{{ item.id }}</span>
        </template>

        <!-- Columna: Nombre -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar size="32" color="primary" variant="tonal" class="rounded">
              <VIcon icon="tabler-tag" size="16" />
            </VAvatar>
            <span class="font-weight-medium text-body-2">{{ item.name }}</span>
          </div>
        </template>

        <!-- Columna: Uso Contable -->
        <template #item.total_usage_count="{ item }">
          <VChip
            :color="item.total_usage_count > 0 ? 'info' : 'secondary'"
            size="small"
            variant="tonal"
            class="font-weight-medium"
          >
            {{ item.total_usage_count }} registro(s)
          </VChip>
        </template>

        <!-- Columna: Fecha -->
        <template #item.created_at="{ item }">
          <span class="text-caption text-medium-emphasis">{{ formatDate(item.created_at) }}</span>
        </template>

        <!-- Columna: Acciones con Tooltips -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center align-center gap-1">
            <VTooltip text="Editar categoría" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon
                  variant="text"
                  size="x-small"
                  color="info"
                  @click="openEditDialog(item)"
                >
                  <VIcon icon="tabler-pencil" size="18" />
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip text="Eliminar categoría" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon
                  variant="text"
                  size="x-small"
                  color="error"
                  @click="confirmDelete(item)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <!-- Estado Vacío -->
        <template #no-data>
          <div class="text-center py-6">
            <VIcon icon="tabler-folder-off" size="40" color="secondary" class="mb-2" />
            <p class="text-subtitle-2 text-medium-emphasis mb-0">
              No se encontraron categorías de gastos registradas
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

        <VForm ref="formRef" @submit.prevent="submitForm">
          <VCardText class="pt-4 pb-2">
            <VTextField
              v-model="form.name"
              label="Nombre de la categoría *"
              placeholder="Ej. Servicios Públicos, Mantenimiento..."
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              :rules="nameRules"
              :error="Boolean(form.error)"
              :error-messages="form.error"
              autofocus
              :disabled="isSubmitting"
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
              :disabled="isSubmitting"
            >
              {{ isEditing ? 'Guardar Cambios' : 'Crear Categoría' }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>
  </VCard>
</template>

