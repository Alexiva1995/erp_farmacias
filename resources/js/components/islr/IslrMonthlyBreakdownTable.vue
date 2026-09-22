<script setup>
// Tabla de auditoría mensual de ISLR
import { computed } from "vue";

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  isCeEnabled: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(number);
};

const headers = computed(() => {
  const baseHeaders = [
    { title: "PERÍODO / MES", key: "month_name", sortable: false },
    { title: "INGRESOS BRUTOS (BS.)", key: "fiscal_total", align: "end", sortable: false },
    { title: "COSTOS COMPRAS (BS.)", key: "costs", align: "end", sortable: false },
    { title: "GASTOS DEDUCIBLES (BS.)", key: "deductions", align: "end", sortable: false },
    { title: "GASTOS NO DEDUCIBLES (BS.)", key: "non_deductible", align: "end", sortable: false },
    { title: "BASE IMPONIBLE (BS.)", key: "net_income", align: "end", sortable: false },
  ];

  if (props.isCeEnabled) {
    baseHeaders.push({
      title: "ANTICIPO ISLR CE (0.75%)",
      key: "estimated_prepayment",
      align: "end",
      sortable: false,
    });
  }

  return baseHeaders;
});
</script>

<template>
  <VCard class="ma-0 rounded-lg border-0 shadow-sm overflow-hidden bg-surface mt-3">
    <VCardTitle class="pa-4 px-6 d-flex align-center flex-wrap gap-2">
      <div class="d-flex align-center">
        <VAvatar color="info" variant="tonal" size="32" class="me-3 rounded-lg">
          <VIcon icon="tabler-calendar-stats" size="18" />
        </VAvatar>
        <span class="text-sm font-weight-black uppercase">Auditoría & Desglose Mensual</span>
      </div>
      <VSpacer />
      <span class="text-caption text-medium-emphasis">
        12 Períodos Fiscales
      </span>
    </VCardTitle>

    <VDivider class="opacity-10" />

    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      density="compact"
      hover
      class="border-0 islr-monthly-table"
      :items-per-page="12"
      hide-default-footer
    >
      <!-- Formato de Ingresos Brutos -->
      <template #item.fiscal_total="{ item }">
        <span class="font-weight-bold text-success">
          Bs. {{ formatCurrency(item.fiscal_total) }}
        </span>
      </template>

      <!-- Formato de Costos -->
      <template #item.costs="{ item }">
        <span class="text-medium-emphasis">
          Bs. {{ formatCurrency(item.costs) }}
        </span>
      </template>

      <!-- Formato de Deducciones -->
      <template #item.deductions="{ item }">
        <span class="text-error font-weight-medium">
          - Bs. {{ formatCurrency(item.deductions) }}
        </span>
      </template>

      <!-- Formato de No Deducibles -->
      <template #item.non_deductible="{ item }">
        <span class="text-disabled text-xs">
          Bs. {{ formatCurrency(item.non_deductible) }}
        </span>
      </template>

      <!-- Formato de Base Imponible -->
      <template #item.net_income="{ item }">
        <span class="font-weight-black text-primary">
          Bs. {{ formatCurrency(item.net_income) }}
        </span>
      </template>

      <!-- Formato de Anticipos CE -->
      <template #item.estimated_prepayment="{ item }">
        <span class="font-weight-bold text-secondary">
          Bs. {{ formatCurrency(item.estimated_prepayment) }}
        </span>
      </template>

      <template #no-data>
        <div class="pa-6 text-center text-medium-emphasis">
          <VIcon icon="tabler-calendar-off" size="36" class="mb-2 opacity-50" />
          <div class="text-caption">No se encontraron movimientos registrados en este año fiscal.</div>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>

<style scoped>
.islr-monthly-table :deep(th) {
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
}

.islr-monthly-table :deep(td) {
  font-size: 0.8125rem !important;
}
</style>
