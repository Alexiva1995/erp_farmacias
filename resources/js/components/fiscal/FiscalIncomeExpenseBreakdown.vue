<template>
  <VRow class="mb-6 match-height">
    <!-- Ingresos Totales -->
    <VCol cols="12" md="6">
      <VCard class="h-100" :loading="loading">
        <VCardText>
          <div class="d-flex justify-space-between align-center mb-4">
            <h6 class="text-h6 font-weight-medium">Ingresos Totales</h6>
            <VIcon icon="tabler-trending-up" color="success" />
          </div>

          <div class="mb-6">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-body-1">Total Acumulado</span>
              <span class="text-h4 font-weight-bold text-success">
                {{ formatCurrency(totalIncomeData?.total_income || 0) }}
              </span>
            </div>
          </div>

          <VDivider class="my-4" />

          <!-- Desglose de Ingresos -->
          <div class="mb-4">
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-checkbox-circle"
                  color="success"
                  size="20"
                  class="mr-2"
                />
                <span class="text-body-2">Ventas Gravadas</span>
              </div>
              <span class="text-body-1 font-weight-medium">
                {{ formatCurrency(totalIncomeData?.taxable_amount || 0) }}
              </span>
            </div>

            <div class="d-flex justify-space-between align-center">
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-circle-check"
                  color="info"
                  size="20"
                  class="mr-2"
                />
                <span class="text-body-2">Ventas Exentas</span>
              </div>
              <span class="text-body-1 font-weight-medium">
                {{ formatCurrency(totalIncomeData?.exempt_amount || 0) }}
              </span>
            </div>
          </div>

          <VProgressLinear
            :model-value="totalIncomeData?.taxable_percentage || 0"
            color="success"
            height="8"
            rounded
            class="mb-2"
          />
          <div class="text-caption text-medium-emphasis">
            {{ (totalIncomeData?.taxable_percentage || 0).toFixed(0) }}%
            Gravadas |
            {{ (totalIncomeData?.exempt_percentage || 0).toFixed(0) }}%
            Exentas
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Gastos Deducibles -->
    <VCol cols="12" md="6">
      <VCard class="h-100" :loading="loading">
        <VCardText>
          <div class="d-flex justify-space-between align-center mb-4">
            <h6 class="text-h6 font-weight-medium">Gastos Deducibles</h6>
            <VIcon icon="tabler-receipt" color="warning" />
          </div>

          <div class="mb-6">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-body-1">Total Deducible</span>
              <span class="text-h4 font-weight-bold text-warning">
                {{
                  formatCurrency(
                    deductibleExpensesData?.total_deductible || 0,
                  )
                }}
              </span>
            </div>
          </div>

          <VDivider class="my-4" />

          <!-- Lista de Gastos Deducibles -->
          <VList density="compact" class="pa-0">
            <VListItem
              v-for="category in deductibleExpensesData?.categories || []"
              :key="category.category_id"
              class="px-0 mb-2"
            >
              <VListItemTitle class="text-body-2">
                {{ category.category_name }}
              </VListItemTitle>
              <template #append>
                <span class="text-body-1 font-weight-medium">
                  {{ formatCurrency(category.total_amount) }}
                </span>
              </template>
            </VListItem>

            <VListItem
              v-if="!deductibleExpensesData?.categories?.length"
              class="px-0"
            >
              <VListItemTitle
                class="text-body-2 text-center text-medium-emphasis"
              >
                No hay gastos deducibles para {{ year }}
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
  totalIncomeData: { type: Object, required: true },
  deductibleExpensesData: { type: Object, required: true },
  year: { type: Number, required: true },
  formatCurrency: { type: Function, required: true },
});
</script>
