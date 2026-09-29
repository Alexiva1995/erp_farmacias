<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'
import IaAssistantModuleCard from '@/components/configuration/IaAssistantModuleCard.vue'

const ability = useAbility()
const brandingStore = useBrandingStore()

// Validación de permisos mediante CASL
const canEdit = computed(() => {
  return (
    ability.can('manage', 'admin') ||
    ability.can('manage', 'all') ||
    ability.can('edit', 'Configuration')
  )
})

// Estados reactivos de la vista
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

const enabledIaAssistantViews = ref([])

// Catálogo maestro de submódulos de IA Assistant
const availableIaAssistantViews = [
  {
    key: 'pedidos',
    title: 'Sugerencia de Pedidos',
    description: 'Asistente inteligente para preparar y consolidar sugerencias de compras a droguerías y laboratorios.',
    icon: 'tabler-shopping-cart',
  },
  {
    key: 'reporte',
    title: 'Reporte Predictivo',
    description: 'Análisis detallado sobre rotación de inventario, predicción de demanda y productos faltantes.',
    icon: 'tabler-file-report',
  },
  {
    key: 'oportunidad',
    title: 'Oportunidades de Compra',
    description: 'Detección de ofertas comerciales y diferencias de costos competitivos entre proveedores.',
    icon: 'tabler-bulb',
  },
  {
    key: 'comparador',
    title: 'Comparador de Costos',
    description: 'Comparativa visual e histórica de costos, bonificaciones y condiciones comerciales de compra.',
    icon: 'tabler-scale',
  },
  {
    key: 'automatizacion',
    title: 'Reposición Automática',
    description: 'Ejecución y programación periódica de reposición de stock basada en límites mínimos e IA.',
    icon: 'tabler-settings-automation',
  },
]

// Propiedades computadas de soporte y métricas
const totalCount = computed(() => availableIaAssistantViews.length)
const activeCount = computed(() => enabledIaAssistantViews.value.length)
const activePercentage = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((activeCount.value / totalCount.value) * 100)
})

const allEnabled = computed(() => activeCount.value === totalCount.value)
const noneEnabled = computed(() => activeCount.value === 0)

// Consulta inicial de configuración
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_ia_assistant_views' },
    })

    const views = response.data.data?.enabled_ia_assistant_views
    if (Array.isArray(views)) {
      enabledIaAssistantViews.value = views
    }
  } catch (error) {
    console.error('Error cargando configuración de Asistente IA:', error)
    hasError.value = true
    errorMessage.value = 'No se pudo cargar la configuración del Asistente IA. Verifique su conexión e intente nuevamente.'
    toast.error('Error al cargar la configuración')
  } finally {
    isLoading.value = false
  }
}

// Alternar submódulo individual
const toggleIaView = async (key) => {
  if (isSaving.value || isLoading.value || !canEdit.value) return

  const previousState = [...enabledIaAssistantViews.value]
  const updatedViews = [...enabledIaAssistantViews.value]
  const index = updatedViews.indexOf(key)

  if (index > -1) {
    updatedViews.splice(index, 1)
  } else {
    updatedViews.push(key)
  }

  enabledIaAssistantViews.value = updatedViews
  await persistSettings(previousState)
}

// Acciones masivas con confirmación SweetAlert2 para acciones destructivas
const confirmSetAllViews = async (enable) => {
  if (isSaving.value || isLoading.value || !canEdit.value) return

  if (!enable) {
    const result = await Swal.fire({
      title: '¿Desactivar todos los módulos?',
      text: 'Los módulos de Inteligencia Artificial dejarán de ser visibles en el menú lateral para todos los usuarios.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#E20074',
      cancelButtonColor: '#7A0099',
      confirmButtonText: 'Sí, desactivar todos',
      cancelButtonText: 'Cancelar',
    })

    if (!result.isConfirmed) return
  }

  const previousState = [...enabledIaAssistantViews.value]
  enabledIaAssistantViews.value = enable ? availableIaAssistantViews.map(v => v.key) : []
  await persistSettings(previousState)
}

// Persistencia en el backend
const persistSettings = async (previousState = null) => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_ia_assistant_views: enabledIaAssistantViews.value,
    })

    await brandingStore.fetchSettings()
    toast.success('Configuración de Asistente IA actualizada')
  } catch (error) {
    if (previousState) {
      enabledIaAssistantViews.value = previousState
    }
    console.error('Error al guardar configuración de Asistente IA:', error)
    toast.error('Error al actualizar la configuración')
  } finally {
    isSaving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div class="position-relative">
    <!-- Barra de progreso superior durante sincronización -->
    <VProgressLinear
      v-if="isSaving"
      color="primary"
      indeterminate
      height="4"
      class="position-absolute top-0 left-0 right-0"
      style="z-index: 99;"
    />

    <!-- Tarjeta Principal de Configuración -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <!-- Encabezado con Métricas y Botones de Acción -->
        <div class="d-flex flex-column flex-md-row justify-space-between align-start align-md-center gap-4 mb-4">
          <div>
            <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
              <VIcon icon="tabler-brain" color="primary" size="28" />
              Módulos del Asistente IA
            </VCardTitle>
            <p class="text-caption text-medium-emphasis mb-0 mt-1">
              Controla la visibilidad y disponibilidad de las herramientas de Inteligencia Artificial en el ERP.
            </p>
          </div>

          <!-- Métricas y Controles Masivos -->
          <div v-if="!isLoading && !hasError" class="d-flex align-center gap-2 flex-wrap">
            <VChip color="primary" variant="tonal" size="small" class="font-weight-bold">
              {{ activeCount }} / {{ totalCount }} Activos ({{ activePercentage }}%)
            </VChip>

            <VTooltip v-if="canEdit" location="top" text="Habilitar todos los módulos de IA en el menú">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  size="small"
                  variant="outlined"
                  color="primary"
                  :disabled="allEnabled || isSaving"
                  @click="confirmSetAllViews(true)"
                >
                  Activar Todos
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip v-if="canEdit" location="top" text="Ocultar todos los módulos de IA en el menú">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  size="small"
                  variant="outlined"
                  color="error"
                  :disabled="noneEnabled || isSaving"
                  @click="confirmSetAllViews(false)"
                >
                  Desactivar Todos
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </div>

        <VDivider class="mb-6" />

        <!-- Banner de Error con Opción de Reintento -->
        <VAlert
          v-if="hasError"
          type="error"
          variant="tonal"
          class="mb-6 rounded-lg"
          closable
        >
          <template #title> Error de Carga </template>
          {{ errorMessage }}
          <template #append>
            <VBtn color="error" variant="text" size="small" @click="fetchSettings">
              Reintentar
            </VBtn>
          </template>
        </VAlert>

        <!-- Skeletons durante Carga Inicial -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 5" :key="n" cols="12" sm="6" md="4">
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="140" />
          </VCol>
        </VRow>

        <!-- Rejilla de Módulos IA -->
        <VRow v-else-if="!hasError">
          <VCol
            v-for="view in availableIaAssistantViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="4"
          >
            <IaAssistantModuleCard
              :view="view"
              :is-active="enabledIaAssistantViews.includes(view.key)"
              :is-saving="isSaving"
              :can-edit="canEdit"
              @toggle="toggleIaView"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
