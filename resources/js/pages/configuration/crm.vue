<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import CrmModuleCard from '@/components/configuration/CrmModuleCard.vue'

const brandingStore = useBrandingStore()

// Vistas activas por defecto
const enabledCrmViews = ref([
  'clients',
  'companies',
  'doctors',
  'lottery'
])

// Estados de carga y guardado
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

// Catálogo de vistas disponibles del CRM
const availableCrmViews = [
  { key: 'clients', title: 'Clientes', description: 'Gestión de ficha de clientes, hábitos de compra y trazabilidad.', icon: 'tabler-users' },
  { key: 'companies', title: 'Convenios / Empresas', description: 'Gestión de acuerdos corporativos y descuentos institucionales.', icon: 'tabler-building' },
  { key: 'doctors', title: 'Médicos', description: 'Registro de médicos tratantes, especialidades y comisiones.', icon: 'tabler-stethoscope' },
  { key: 'lottery', title: 'Sorteo / Lotería', description: 'Campañas de fidelización, emisión de boletos y rifas.', icon: 'tabler-ticket' },
]

// Propiedades computadas para la interfaz y métricas
const totalCount = computed(() => availableCrmViews.length)

const activeCount = computed(() => enabledCrmViews.value.length)

const activePercentage = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((activeCount.value / totalCount.value) * 100)
})

const allEnabled = computed(() => activeCount.value === totalCount.value)

const noneEnabled = computed(() => activeCount.value === 0)

// Cargar la configuración optimizada solicitando únicamente el campo de CRM
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''
  
  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_crm_views' }
    })
    
    const settings = response.data.data
    if (settings && Array.isArray(settings.enabled_crm_views)) {
      enabledCrmViews.value = settings.enabled_crm_views
    }
  } catch (error) {
    console.error("Error cargando configuración del CRM:", error)
    hasError.value = true
    errorMessage.value = "No se pudo cargar la configuración del CRM. Verifique su conexión e intente de nuevo."
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

// Alternar el estado de una vista específica
const toggleCrmView = async (key) => {
  if (isSaving.value || isLoading.value) return

  const updatedViews = [...enabledCrmViews.value]
  const index = updatedViews.indexOf(key)
  
  if (index > -1) {
    updatedViews.splice(index, 1)
  } else {
    updatedViews.push(key)
  }
  
  enabledCrmViews.value = updatedViews
  await updateSettings()
}

// Acciones masivas: Habilitar o deshabilitar todas las vistas
const setAllViews = async (enable) => {
  if (isSaving.value || isLoading.value) return
  
  enabledCrmViews.value = enable ? availableCrmViews.map(v => v.key) : []
  await updateSettings()
}

// Persistir la configuración en el servidor
const updateSettings = async () => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_crm_views: enabledCrmViews.value
    })
    
    await brandingStore.fetchSettings()
    toast.success("Configuración de vistas del CRM actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar la configuración:", error)
    toast.error("Error al actualizar la configuración")
    // Revertir estado previo mediante recarga ligera
    await fetchSettings()
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <!-- Tarjeta Principal de Configuración del CRM -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <!-- Encabezado con Jerarquía y Estado de Guardado -->
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4 mb-4">
          <div>
            <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
              <VIcon icon="tabler-address-book" color="primary" size="28" />
              Configuración de Vistas del CRM
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
              Habilita o deshabilita los módulos y vistas del CRM en la barra de navegación lateral. Las vistas desmarcadas se ocultarán inmediatamente para todos los usuarios.
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

        <!-- Skeletons durante Carga Inicial -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 4" :key="n" cols="12" sm="6" md="3">
            <VSkeletonLoader
              type="article, actions"
              class="rounded-lg border"
              height="140"
            />
          </VCol>
        </VRow>

        <!-- Tarjetas de Módulos CRM -->
        <VRow v-else-if="!hasError">
          <VCol
            v-for="view in availableCrmViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="3"
          >
            <CrmModuleCard
              :view="view"
              :is-active="enabledCrmViews.includes(view.key)"
              :is-saving="isSaving"
              @toggle="toggleCrmView"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
