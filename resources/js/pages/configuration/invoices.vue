<script setup>
import { onMounted } from 'vue'
import { useInvoiceSettings } from '@/composables/useInvoiceSettings'

// --- Composable: toda la lógica reside aquí, el componente solo orquesta la vista ---
const {
  isLoading,
  isSaving,
  enableInvoices,
  enableInvoiceLocations,
  hasPendingChanges,
  fetchSettings,
  saveSettings,
} = useInvoiceSettings()

onMounted(fetchSettings)
</script>

<template>
  <div class="d-flex flex-column gap-6 pb-12 w-full">
    <VCard class="rounded-xl border" :class="{ 'card--saving': isSaving }">

      <!-- ── Encabezado de la tarjeta ── -->
      <VCardItem class="py-5 px-6">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <div class="icon-wrapper bg-primary rounded-lg pa-2 d-flex align-center justify-center">
              <VIcon icon="tabler-file-invoice" color="white" size="22" />
            </div>
            <div>
              <VCardTitle class="text-h6 font-weight-bold pa-0 ma-0">
                Configuración de Facturación
              </VCardTitle>
              <p class="text-caption text-medium-emphasis ma-0">
                Controla los parámetros de carga y flujos de distribución del inventario.
              </p>
            </div>
          </div>

          <!-- Indicador de guardado automático -->
          <Transition name="fade">
            <VChip
              v-if="isSaving"
              color="warning"
              size="small"
              variant="tonal"
              prepend-icon="tabler-loader-2"
              class="chip-saving"
            >
              Guardando…
            </VChip>
            <VChip
              v-else-if="!hasPendingChanges && !isLoading"
              color="success"
              size="small"
              variant="tonal"
              prepend-icon="tabler-circle-check"
            >
              Guardado
            </VChip>
          </Transition>
        </div>
      </VCardItem>

      <VDivider />

      <!-- ── Skeleton de carga ── -->
      <VCardItem v-if="isLoading" class="py-6 px-6">
        <VRow>
          <VCol v-for="n in 2" :key="n" cols="12" sm="6">
            <div class="d-flex flex-column gap-3">
              <div class="d-flex align-center justify-space-between">
                <VSkeleton type="text" width="55%" />
                <VSkeleton type="ossein" width="52px" height="24px" class="rounded-pill" />
              </div>
              <VSkeleton type="text" width="90%" />
              <VSkeleton type="text" width="75%" />
            </div>
          </VCol>
        </VRow>
      </VCardItem>

      <!-- ── Contenido real ── -->
      <VCardItem v-else class="py-6 px-6">
        <VRow>

          <!-- Habilitar Módulo de Facturas -->
          <VCol cols="12" sm="6">
            <div class="setting-item pa-4 rounded-lg h-full">
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-file-text" size="18" class="text-primary" />
                  <span class="text-body-2 font-weight-semibold text-high-emphasis">
                    Módulo de Facturas
                  </span>
                </div>
                <VSwitch
                  v-model="enableInvoices"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="saveSettings"
                />
              </div>
              <p class="text-caption text-medium-emphasis ma-0 setting-description">
                Muestra u oculta por completo las opciones del módulo de Facturas en el menú
                lateral del sistema.
              </p>

              <!-- Badge de estado del módulo -->
              <VChip
                :color="enableInvoices ? 'success' : 'default'"
                size="x-small"
                variant="tonal"
                class="mt-3"
              >
                {{ enableInvoices ? 'Activo' : 'Inactivo' }}
              </VChip>
            </div>
          </VCol>

          <!-- Habilitar Ubicaciones en Carga -->
          <VCol cols="12" sm="6">
            <div class="setting-item pa-4 rounded-lg h-full">
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-map-pin" size="18" class="text-primary" />
                  <span class="text-body-2 font-weight-semibold text-high-emphasis">
                    Ubicaciones en Carga
                  </span>
                </div>
                <VSwitch
                  v-model="enableInvoiceLocations"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving || !enableInvoices"
                  @update:model-value="saveSettings"
                />
              </div>
              <p class="text-caption text-medium-emphasis ma-0 setting-description">
                Si se deshabilita, las facturas aprobadas pasarán directamente al estado
                «Ordenadas» y los lotes se guardarán con ubicación «N/A» sin requerir
                asignación manual.
              </p>

              <!-- Badge de estado + aviso de dependencia -->
              <div class="d-flex align-center gap-2 mt-3">
                <VChip
                  :color="enableInvoiceLocations && enableInvoices ? 'success' : 'default'"
                  size="x-small"
                  variant="tonal"
                >
                  {{ enableInvoiceLocations && enableInvoices ? 'Activo' : 'Inactivo' }}
                </VChip>
                <VChip
                  v-if="!enableInvoices"
                  color="warning"
                  size="x-small"
                  variant="tonal"
                  prepend-icon="tabler-alert-triangle"
                >
                  Requiere módulo activo
                </VChip>
              </div>
            </div>
          </VCol>

        </VRow>
      </VCardItem>

    </VCard>
  </div>
</template>

<style scoped>
/* ── Contenedor de cada ajuste ── */
.setting-item {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.07);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.setting-item:hover {
  border-color: rgba(var(--v-theme-primary), 0.35);
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.06);
}

/* ── Icono del encabezado ── */
.icon-wrapper {
  background: rgb(var(--v-theme-primary));
  width: 40px;
  height: 40px;
  flex-shrink: 0;
}

/* ── Animación del chip de guardado ── */
@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

.chip-saving :deep(.v-icon:first-child) {
  animation: spin 1s linear infinite;
}

/* ── Transición fade para el chip de estado ── */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ── Limita el ancho de la descripción ── */
.setting-description {
  line-height: 1.5;
  max-width: 420px;
}

/* ── Sutil pulso mientras guarda ── */
.card--saving {
  opacity: 0.85;
  transition: opacity 0.3s ease;
}
</style>
