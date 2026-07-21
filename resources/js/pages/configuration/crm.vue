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

const availableCrmViews = [
  { key: 'clients', title: 'Clientes', description: 'Gestión de ficha de clientes, hábitos de compra y trazabilidad.', icon: 'tabler-users' },
  { key: 'companies', title: 'Convenios / Empresas', description: 'Gestión de acuerdos corporativos y descuentos institucionales.', icon: 'tabler-building' },
  { key: 'doctors', title: 'Médicos', description: 'Registro de médicos tratantes, especialidades y comisiones.', icon: 'tabler-stethoscope' },
  { key: 'lottery', title: 'Sorteo / Lotería', description: 'Campañas de fidelización, emisión de boletos y rifas.', icon: 'tabler-ticket' },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    if (settings.enabled_crm_views) {
      enabledCrmViews.value = settings.enabled_crm_views
    }
  } catch (error) {
    console.error("Error cargando configuración del CRM:", error)
    toast.error("Error al cargar la configuración")
  }
}

const toggleCrmView = (key) => {
  const index = enabledCrmViews.value.indexOf(key)
  if (index > -1) {
    enabledCrmViews.value.splice(index, 1)
  } else {
    enabledCrmViews.value.push(key)
  }
  updateSettings()
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      enabled_crm_views: enabledCrmViews.value
    })
    await brandingStore.fetchSettings()

    toast.success("Configuración de vistas del CRM actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.error("Error al actualizar la configuración")
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
        </VCardTitle>
        <p class="text-caption text-medium-emphasis mb-6">
          Habilita o deshabilita los módulos y vistas del CRM en la barra de navegación lateral. Las vistas desmarcadas se ocultarán inmediatamente para todos los usuarios.
        </p>

        <VRow>
          <VCol v-for="view in availableCrmViews" :key="view.key" cols="12" sm="6" md="3">
            <VCard
              variant="outlined"
              class="rounded-lg cursor-pointer transition-all h-100"
              :class="enabledCrmViews.includes(view.key) ? 'border-primary bg-var-theme-background' : 'opacity-60'"
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
