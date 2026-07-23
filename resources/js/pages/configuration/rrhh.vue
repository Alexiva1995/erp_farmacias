<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

// Instancia de la tienda de branding
const brandingStore = useBrandingStore()

// Estados de la interfaz
const isLoading = ref(true)
const hasError = ref(false)

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

// Obtiene la configuración guardada optimizando la carga de datos
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  try {
    // Rendimiento: Solo solicitamos el campo requerido usando el nuevo filtro del recurso
    const response = await axios.get('/general-settings?only=enabled_rrhh_views')
    const settings = response.data.data
    if (settings.enabled_rrhh_views) {
      enabledRrhhViews.value = settings.enabled_rrhh_views
    }
  } catch (error) {
    hasError.value = true
    console.error("Error cargando configuración de RRHH:", error)
    toast.error("Error al cargar la configuración de Recursos Humanos")
  } finally {
    isLoading.value = false
  }
}

// Guarda los cambios en el servidor con control de concurrencia y rollback optimista
const toggleRrhhView = async (key) => {
  if (savingKeys.value[key]) return

  const previousState = [...enabledRrhhViews.value]
  const index = enabledRrhhViews.value.indexOf(key)

  // Modificación reactiva optimista de la UI
  if (index > -1) {
    enabledRrhhViews.value.splice(index, 1)
  } else {
    enabledRrhhViews.value.push(key)
  }

  savingKeys.value[key] = true

  try {
    await axios.post('/general-settings', {
      enabled_rrhh_views: enabledRrhhViews.value
    })
    
    // Recargar estado de branding global
    await brandingStore.fetchSettings()
    toast.success("Configuración de vistas de RRHH actualizada exitosamente")
  } catch (error) {
    // Rollback en caso de fallo
    enabledRrhhViews.value = previousState
    console.error("Error al guardar la configuración de RRHH:", error)
    toast.error("Error al actualizar la configuración en el servidor")
  } finally {
    savingKeys.value[key] = false
  }
}

// Retorna las clases dinámicas de las tarjetas para separar lógica del template
const getCardClasses = (key) => {
  const isEnabled = enabledRrhhViews.value.includes(key)
  const isSaving = savingKeys.value[key]
  
  return [
    'rounded-lg border cursor-pointer transition-all duration-300 ease-in-out h-100 position-relative',
    isEnabled 
      ? 'border-primary border-2 bg-var-theme-background shadow-sm hover:shadow-md' 
      : 'border-light bg-card opacity-90 hover:opacity-100 hover:shadow-sm',
    isSaving ? 'pointer-events-none opacity-75' : 'hover:-translate-y-1'
  ]
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <!-- Tarjeta Principal de Configuración de Vistas de RRHH -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden">
      <VCardItem class="py-6 px-6">
        <!-- Título principal con diseño mejorado -->
        <div class="d-flex align-center gap-3 mb-2">
          <div class="p-2 rounded bg-primary-lighten-5">
            <VIcon icon="tabler-users-group" class="text-primary" size="28" />
          </div>
          <div>
            <VCardTitle class="text-h5 font-weight-black text-uppercase tracking-wide mb-0">
              Configuración de Vistas de RRHH
            </VCardTitle>
            <span class="text-caption text-medium-emphasis">
              Panel de control para los módulos de Recursos Humanos y Productividad del ERP.
            </span>
          </div>
        </div>

        <p class="text-body-2 text-medium-emphasis mt-3 mb-6">
          Habilita o deshabilita los módulos de Recursos Humanos en la barra de navegación lateral. Las vistas desmarcadas se ocultarán inmediatamente para todos los usuarios, optimizando el espacio de trabajo.
        </p>

        <!-- Skeleton loader mientras cargan los datos -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 8" :key="n" cols="12" sm="6" md="3">
            <VSkeletonLoader type="card" height="120" class="rounded-lg border shadow-xs" />
          </VCol>
        </VRow>

        <!-- Estado de error con botón de reintento -->
        <div v-else-if="hasError" class="d-flex flex-column align-center justify-center py-10 gap-3 border rounded-lg bg-var-theme-background">
          <VIcon icon="tabler-alert-circle" class="text-error" size="48" />
          <h3 class="text-h6 font-weight-bold">Error al cargar configuración</h3>
          <p class="text-caption text-medium-emphasis max-w-md text-center">
            No se pudo establecer conexión con el servidor para leer el estado de las vistas de recursos humanos.
          </p>
          <VBtn color="primary" variant="tonal" size="small" prepend-icon="tabler-refresh" @click="fetchSettings">
            Reintentar Carga
          </VBtn>
        </div>

        <!-- Tarjetas interactivas de las vistas -->
        <VRow v-else>
          <VCol v-for="view in availableRrhhViews" :key="view.key" cols="12" sm="6" md="3">
            <VCard
              variant="flat"
              :class="getCardClasses(view.key)"
              @click="toggleRrhhView(view.key)"
            >
              <!-- Indicador de guardado superpuesto -->
              <div 
                v-if="savingKeys[view.key]" 
                class="position-absolute top-0 right-0 p-2 d-flex align-center justify-center"
                style="z-index: 2;"
              >
                <VProgressCircular indeterminate size="16" width="2" color="primary" />
              </div>

              <VCardItem class="py-5 px-5">
                <div class="d-flex flex-column h-100 justify-space-between">
                  <div>
                    <div class="d-flex align-center justify-space-between w-100 mb-3">
                      <div class="d-flex align-center">
                        <VAvatar 
                          :color="enabledRrhhViews.includes(view.key) ? 'primary' : 'secondary'" 
                          variant="tonal" 
                          size="36" 
                          class="me-3 transition-all duration-300"
                        >
                          <VIcon :icon="view.icon" size="20" />
                        </VAvatar>
                        <span 
                          class="font-weight-black text-body-2 transition-colors duration-300" 
                          :class="enabledRrhhViews.includes(view.key) ? 'text-high-emphasis' : 'text-disabled'"
                        >
                          {{ view.title }}
                        </span>
                      </div>
                      <VSwitch
                        :model-value="enabledRrhhViews.includes(view.key)"
                        :loading="savingKeys[view.key]"
                        :disabled="savingKeys[view.key] || isLoading"
                        density="compact"
                        hide-details
                        color="primary"
                        class="ms-2"
                        @click.stop
                        @update:model-value="toggleRrhhView(view.key)"
                      />
                    </div>
                    <p class="text-caption text-medium-emphasis mb-0 leading-normal">
                      {{ view.description }}
                    </p>
                  </div>
                </div>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>

<style scoped>
.hover\:-translate-y-1:hover {
  transform: translateY(-4px);
}
.transition-all {
  transition-property: all;
}
.duration-300 {
  transition-duration: 300ms;
}
</style>
