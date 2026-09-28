<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import RrhhModuleCard from '@/components/configuration/RrhhModuleCard.vue'

// Instancias de control y estado
const ability = useAbility()
const brandingStore = useBrandingStore()

// Validación de permisos de edición
const canEdit = computed(() => {
  return ability.can('manage', 'admin') || 
         ability.can('manage', 'all') || 
         ability.can('edit', 'Configuration')
})

// Estados de la interfaz
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const selectedCategory = ref('all')

// Control de operaciones concurrentes
const savingKeys = ref({})
const enabledRrhhViews = ref([])

// Lista exhaustiva de módulos de RRHH y Productividad
const availableRrhhViews = [
  { 
    key: 'employees', 
    title: 'Empleados', 
    description: 'Gestión integral de expedientes y datos del personal.', 
    icon: 'tabler-users', 
    category: 'Core RRHH' 
  },
  { 
    key: 'social_benefits', 
    title: 'Prestaciones Sociales', 
    description: 'Cálculo y control de prestaciones y pasivos laborales.', 
    icon: 'tabler-cash', 
    category: 'Core RRHH' 
  },
  { 
    key: 'resignations', 
    title: 'Renuncias', 
    description: 'Procesamiento de egresos y finiquitos de colaboradores.', 
    icon: 'tabler-file-dislike', 
    category: 'Core RRHH' 
  },
  { 
    key: 'cleaning', 
    title: 'Limpieza', 
    description: 'Asignación de turnos y protocolos de limpieza de sucursal.', 
    icon: 'tabler-brush', 
    category: 'Productividad' 
  },
  { 
    key: 'laboratory', 
    title: 'Laboratorios Empleados', 
    description: 'Asignación y seguimiento de marcas y laboratorios aliados.', 
    icon: 'tabler-building-factory-2', 
    category: 'Productividad' 
  },
  { 
    key: 'product', 
    title: 'Productos Empleados', 
    description: 'Asignación de catálogo e incentivos por colaborador.', 
    icon: 'tabler-box', 
    category: 'Productividad' 
  },
  { 
    key: 'employee_task', 
    title: 'Tareas Empleados', 
    description: 'Control de asignaciones y actividades diarias operativas.', 
    icon: 'tabler-checkbox', 
    category: 'Productividad' 
  },
  { 
    key: 'supervisor_cleaning_activities', 
    title: 'Revisión de Tareas', 
    description: 'Módulo de auditoría y validación de actividades realizadas.', 
    icon: 'tabler-clipboard-check', 
    category: 'Productividad' 
  },
  { 
    key: 'employee_month', 
    title: 'Empleado del Mes', 
    description: 'Métricas de productividad y cuadro de honor mensual.', 
    icon: 'tabler-trophy', 
    category: 'Productividad' 
  },
]

// Filtro computado por categorías funcionales
const filteredViews = computed(() => {
  if (selectedCategory.value === 'all') return availableRrhhViews
  return availableRrhhViews.filter(view => view.category === selectedCategory.value)
})

// Métricas de visualización
const totalCount = computed(() => availableRrhhViews.length)
const activeCount = computed(() => enabledRrhhViews.value.length)
const activePercentage = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((activeCount.value / totalCount.value) * 100)
})

const allEnabled = computed(() => activeCount.value === totalCount.value)
const noneEnabled = computed(() => activeCount.value === 0)

// Consulta inicial de la configuración
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''
  
  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_rrhh_views' }
    })
    
    const settings = response.data?.data
    if (settings && Array.isArray(settings.enabled_rrhh_views)) {
      enabledRrhhViews.value = settings.enabled_rrhh_views
    }
  } catch (error) {
    hasError.value = true
    errorMessage.value = "No se pudo recuperar la configuración de RRHH desde el servidor."
    toast.error("Error al cargar la configuración de RRHH")
  } finally {
    isLoading.value = false
  }
}

// Alternar estado individual de una vista con rollback optimista
const toggleRrhhView = async (key) => {
  if (savingKeys.value[key] || isSaving.value || !canEdit.value) return

  const previousState = [...enabledRrhhViews.value]
  const index = enabledRrhhViews.value.indexOf(key)

  if (index > -1) {
    enabledRrhhViews.value.splice(index, 1)
  } else {
    enabledRrhhViews.value.push(key)
  }

  savingKeys.value[key] = true
  await persistSettings(previousState, key)
}

// Activación o desactivación masiva con validación modal SweetAlert2
const handleBatchToggle = async (enable) => {
  if (!canEdit.value || isSaving.value) return

  if (!enable) {
    const confirmed = await confirmDialog({
      title: '¿Desactivar todos los módulos de RRHH?',
      text: 'Los usuarios no podrán acceder a ninguna de las vistas de RRHH en el menú de navegación.',
      confirmButtonText: 'Sí, desactivar todo',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#FF4C51',
    })
    if (!confirmed) return
  }

  const previousState = [...enabledRrhhViews.value]
  enabledRrhhViews.value = enable ? availableRrhhViews.map(v => v.key) : []
  await persistSettings(previousState)
}

// Persistencia en el backend y refresco del store de branding
const persistSettings = async (previousState = null, specificKey = null) => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_rrhh_views: enabledRrhhViews.value
    })
    
    await brandingStore.fetchSettings(true)
    toast.success("Módulos de RRHH actualizados exitosamente")
  } catch (error) {
    if (previousState) {
      enabledRrhhViews.value = previousState
    }
    toast.error("Ocurrió un error al persistir los cambios")
  } finally {
    if (specificKey) {
      savingKeys.value[specificKey] = false
    }
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <!-- Encabezado y Acciones Globales -->
        <div class="d-flex flex-column flex-md-row justify-space-between align-start align-md-center gap-4 mb-4">
          <div>
            <VCardTitle class="text-h5 font-weight-bold text-uppercase d-flex align-center gap-2">
              <VIcon icon="tabler-users-group" color="primary" size="28" />
              Configuración de Vistas de RRHH
              <VProgressCircular
                v-if="isSaving"
                indeterminate
                size="20"
                width="2"
                color="primary"
                class="ms-2"
              />
            </VCardTitle>
            <p class="text-body-2 text-medium-emphasis mb-0 mt-1">
              Controla la visibilidad de los módulos de Recursos Humanos y Productividad en el sistema.
            </p>
          </div>

          <!-- Métricas y Controles Masivos -->
          <div class="d-flex align-center gap-3 flex-wrap" v-if="!isLoading && !hasError">
            <VChip color="primary" variant="tonal" size="small" class="font-weight-bold">
              {{ activeCount }} / {{ totalCount }} Activos ({{ activePercentage }}%)
            </VChip>
            
            <VBtn
              size="small"
              variant="outlined"
              color="primary"
              :disabled="allEnabled || isSaving || !canEdit"
              @click="handleBatchToggle(true)"
            >
              Activar Todas
            </VBtn>

            <VBtn
              size="small"
              variant="outlined"
              color="error"
              :disabled="noneEnabled || isSaving || !canEdit"
              @click="handleBatchToggle(false)"
            >
              Desactivar Todas
            </VBtn>
          </div>
        </div>

        <!-- Barra de progreso global -->
        <VProgressLinear
          :model-value="activePercentage"
          color="primary"
          height="6"
          rounded
          class="mb-5"
        />

        <!-- Filtro por Categorías -->
        <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-6">
          <VTabs v-model="selectedCategory" density="comfortable" color="primary">
            <VTab value="all">Todos los Módulos</VTab>
            <VTab value="Core RRHH">Core RRHH</VTab>
            <VTab value="Productividad">Productividad y Operaciones</VTab>
          </VTabs>
        </div>

        <VDivider class="mb-6" />

        <!-- Alerta de Error con Acción de Reintento -->
        <VAlert
          v-if="hasError"
          type="error"
          variant="tonal"
          class="mb-6 rounded-lg"
          closable
        >
          <template #title>
            Error de Sincronización
          </template>
          {{ errorMessage }}
          <template #append>
            <VBtn color="error" variant="text" size="small" @click="fetchSettings">
              Reintentar
            </VBtn>
          </template>
        </VAlert>

        <!-- Skeleton Loaders durante carga -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 8" :key="n" cols="12" sm="6" md="4" lg="3">
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="150" />
          </VCol>
        </VRow>

        <!-- Grilla de Módulos Configurados -->
        <VRow v-else-if="!hasError">
          <VCol
            v-for="view in filteredViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <RrhhModuleCard
              :view="view"
              :is-active="enabledRrhhViews.includes(view.key)"
              :is-saving="!!savingKeys[view.key] || isSaving"
              :disabled="!canEdit"
              @toggle="toggleRrhhView"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
