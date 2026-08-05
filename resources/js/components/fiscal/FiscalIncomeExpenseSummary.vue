<template>
  <VRow class="mb-6">
    <!-- Estado de Resultados -->
    <VCol cols="12" md="8">
      <VCard class="h-100" :loading="loading">
        <VCardText>
          <div class="d-flex justify-space-between align-center mb-6">
            <h6 class="text-h6 font-weight-medium">Estado de Resultados</h6>
            <VChip color="primary" variant="tonal">
              <VIcon icon="tabler-calculator" size="16" class="mr-1" />
              Año {{ year }}
            </VChip>
          </div>

          <VRow>
            <VCol cols="12" sm="6">
              <VCard variant="outlined" class="mb-4">
                <VCardText>
                  <div class="text-center">
                    <div class="text-body-2 text-medium-emphasis mb-2">
                      Ingresos Totales
                    </div>
                    <div class="text-h4 font-weight-bold text-success mb-2">
                      {{ formatCurrency(revenueStats?.total_income || 0) }}
                    </div>
                    <VChip size="small" color="success" variant="tonal">
                      <VIcon
                        icon="tabler-trending-up"
                        size="14"
                        class="mr-1"
                      />
                      Historial Fiscal
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6">
              <VCard variant="outlined" class="mb-4">
                <VCardText>
                  <div class="text-center">
                    <div class="text-body-2 text-medium-emphasis mb-2">
                      Gastos Totales
                    </div>
                    <div class="text-h4 font-weight-bold text-warning mb-2">
                      {{ formatCurrency(revenueStats?.total_expenses || 0) }}
                    </div>
                    <VChip size="small" color="warning" variant="tonal">
                      <VIcon icon="tabler-receipt" size="14" class="mr-1" />
                      Gastos Registrados
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <div class="d-flex gap-3 mt-4">
            <VBtn
              color="primary"
              variant="tonal"
              prepend-icon="tabler-file-download"
              @click="$emit('download-report')"
            >
              Descargar Reporte
            </VBtn>
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-printer"
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
      <VCard
        color="error-lighten-5"
        variant="tonal"
        class="h-100"
        :loading="loading"
      >
        <VCardText>
          <div class="d-flex justify-space-between align-center mb-4">
            <h6 class="text-h6 font-weight-medium">Gastos No Deducibles</h6>
            <VIcon icon="tabler-alert-circle" color="error" />
          </div>

          <div class="mb-4">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-body-1">Total No Deducible</span>
              <span class="text-h5 font-weight-bold text-error">
                {{
                  formatCurrency(
                    nonDeductibleExpensesData?.total_non_deductible || 0,
                  )
                }}
              </span>
            </div>
          </div>

          <VDivider class="my-4" />

          <VList density="compact" class="pa-0 bg-transparent">
            <VListItem
              v-for="category in nonDeductibleExpensesData?.categories || []"
              :key="category.category_id"
              class="px-0 mb-2"
            >
              <VListItemTitle class="text-body-2">
                {{ category.category_name }}
              </VListItemTitle>
              <template #append>
                <span class="text-body-2 font-weight-medium">
                  {{ formatCurrency(category.total_amount) }}
                </span>
              </template>
            </VListItem>

            <VListItem
              v-if="!nonDeductibleExpensesData?.categories?.length"
              class="px-0"
            >
              <VListItemTitle
                class="text-body-2 text-center text-medium-emphasis"
              >
                No hay gastos no deducibles para {{ year }}
              </VListItemTitle>
            </VListItem>
          </VList>
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
