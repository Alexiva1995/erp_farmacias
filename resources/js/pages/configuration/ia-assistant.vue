<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"

const brandingStore = useBrandingStore()

// --- Estado de la UI ---
const isLoading = ref(true)
const isSaving = ref(false)

// --- Datos reactivos ---
const enabledIaAssistantViews = ref([])

// Catálogo estático de vistas disponibles (no necesita ser reactivo)
const availableIaAssistantViews = [
  { key: 'pedidos',       title: 'Pedidos',               description: 'Acceso al asistente inteligente para preparar y consolidar sugerencias de pedidos.',   icon: 'tabler-shopping-cart'      },
  { key: 'reporte',       title: 'Reporte',               description: 'Análisis detallado y reporte general generado por la IA sobre faltantes y excesos.',    icon: 'tabler-file-report'        },
  { key: 'oportunidad',   title: 'Oportunidad de Mercado',description: 'Detección inteligente de ofertas y diferencias de precios entre proveedores.',          icon: 'tabler-bulb'               },
  { key: 'comparador',    title: 'Comparador',            description: 'Comparador visual de costos, ofertas y condiciones comerciales de productos.',           icon: 'tabler-scale'              },
  { key: 'automatizacion',title: 'Automatización',        description: 'Reglas de reposición automática periódicas con cron y min-solicitar.',                  icon: 'tabler-settings-automation'},
]

// Computed usando Set para O(1) lookup en lugar de O(n) includes() por cada render
const enabledSet = computed(() => new Set(enabledIaAssistantViews.value))

// Verifica si una vista está habilitada
const isEnabled = (key) => enabledSet.value.has(key)

// --- Carga inicial de configuración (solo el campo necesario) ---
const fetchSettings = async () => {
  isLoading.value = true
  try {
    // Utiliza el parámetro ?only= que el Resource ya soporta para evitar payload masivo
    const response = await axios.get('/general-settings?only=enabled_ia_assistant_views')
    const views = response.data.data?.enabled_ia_assistant_views
    if (Array.isArray(views)) {
      enabledIaAssistantViews.value = views
    }
  } catch (error) {
    console.error("Error cargando configuración de IA Assistence:", error)
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

// --- Toggle sin doble disparo: solo desde el switch, card actúa como label ---
const toggleIaView = async (key) => {
  if (isSaving.value) return

  // Mutación inmutable: filter/push en lugar de splice
  if (enabledSet.value.has(key)) {
    enabledIaAssistantViews.value = enabledIaAssistantViews.value.filter(k => k !== key)
  } else {
    enabledIaAssistantViews.value = [...enabledIaAssistantViews.value, key]
  }

  await updateSettings()
}

// --- Persistencia: bloquea interacciones durante el guardado ---
const updateSettings = async () => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_ia_assistant_views: enabledIaAssistantViews.value,
    })
    // Sincroniza el store global para que el menú lateral refleje el cambio
    await brandingStore.fetchSettings()
    toast.success("Configuración de IA Assistence actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar configuración de IA:", error)
    toast.error("Error al actualizar la configuración")
    // Recargar para revertir el estado optimista en caso de error
    await fetchSettings()
  } finally {
    isSaving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div>
    <!-- Encabezado de página -->
    <div class="d-flex align-center gap-3 mb-6">
      <VAvatar color="primary" variant="tonal" size="44" rounded>
        <VIcon icon="tabler-brain" size="24" />
      </VAvatar>
      <div>
        <h1 class="text-h5 font-weight-black mb-0">
          Módulos IA Assistence
        </h1>
        <p class="text-caption text-medium-emphasis mb-0">
          Habilita o deshabilita las secciones del menú de IA. Los cambios son inmediatos para todos los usuarios.
        </p>
      </div>
    </div>

    <!-- Tarjeta Principal -->
    <VCard class="rounded-lg" border>
      <VCardItem class="py-5 px-5">

        <!-- Skeleton de carga -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 5" :key="n" cols="12" sm="6" md="4">
            <VSkeletonLoader type="card" height="120" />
          </VCol>
        </VRow>

        <!-- Grid de módulos -->
        <VRow v-else>
          <VCol
            v-for="view in availableIaAssistantViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="4"
          >
            <!--
              La card actúa solo como contenedor visual (no dispara toggle)
              El VSwitch es el único control de la acción para evitar doble disparo
            -->
            <VCard
              variant="outlined"
              class="rounded-lg h-100 ia-view-card"
              :class="{
                'border-primary': isEnabled(view.key),
                'ia-view-card--active': isEnabled(view.key),
                'opacity-60': !isEnabled(view.key),
              }"
            >
              <VCardItem class="py-4 px-4">
                <div class="d-flex flex-column h-100 justify-space-between">
                  <div>
                    <!-- Cabecera: ícono + título + switch -->
                    <div class="d-flex align-center justify-space-between w-100 mb-3">
                      <div class="d-flex align-center gap-2">
                        <VAvatar
                          :color="isEnabled(view.key) ? 'primary' : 'secondary'"
                          variant="tonal"
                          size="36"
                        >
                          <VIcon :icon="view.icon" size="20" />
                        </VAvatar>
                        <span
                          class="font-weight-black text-body-2"
                          :class="isEnabled(view.key) ? 'text-high-emphasis' : 'text-disabled'"
                        >
                          {{ view.title }}
                        </span>
                      </div>

                      <!-- Switch: único punto de entrada para el toggle -->
                      <VSwitch
                        :model-value="isEnabled(view.key)"
                        :disabled="isSaving"
                        density="compact"
                        hide-details
                        color="primary"
                        @update:model-value="toggleIaView(view.key)"
                      />
                    </div>

                    <!-- Descripción -->
                    <p class="text-caption text-medium-emphasis mb-0 lh-sm">
                      {{ view.description }}
                    </p>
                  </div>
                </div>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>

      </VCardItem>

      <!-- Indicador de guardado en el pie de la tarjeta -->
      <VDivider v-if="isSaving" />
      <VCardText v-if="isSaving" class="py-2 d-flex align-center gap-2 text-caption text-medium-emphasis">
        <VProgressCircular indeterminate size="14" width="2" color="primary" />
        Guardando cambios...
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
/* Transición suave al activar/desactivar una tarjeta */
.ia-view-card {
  transition: border-color 0.25s ease, opacity 0.25s ease, background-color 0.25s ease;
  cursor: default;
}

/* Leve fondo tonal cuando la vista está activa */
.ia-view-card--active {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>
