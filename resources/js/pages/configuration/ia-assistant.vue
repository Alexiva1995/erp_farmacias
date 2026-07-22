<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()

const enabledIaAssistantViews = ref([
  'pedidos',
  'reporte',
  'oportunidad',
  'comparador',
  'automatizacion'
])

const availableIaAssistantViews = [
  { key: 'pedidos', title: 'Pedidos', description: 'Acceso al asistente inteligente para preparar y consolidar sugerencias de pedidos.', icon: 'tabler-shopping-cart' },
  { key: 'reporte', title: 'Reporte', description: 'Análisis detallado y reporte general generado por la IA sobre faltantes y excesos.', icon: 'tabler-file-report' },
  { key: 'oportunidad', title: 'Oportunidad de Mercado', description: 'Detección inteligente de ofertas y diferencias de precios entre proveedores.', icon: 'tabler-bulb' },
  { key: 'comparador', title: 'Comparador', description: 'Comparador visual de costos, ofertas y condiciones comerciales de productos.', icon: 'tabler-scale' },
  { key: 'automatizacion', title: 'Automatización', description: 'Reglas de reposición automática periódicas con cron y min-solicitar.', icon: 'tabler-settings-automation' },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    if (settings.enabled_ia_assistant_views) {
      enabledIaAssistantViews.value = settings.enabled_ia_assistant_views
    }
  } catch (error) {
    console.error("Error cargando configuración de IA Assistence:", error)
    toast.error("Error al cargar la configuración")
  }
}

const toggleIaView = (key) => {
  const index = enabledIaAssistantViews.value.indexOf(key)
  if (index > -1) {
    enabledIaAssistantViews.value.splice(index, 1)
  } else {
    enabledIaAssistantViews.value.push(key)
  }
  updateSettings()
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      enabled_ia_assistant_views: enabledIaAssistantViews.value,
    })
    await brandingStore.fetchSettings()

    toast.success("Configuración de IA Assistence actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar configuración de IA:", error)
    toast.error("Error al actualizar la configuración")
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <!-- Tarjeta Principal de Configuración de Vistas de IA Assistence -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-brain" class="text-primary" size="26" />
          Configuración de Vistas de IA Assistence
        </VCardTitle>
        <p class="text-caption text-medium-emphasis mb-6">
          Habilita o deshabilita los módulos del menú lateral de IA Assistence. Las vistas desmarcadas se ocultarán inmediatamente para todos los usuarios.
        </p>

        <VRow>
          <VCol v-for="view in availableIaAssistantViews" :key="view.key" cols="12" sm="6" md="3">
            <VCard
              variant="outlined"
              class="rounded-lg cursor-pointer transition-all h-100"
              :class="enabledIaAssistantViews.includes(view.key) ? 'border-primary bg-var-theme-background' : 'opacity-60'"
              @click="toggleIaView(view.key)"
            >
              <VCardItem class="py-4 px-4">
                <div class="d-flex flex-column h-100 justify-space-between">
                  <div>
                    <div class="d-flex align-center justify-space-between w-100 mb-2">
                      <div class="d-flex align-center">
                        <VAvatar color="primary" variant="tonal" size="32" class="me-2">
                          <VIcon :icon="view.icon" size="18" />
                        </VAvatar>
                        <span class="font-weight-black text-body-2" :class="enabledIaAssistantViews.includes(view.key) ? 'text-high-emphasis' : 'text-disabled'">
                          {{ view.title }}
                        </span>
                      </div>
                      <VSwitch
                        :model-value="enabledIaAssistantViews.includes(view.key)"
                        density="compact"
                        hide-details
                        color="primary"
                        @click.stop
                        @update:model-value="toggleIaView(view.key)"
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
