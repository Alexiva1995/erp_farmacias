<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import IaAssistantModuleCard from '@/components/configuration/IaAssistantModuleCard.vue'

const brandingStore = useBrandingStore()

// Estados reactivos de la UI
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

const enabledIaAssistantViews = ref([])

// Catálogo estático de vistas de IA Assistence
const availableIaAssistantViews = [
  { key: 'pedidos',       title: 'Pedidos',               description: 'Asistente inteligente para preparar y consolidar sugerencias de pedidos.',   icon: 'tabler-shopping-cart'      },
  { key: 'reporte',       title: 'Reporte',               description: 'Análisis detallado y reporte general generado por la IA sobre faltantes.',    icon: 'tabler-file-report'        },
  { key: 'oportunidad',   title: 'Oportunidad de Mercado',description: 'Detección inteligente de ofertas y diferencias de precios entre proveedores.',  icon: 'tabler-bulb'               },
  { key: 'comparador',    title: 'Comparador',            description: 'Comparador visual de costos, ofertas y condiciones comerciales.',              icon: 'tabler-scale'              },
  { key: 'automatizacion',title: 'Automatización',        description: 'Reglas de reposición automática periódicas con cron y parámetros mínimos.',      icon: 'tabler-settings-automation'},
]

// Propiedades computadas para métricas y estado global
const totalCount = computed(() => availableIaAssistantViews.length)

const activeCount = computed(() => enabledIaAssistantViews.value.length)

const activePercentage = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((activeCount.value / totalCount.value) * 100)
})

const allEnabled = computed(() => activeCount.value === totalCount.value)

const noneEnabled = computed(() => activeCount.value === 0)

// Carga inicial optimizada mediante filtro 'only'
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_ia_assistant_views' }
    })
    const views = response.data.data?.enabled_ia_assistant_views
    if (Array.isArray(views)) {
      enabledIaAssistantViews.value = views
    }
  } catch (error) {
    console.error("Error cargando configuración de IA Assistence:", error)
    hasError.value = true
    errorMessage.value = "No se pudo cargar la configuración de IA Assistence. Verifique su conexión e intente de nuevo."
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

// Alternar vista individual
const toggleIaView = async (key) => {
  if (isSaving.value || isLoading.value) return

  const previousState = [...enabledIaAssistantViews.value]
  const updatedViews = [...enabledIaAssistantViews.value]
  const index = updatedViews.indexOf(key)

  if (index > -1) {
    updatedViews.splice(index, 1)
  } else {
    updatedViews.push(key)
  }

  enabledIaAssistantViews.value = updatedViews
  await updateSettings(previousState)
}

// Acciones masivas: Activar o Desactivar todas las vistas
const setAllViews = async (enable) => {
  if (isSaving.value || isLoading.value) return

  const previousState = [...enabledIaAssistantViews.value]
  enabledIaAssistantViews.value = enable ? availableIaAssistantViews.map(v => v.key) : []
  await updateSettings(previousState)
}

// Persistir la configuración en el servidor
const updateSettings = async (previousState = null) => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_ia_assistant_views: enabledIaAssistantViews.value,
    })
    await brandingStore.fetchSettings()
    toast.success("Configuración de IA Assistence actualizada exitosamente")
  } catch (error) {
    if (previousState) {
      enabledIaAssistantViews.value = previousState
    }
    console.error("Error al guardar configuración de IA:", error)
    toast.error("Error al actualizar la configuración")
  } finally {
    isSaving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div class="position-relative">
    <!-- Barra de procesamiento superior -->
    <VProgressLinear
      v-if="isSaving"
      color="primary"
      indeterminate
      height="4"
      class="position-absolute top-0 left-0 right-0"
      style="z-index: 99;"
    />

    <!-- Tarjeta Principal de Configuración de IA Assistence -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <!-- Encabezado Estandarizado con Métricas -->
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4 mb-4">
          <div>
            <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
              <VIcon icon="tabler-brain" color="primary" size="28" />
              Módulos IA Assistence
            </VCardTitle>
            <p class="text-caption text-medium-emphasis mb-0 mt-1">
              Habilita o deshabilita los módulos de Inteligencia Artificial en la barra de navegación lateral.
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
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="130" />
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
              @toggle="toggleIaView"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
