<template>
  <VRow class="mb-6 match-height">
    <!-- Ingresos Totales Fiscales -->
    <VCol cols="12" md="6">
      <VCard class="h-100 border shadow-sm rounded-lg" :loading="loading">
        <VCardText class="pa-4 pa-sm-5">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <h6 class="text-h6 font-weight-bold text-high-emphasis mb-1">
                Estructura de Ingresos Brutos
              </h6>
              <span class="text-caption text-medium-emphasis">
                Clasificación fiscal según base imponible de IVA / ISLR
              </span>
            </div>
            <VAvatar color="success" variant="tonal" size="36">
              <VIcon icon="tabler-trending-up" size="20" />
            </VAvatar>
          </div>

          <!-- Total Acumulado -->
          <div class="bg-grey-50 rounded-lg pa-3 border mb-4 d-flex justify-space-between align-center">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              Total Ingresos Facturados
            </span>
            <span class="text-h5 font-weight-black text-success">
              {{ formatCurrency(totalIncomeData?.total_income || 0) }}
            </span>
          </div>

          <!-- Desglose de Ingresos -->
          <div class="d-flex flex-column gap-3 mb-4">
            <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
              <div class="d-flex align-center">
                <VAvatar size="26" color="success" variant="tonal" class="me-2">
                  <VIcon icon="tabler-receipt-tax" size="14" />
                </VAvatar>
                <span class="text-caption font-weight-medium">Ventas Gravadas (Base Imponible):</span>
              </div>
              <span class="text-caption font-weight-bold text-high-emphasis">
                {{ formatCurrency(totalIncomeData?.taxable_amount || 0) }}
              </span>
            </div>

            <div class="d-flex justify-space-between align-center pa-2 rounded border-sm">
              <div class="d-flex align-center">
                <VAvatar size="26" color="info" variant="tonal" class="me-2">
                  <VIcon icon="tabler-shield-check" size="14" />
                </VAvatar>
                <span class="text-caption font-weight-medium">Ventas Exentas / Exoneradas:</span>
              </div>
              <span class="text-caption font-weight-bold text-high-emphasis">
                {{ formatCurrency(totalIncomeData?.exempt_amount || 0) }}
              </span>
            </div>
          </div>

          <!-- Barra de Proporción -->
          <div class="pt-2">
            <div class="d-flex justify-space-between align-center text-caption font-weight-bold mb-1">
              <span class="text-success">
                {{ (totalIncomeData?.taxable_percentage || 0).toFixed(1) }}% Gravadas
              </span>
              <span class="text-info">
                {{ (totalIncomeData?.exempt_percentage || 0).toFixed(1) }}% Exentas
              </span>
            </div>
            <VProgressLinear
              :model-value="totalIncomeData?.taxable_percentage || 0"
              color="success"
              bg-color="info"
              bg-opacity="1"
              height="8"
              rounded
            />
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Gastos Deducibles -->
    <VCol cols="12" md="6">
      <VCard class="h-100 border shadow-sm rounded-lg" :loading="loading">
        <VCardText class="pa-4 pa-sm-5 d-flex flex-column justify-space-between h-100">
          <div>
            <div class="d-flex justify-space-between align-center mb-4">
              <div>
                <h6 class="text-h6 font-weight-bold text-high-emphasis mb-1">
                  Gastos Deducibles de ISLR
                </h6>
                <span class="text-caption text-medium-emphasis">
                  Egresos operativos con soporte de factura fiscal válida
                </span>
              </div>
              <VAvatar color="warning" variant="tonal" size="36">
                <VIcon icon="tabler-receipt" size="20" />
              </VAvatar>
            </div>

            <!-- Total Deducible -->
            <div class="bg-grey-50 rounded-lg pa-3 border mb-4 d-flex justify-space-between align-center">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                Total Gastos Deducibles
              </span>
              <span class="text-h5 font-weight-black text-warning">
                {{ formatCurrency(deductibleExpensesData?.total_deductible || 0) }}
              </span>
            </div>

            <!-- Lista de Gastos Deducibles -->
            <VList density="compact" class="pa-0">
              <VListItem
                v-for="category in deductibleExpensesData?.categories || []"
                :key="category.category_id"
                class="px-2 py-1 mb-1 rounded border-sm"
              >
                <template #prepend>
                  <VIcon icon="tabler-circle-check" size="16" color="success" class="me-2" />
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
                v-if="!deductibleExpensesData?.categories?.length"
                class="text-center py-6 text-caption text-medium-emphasis"
              >
                <VIcon icon="tabler-receipt-off" size="28" class="mb-1 d-block mx-auto text-disabled" />
                No se registraron gastos deducibles en el año {{ year }}
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
  totalIncomeData: { type: Object, required: true },
  deductibleExpensesData: { type: Object, required: true },
  year: { type: Number, required: true },
  formatCurrency: { type: Function, required: true },
});
</script>
