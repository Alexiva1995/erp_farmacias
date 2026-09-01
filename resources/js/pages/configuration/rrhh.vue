<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import RrhhModuleCard from '@/components/configuration/RrhhModuleCard.vue'

// Instancia de la tienda de branding
const brandingStore = useBrandingStore()

// Estados de la interfaz
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

// Mapa reactivo para controlar qué módulos están guardando cambios actualmente
const savingKeys = ref({})

// Listado de vistas habilitadas en el servidor
const enabledRrhhViews = ref([])

// Lista estática de vistas configurables de RRHH y Productividad
const availableRrhhViews = [
  { key: 'employees', title: 'Empleados', description: 'Gestión y ficha del personal de la empresa.', icon: 'tabler-users' },
  { key: 'social_benefits', title: 'Prestaciones Sociales', description: 'Cálculo y seguimiento de prestaciones sociales de empleados.', icon: 'tabler-cash' },
  { key: 'resignations', title: 'Renuncias', description: 'Registro y gestión del proceso de renuncias del personal.', icon: 'tabler-file-dislike' },
  { key: 'cleaning', title: 'Limpieza', description: 'Asignación y seguimiento de actividades de limpieza.', icon: 'tabler-brush' },
  { key: 'laboratory', title: 'Laboratorios Empleados', description: 'Seguimiento de marcas y laboratorios asignados a empleados.', icon: 'tabler-building-factory-2' },
  { key: 'product', title: 'Productos Empleados', description: 'Asignación de productos a supervisar o impulsar por empleados.', icon: 'tabler-box' },
  { key: 'employee_task', title: 'Tareas Empleados', description: 'Gestión de tareas y actividades diarias del equipo.', icon: 'tabler-checkbox' },
  { key: 'employee_month', title: 'Empleado del Mes', description: 'Evaluación de productividad y reconocimiento del empleado del mes.', icon: 'tabler-trophy' },
]

// Propiedades computadas para la interfaz y métricas
const totalCount = computed(() => availableRrhhViews.length)

const activeCount = computed(() => enabledRrhhViews.value.length)

const activePercentage = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((activeCount.value / totalCount.value) * 100)
})

const allEnabled = computed(() => activeCount.value === totalCount.value)

const noneEnabled = computed(() => activeCount.value === 0)

// Obtiene la configuración guardada optimizando la carga de datos (solo la llave necesaria)
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''
  
  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_rrhh_views' }
    })
    
    const settings = response.data.data
    if (settings && Array.isArray(settings.enabled_rrhh_views)) {
      enabledRrhhViews.value = settings.enabled_rrhh_views
    }
  } catch (error) {
    hasError.value = true
    errorMessage.value = "No se pudo cargar la configuración de Recursos Humanos. Verifique su conexión e intente de nuevo."
    console.error("Error cargando configuración de RRHH:", error)
    toast.error("Error al cargar la configuración de Recursos Humanos")
  } finally {
    isLoading.value = false
  }
}

// Guarda los cambios en el servidor con control de concurrencia y rollback optimista
const toggleRrhhView = async (key) => {
  if (savingKeys.value[key] || isSaving.value) return

  const previousState = [...enabledRrhhViews.value]
  const index = enabledRrhhViews.value.indexOf(key)

  if (index > -1) {
    enabledRrhhViews.value.splice(index, 1)
  } else {
    enabledRrhhViews.value.push(key)
  }

  savingKeys.value[key] = true
  await updateSettings(previousState, key)
}

// Acciones masivas: Habilitar o deshabilitar todas las vistas
const setAllViews = async (enable) => {
  if (isSaving.value || isLoading.value) return
  
  const previousState = [...enabledRrhhViews.value]
  enabledRrhhViews.value = enable ? availableRrhhViews.map(v => v.key) : []
  await updateSettings(previousState)
}

// Persistir la configuración en el servidor
const updateSettings = async (previousState = null, specificKey = null) => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_rrhh_views: enabledRrhhViews.value
    })
    
    await brandingStore.fetchSettings()
    toast.success("Configuración de vistas de RRHH actualizada exitosamente")
  } catch (error) {
    if (previousState) {
      enabledRrhhViews.value = previousState
    }
    console.error("Error al guardar la configuración de RRHH:", error)
    toast.error("Error al actualizar la configuración en el servidor")
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
    <!-- Tarjeta Principal de Configuración de Vistas de RRHH -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <!-- Encabezado con Jerarquía y Estado de Guardado -->
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4 mb-4">
          <div>
            <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
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
            <p class="text-caption text-medium-emphasis mb-0 mt-1">
              Habilita o deshabilita los módulos de Recursos Humanos y Productividad en la barra de navegación lateral.
            </p>
          </div>

          <!-- Métricas y Botones de Acción Masiva -->
          <div class="d-flex align-center gap-2 flex-wrap" v-if="!isLoading && !hasError">
            <VChip color="primary" variant="tonal" size="small" class="font-weight-bold">
              {{ activeCount }} / {{ totalCount }} Módulos Activos ({{ activePercentage }}%)
            </VChip>
            <VBtn
              size="small"
              variant="outlined"
              color="primary"
              :disabled="allEnabled || isSaving"
              @click="setAllViews(true)"
            >
              Activar Todas
            </VBtn>
            <VBtn
              size="small"
              variant="outlined"
              color="error"
              :disabled="noneEnabled || isSaving"
              @click="setAllViews(false)"
            >
              Desactivar Todas
            </VBtn>
          </div>
        </div>

        <VDivider class="mb-6" />

        <!-- Banner de Error con Reintento -->
        <VAlert
          v-if="hasError"
          type="error"
          variant="tonal"
          class="mb-6 rounded-lg"
          closable
        >
          <template #title>
            Error de Carga
          </template>
          {{ errorMessage }}
          <template #append>
            <VBtn
              color="error"
              variant="text"
              size="small"
              @click="fetchSettings"
            >
              Reintentar
            </VBtn>
          </template>
        </VAlert>

        <!-- Skeleton loaders durante Carga Inicial -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 8" :key="n" cols="12" sm="6" md="3">
            <VSkeletonLoader
              type="article, actions"
              class="rounded-lg border"
              height="140"
            />
          </VCol>
        </VRow>

        <!-- Tarjetas de Módulos RRHH -->
        <VRow v-else-if="!hasError">
          <VCol
            v-for="view in availableRrhhViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="3"
          >
            <RrhhModuleCard
              :view="view"
              :is-active="enabledRrhhViews.includes(view.key)"
              :is-saving="!!savingKeys[view.key] || isSaving"
              @toggle="toggleRrhhView"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
