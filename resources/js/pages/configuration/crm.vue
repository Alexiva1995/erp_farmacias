<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()

const enabledCrmViews = ref([
  'clients',
  'companies',
  'doctors',
  'lottery'
])

const isLoading = ref(true)
const isSaving = ref(false)

const availableCrmViews = [
  { key: 'clients', title: 'Clientes', description: 'Gestión de ficha de clientes, hábitos de compra y trazabilidad.', icon: 'tabler-users' },
  { key: 'companies', title: 'Convenios / Empresas', description: 'Gestión de acuerdos corporativos y descuentos institucionales.', icon: 'tabler-building' },
  { key: 'doctors', title: 'Médicos', description: 'Registro de médicos tratantes, especialidades y comisiones.', icon: 'tabler-stethoscope' },
  { key: 'lottery', title: 'Sorteo / Lotería', description: 'Campañas de fidelización, emisión de boletos y rifas.', icon: 'tabler-ticket' },
]

const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    if (settings.enabled_crm_views) {
      enabledCrmViews.value = settings.enabled_crm_views
    }
  } catch (error) {
    console.error("Error cargando configuración del CRM:", error)
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

const toggleCrmView = async (key) => {
  if (isSaving.value || isLoading.value) return
  
  const index = enabledCrmViews.value.indexOf(key)
  if (index > -1) {
    enabledCrmViews.value.splice(index, 1)
  } else {
    enabledCrmViews.value.push(key)
  }
  await updateSettings()
}

const updateSettings = async () => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_crm_views: enabledCrmViews.value
    })
    await brandingStore.fetchSettings()
    toast.success("Configuración de vistas del CRM actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.error("Error al actualizar la configuración")
    // Revertir en caso de error
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
    <!-- Tarjeta Principal de Configuración de Vistas del CRM -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-address-book" class="text-primary" size="26" />
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
        <p class="text-caption text-medium-emphasis mb-6">
          Habilita o deshabilita los módulos y vistas del CRM en la barra de navegación lateral. Las vistas desmarcadas se ocultarán inmediatamente para todos los usuarios.
        </p>

        <!-- Skeletons durante la carga inicial -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 4" :key="n" cols="12" sm="6" md="3">
            <VCard variant="outlined" class="rounded-lg h-100 border-dashed opacity-75">
              <VCardItem class="py-4 px-4">
                <div class="animate-pulse">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center">
                      <div class="rounded-circle me-2 bg-skeleton" style="width: 32px; height: 32px;"></div>
                      <div class="rounded bg-skeleton" style="width: 80px; height: 16px;"></div>
                    </div>
                    <div class="rounded bg-skeleton" style="width: 36px; height: 20px;"></div>
                  </div>
                  <div class="rounded bg-skeleton mb-2" style="width: 100%; height: 12px;"></div>
                  <div class="rounded bg-skeleton" style="width: 75%; height: 12px;"></div>
                </div>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>

        <!-- Vista de tarjetas de configuración -->
        <VRow v-else>
          <VCol v-for="view in availableCrmViews" :key="view.key" cols="12" sm="6" md="3">
            <VCard
              variant="outlined"
              class="rounded-lg cursor-pointer crm-view-card h-100"
              :class="[
                enabledCrmViews.includes(view.key) ? 'is-active' : 'opacity-60',
                { 'is-disabled': isSaving }
              ]"
              @click="toggleCrmView(view.key)"
            >
              <VCardItem class="py-4 px-4">
                <div class="d-flex flex-column h-100 justify-space-between">
                  <div>
                    <div class="d-flex align-center justify-space-between w-100 mb-2">
                      <div class="d-flex align-center">
                        <VAvatar color="primary" variant="tonal" size="32" class="me-2">
                          <VIcon :icon="view.icon" size="18" />
                        </VAvatar>
                        <span class="font-weight-black text-body-2" :class="enabledCrmViews.includes(view.key) ? 'text-high-emphasis' : 'text-disabled'">
                          {{ view.title }}
                        </span>
                      </div>
                      <VSwitch
                        :model-value="enabledCrmViews.includes(view.key)"
                        :disabled="isSaving"
                        density="compact"
                        hide-details
                        color="primary"
                        @click.stop
                        @update:model-value="toggleCrmView(view.key)"
                      />
                    </div>
                    <p class="text-caption text-medium-emphasis mb-0 leading-tight">
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
.crm-view-card {
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.2s, opacity 0.2s;
}

.crm-view-card:hover:not(.is-disabled) {
  transform: translateY(-3px);
  box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.08) !important;
}

.crm-view-card.is-active {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

.is-disabled {
  pointer-events: none;
  opacity: 0.65;
}

.bg-skeleton {
  background-color: rgba(0, 0, 0, 0.08);
}

.animate-pulse {
  animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: .4;
  }
}
</style>

