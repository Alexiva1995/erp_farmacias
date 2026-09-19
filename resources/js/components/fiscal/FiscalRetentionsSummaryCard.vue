<template>
  <VRow class="mb-6 match-height">
    <!-- Retenciones de IVA Aplicadas / Enteradas -->
    <VCol cols="12" md="6">
      <VCard class="h-100 border shadow-sm rounded-lg" :loading="loading">
        <VCardText class="pa-4 pa-sm-5 d-flex flex-column justify-space-between h-100">
          <div>
            <div class="d-flex justify-space-between align-center mb-3">
              <div>
                <h6 class="text-h6 font-weight-bold text-high-emphasis mb-1">
                  Retenciones de IVA Aplicadas
                </h6>
                <span class="text-caption text-medium-emphasis">
                  Comprobantes generados a proveedores (Agente de Retención)
                </span>
              </div>
              <VAvatar color="primary" variant="tonal" size="36">
                <VIcon icon="tabler-file-certificate" size="20" />
              </VAvatar>
            </div>

            <!-- Total Retenido -->
            <div class="bg-grey-50 rounded-lg pa-3 border mb-4 d-flex justify-space-between align-center">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                Total IVA Retenido
              </span>
              <span class="text-h5 font-weight-black text-primary">
                {{ formatCurrency(retentionsData?.generated?.total_withheld || 0) }}
              </span>
            </div>

            <!-- Detalle de Base y Crédito -->
            <div class="d-flex flex-column gap-2 mb-2">
              <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
                <span class="text-caption font-weight-medium">Comprobantes Generados:</span>
                <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                  {{ retentionsData?.generated?.count || 0 }} registros
                </VChip>
              </div>

              <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
                <span class="text-caption font-weight-medium">Base Imponible Compras:</span>
                <span class="text-caption font-weight-bold text-high-emphasis">
                  {{ formatCurrency(retentionsData?.generated?.total_base || 0) }}
                </span>
              </div>

              <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
                <span class="text-caption font-weight-medium">IVA Total Facturado:</span>
                <span class="text-caption font-weight-bold text-high-emphasis">
                  {{ formatCurrency(retentionsData?.generated?.total_tax || 0) }}
                </span>
              </div>
            </div>
          </div>

          <div class="pt-3 border-t d-flex align-center justify-space-between">
            <span class="text-caption text-medium-emphasis">
              Ejercicio Fiscal {{ year }}
            </span>
            <VBtn
              variant="text"
              color="primary"
              size="small"
              to="/fiscal/retenciones"
              append-icon="tabler-arrow-right"
              class="font-weight-bold"
            >
              Ver Módulo de Retenciones
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Retenciones de IVA Pendientes por Enterar / Gestionar -->
    <VCol cols="12" md="6">
      <VCard class="h-100 border shadow-sm rounded-lg" :loading="loading">
        <VCardText class="pa-4 pa-sm-5 d-flex flex-column justify-space-between h-100">
          <div>
            <div class="d-flex justify-space-between align-center mb-3">
              <div>
                <h6 class="text-h6 font-weight-bold text-high-emphasis mb-1">
                  Retenciones de IVA por Generar
                </h6>
                <span class="text-caption text-medium-emphasis">
                  Facturas de compras gravadas pendientes de comprobante fiscal
                </span>
              </div>
              <VAvatar color="warning" variant="tonal" size="36">
                <VIcon icon="tabler-clock-hour-4" size="20" />
              </VAvatar>
            </div>

            <!-- Total Estimado Pendiente -->
            <div class="bg-grey-50 rounded-lg pa-3 border mb-4 d-flex justify-space-between align-center">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                IVA Retenido Estimado (75%)
              </span>
              <span class="text-h5 font-weight-black text-warning">
                {{ formatCurrency(retentionsData?.pending?.estimated_withheld || 0) }}
              </span>
            </div>

            <!-- Detalle de Facturas Pendientes -->
            <div class="d-flex flex-column gap-2 mb-2">
              <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
                <span class="text-caption font-weight-medium">Facturas con IVA Pendientes:</span>
                <VChip size="x-small" color="warning" variant="tonal" class="font-weight-bold">
                  {{ retentionsData?.pending?.count || 0 }} facturas
                </VChip>
              </div>

              <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
                <span class="text-caption font-weight-medium">Base Imponible Pendiente:</span>
                <span class="text-caption font-weight-bold text-high-emphasis">
                  {{ formatCurrency(retentionsData?.pending?.total_base || 0) }}
                </span>
              </div>

              <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
                <span class="text-caption font-weight-medium">Total IVA por Retener:</span>
                <span class="text-caption font-weight-bold text-high-emphasis">
                  {{ formatCurrency(retentionsData?.pending?.total_tax || 0) }}
                </span>
              </div>
            </div>
          </div>

          <div class="pt-3 border-t d-flex align-center justify-space-between">
            <span class="text-caption text-medium-emphasis">
              Flujo de Caja Fiscal
            </span>
            <VBtn
              variant="tonal"
              color="warning"
              size="small"
              to="/fiscal/retenciones"
              prepend-icon="tabler-plus"
              class="font-weight-bold"
            >
              Procesar Retenciones
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  retentionsData: { type: Object, required: true },
  year: { type: Number, required: true },
  formatCurrency: { type: Function, required: true },
});
</script>
