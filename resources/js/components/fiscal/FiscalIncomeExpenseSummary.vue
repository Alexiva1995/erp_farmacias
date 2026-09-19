<template>
  <VRow class="mb-6 match-height">
    <!-- Estado de Resultados Fiscal -->
    <VCol cols="12" md="8">
      <VCard class="h-100 border shadow-sm rounded-lg" :loading="loading">
        <VCardText class="pa-4 pa-sm-5 d-flex flex-column justify-space-between h-100">
          <div>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <h6 class="text-h6 font-weight-bold text-high-emphasis mb-1">
                  Resumen de Conciliación Fiscal
                </h6>
                <span class="text-caption text-medium-emphasis">
                  Ingresos facturados vs. Total de egresos registrados en el período
                </span>
              </div>
              <VChip color="primary" variant="tonal" size="small" class="font-weight-semibold">
                <VIcon icon="tabler-calendar" size="14" class="me-1" />
                Año Fiscal {{ year }}
              </VChip>
            </div>

            <VRow class="mb-2">
              <VCol cols="12" sm="6">
                <VCard variant="outlined" class="rounded-lg border bg-surface">
                  <VCardText class="pa-4 text-center">
                    <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
                      Ingresos Totales
                    </span>
                    <div class="text-h5 font-weight-black text-success mb-2">
                      {{ formatCurrency(revenueStats?.total_income || 0) }}
                    </div>
                    <VChip size="x-small" color="success" variant="tonal" class="font-weight-bold">
                      <VIcon icon="tabler-trending-up" size="12" class="me-1" />
                      Historial Fiscal
                    </VChip>
                  </VCardText>
                </VCard>
              </VCol>

              <VCol cols="12" sm="6">
                <VCard variant="outlined" class="rounded-lg border bg-surface">
                  <VCardText class="pa-4 text-center">
                    <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
                      Gastos Totales
                    </span>
                    <div class="text-h5 font-weight-black text-warning mb-2">
                      {{ formatCurrency(revenueStats?.total_expenses || 0) }}
                    </div>
                    <VChip size="x-small" color="warning" variant="tonal" class="font-weight-bold">
                      <VIcon icon="tabler-receipt" size="12" class="me-1" />
                      Gastos Registrados
                    </VChip>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </div>

          <div class="d-flex flex-wrap gap-3 pt-3 border-t">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-file-download"
              class="font-weight-bold"
              @click="$emit('download-report')"
            >
              Descargar Reporte Fiscal
            </VBtn>
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-printer"
              class="font-weight-bold"
              @click="$emit('print-report')"
            >
              Imprimir
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Gastos No Deducibles -->
    <VCol cols="12" md="4">
      <VCard class="h-100 border shadow-sm rounded-lg" :loading="loading">
        <VCardText class="pa-4 pa-sm-5 d-flex flex-column justify-space-between h-100">
          <div>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <h6 class="text-h6 font-weight-bold text-high-emphasis mb-1">
                  Gastos No Deducibles
                </h6>
                <span class="text-caption text-medium-emphasis">
                  Partidas no admisibles fiscalmente
                </span>
              </div>
              <VAvatar color="error" variant="tonal" size="36">
                <VIcon icon="tabler-alert-circle" size="20" />
              </VAvatar>
            </div>

            <!-- Total No Deducible -->
            <div class="bg-grey-50 rounded-lg pa-3 border mb-4 d-flex justify-space-between align-center">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                Total No Deducible
              </span>
              <span class="text-h5 font-weight-black text-error">
                {{ formatCurrency(nonDeductibleExpensesData?.total_non_deductible || 0) }}
              </span>
            </div>

            <!-- Lista de Gastos No Deducibles -->
            <VList density="compact" class="pa-0">
              <VListItem
                v-for="category in nonDeductibleExpensesData?.categories || []"
                :key="category.category_id"
                class="px-2 py-1 mb-1 rounded border-sm"
              >
                <template #prepend>
                  <VIcon icon="tabler-x" size="14" color="error" class="me-2" />
                </template>
                <VListItemTitle class="text-caption font-weight-medium">
                  {{ category.category_name }}
                </VListItemTitle>
                <template #append>
                  <span class="text-caption font-weight-bold text-high-emphasis">
                    {{ formatCurrency(category.total_amount) }}
                  </span>
                </template>
              </VListItem>

              <div
                v-if="!nonDeductibleExpensesData?.categories?.length"
                class="text-center py-6 text-caption text-medium-emphasis"
              >
                <VIcon icon="tabler-circle-check" size="28" class="mb-1 d-block mx-auto text-success" />
                No hay gastos no deducibles registrados para {{ year }}
              </div>
            </VList>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  revenueStats: { type: Object, required: true },
  nonDeductibleExpensesData: { type: Object, required: true },
  year: { type: Number, required: true },
  formatCurrency: { type: Function, required: true },
});

defineEmits(["download-report", "print-report"]);
</script>
