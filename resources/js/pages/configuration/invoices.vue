<script setup>
import { onMounted } from 'vue'
import { useInvoiceSettings } from '@/composables/useInvoiceSettings'

// --- Composable: orquesta estado, persistencia, permisos y diálogo de confirmación ---
const {
  isLoading,
  isSaving,
  enableInvoices,
  enableInvoiceLocations,
  hasPendingChanges,
  canManageSettings,
  fetchSettings,
  handleSettingChange,
} = useInvoiceSettings()

onMounted(fetchSettings)
</script>

<template>
  <div class="d-flex flex-column gap-6 pb-12 w-100">
    <VCard class="border">
      <!-- ── Encabezado de la tarjeta ── -->
      <VCardItem class="py-5 px-6">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="42"
            >
              <VIcon
                icon="tabler-file-invoice"
                size="24"
              />
            </VAvatar>
            <div>
              <VCardTitle class="text-h6 font-weight-bold pa-0 ma-0">
                Configuración de Facturación
              </VCardTitle>
              <p class="text-body-2 text-medium-emphasis ma-0">
                Controla los parámetros de carga y flujos de distribución del inventario.
              </p>
            </div>
          </div>

          <!-- Indicador de estado de persistencia -->
          <div class="d-flex align-center">
            <VChip
              v-if="isSaving"
              color="warning"
              size="small"
              variant="tonal"
              prepend-icon="tabler-loader-2"
            >
              Guardando cambios…
            </VChip>
            <VChip
              v-else-if="!hasPendingChanges && !isLoading"
              color="success"
              size="small"
              variant="tonal"
              prepend-icon="tabler-circle-check"
            >
              Sincronizado
            </VChip>
          </div>
        </div>
      </VCardItem>

      <VDivider />

      <!-- ── Skeleton de carga nativo de Vuetify 3 ── -->
      <VCardText
        v-if="isLoading"
        class="pa-6"
      >
        <VRow>
          <VCol
            v-for="n in 2"
            :key="n"
            cols="12"
            md="6"
          >
            <VSkeletonLoader type="article" />
          </VCol>
        </VRow>
      </VCardText>

      <!-- ── Contenido real con controles estructurados ── -->
      <VCardText
        v-else
        class="pa-6"
      >
        <VRow>
          <!-- Habilitar Módulo de Facturas -->
          <VCol
            cols="12"
            md="6"
          >
            <VCard
              variant="outlined"
              class="h-100 pa-4"
              :color="enableInvoices ? 'primary' : undefined"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-file-text"
                    size="20"
                    :color="enableInvoices ? 'primary' : 'secondary'"
                  />
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                    Módulo de Facturas
                  </span>
                  <VTooltip
                    location="top"
                    text="Al desactivarlo, se ocultará el menú y se bloqueará el acceso a las vistas de facturas."
                  >
                    <template #activator="{ props: tooltipProps }">
                      <VIcon
                        v-bind="tooltipProps"
                        icon="tabler-info-circle"
                        size="16"
                        class="text-medium-emphasis cursor-pointer"
                      />
                    </template>
                  </VTooltip>
                </div>
                <VSwitch
                  v-model="enableInvoices"
                  color="primary"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="isSaving || !canManageSettings"
                  @update:model-value="val => handleSettingChange('enableInvoices', val)"
                />
              </div>

              <p class="text-caption text-medium-emphasis mb-4">
                Muestra u oculta por completo las opciones del módulo de Facturas en el menú lateral del sistema.
              </p>

              <!-- Badge de estado del módulo -->
              <VChip
                :color="enableInvoices ? 'success' : 'error'"
                size="small"
                variant="tonal"
              >
                {{ enableInvoices ? 'Módulo Activo' : 'Módulo Inactivo' }}
              </VChip>
            </VCard>
          </VCol>

          <!-- Habilitar Ubicaciones en Carga -->
          <VCol
            cols="12"
            md="6"
          >
            <VCard
              variant="outlined"
              class="h-100 pa-4"
              :class="{ 'opacity-50': !enableInvoices }"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-map-pin"
                    size="20"
                    color="primary"
                  />
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                    Ubicaciones en Carga
                  </span>
                  <VTooltip
                    location="top"
                    text="Define si se exige asignación física de anaquel o lote durante la aprobación."
                  >
                    <template #activator="{ props: tooltipProps }">
                      <VIcon
                        v-bind="tooltipProps"
                        icon="tabler-info-circle"
                        size="16"
                        class="text-medium-emphasis cursor-pointer"
                      />
                    </template>
                  </VTooltip>
                </div>
                <VSwitch
                  v-model="enableInvoiceLocations"
                  color="primary"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="isSaving || !enableInvoices || !canManageSettings"
                  @update:model-value="val => handleSettingChange('enableInvoiceLocations', val)"
                />
              </div>

              <p class="text-caption text-medium-emphasis mb-4">
                Si se deshabilita, las facturas aprobadas pasarán directamente al estado «Ordenadas» y los lotes se guardarán con ubicación «N/A».
              </p>

              <!-- Badge de estado + aviso de dependencia -->
              <div class="d-flex align-center gap-2 flex-wrap">
                <VChip
                  :color="enableInvoiceLocations && enableInvoices ? 'success' : 'default'"
                  size="small"
                  variant="tonal"
                >
                  {{ enableInvoiceLocations && enableInvoices ? 'Habilitado' : 'Deshabilitado' }}
                </VChip>
                <VChip
                  v-if="!enableInvoices"
                  color="warning"
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-alert-triangle"
                >
                  Requiere módulo activo
                </VChip>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
