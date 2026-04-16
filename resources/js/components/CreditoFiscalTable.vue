<script setup>
const props = defineProps({
  expensesData: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const headers = [
  { 
    title: "ID", 
    key: "invoice_number", 
    sortable: true,
    value: item => item.invoice_number ? `#${item.invoice_number}` : '',
    cellProps: { class: 'text-sm font-weight-black text-primary' }
  },
  { 
    title: "RIF / IDENTIFICACIÓN", 
    key: "supplier_rif", 
    sortable: true,
    value: item => (item.supplier_rif || 'N/A').toUpperCase(),
    cellProps: { class: 'text-sm text-medium-emphasis' }
  },
  {
    title: "PROVEEDOR / RAZÓN SOCIAL",
    key: "supplier_name",
    sortable: true,
    width: "35%",
    value: item => (item.supplier_name || 'N/A').toUpperCase(),
    cellProps: { class: 'text-sm text-medium-emphasis text-uppercase truncate' }
  },
  { 
    title: "EXENTO", 
    key: "exempt_amount", 
    sortable: true, 
    align: "end", 
    value: item => formatCurrency(item.exempt_amount),
    cellProps: { class: 'text-sm text-medium-emphasis' }
  },
  {
    title: "BASE IMPONIBLE",
    key: "taxable_base",
    sortable: true,
    align: "end",
    value: item => formatCurrency(item.taxable_base),
    cellProps: { class: 'text-sm text-medium-emphasis' }
  },
  { 
    title: "IVA", 
    key: "iva_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(item.iva_amount),
    cellProps: { class: 'text-sm font-weight-black text-medium-emphasis' }
  },
  { 
    title: "TOTAL", 
    key: "total_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(Number(item.taxable_base) + Number(item.iva_amount) + Number(item.exempt_amount)),
    cellProps: { class: 'text-sm font-weight-black text-info text-high-emphasis' }
  }
];

const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(number);
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("es-VE", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
};

const getCategoryChipColor = (categoryName) => {
  const colors = ["primary", "secondary", "success", "info", "warning"];
  const hash = categoryName.split("").reduce((a, b) => a + b.charCodeAt(0), 0);
  return colors[hash % colors.length];
};
</script>

<template>
  <div class="fiscal-table-container">
    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VCard border variant="flat" class="rounded-lg overflow-hidden">
        <VCardTitle class="pa-4 d-flex align-center">
          <div class="pa-2 bg-info-tonal rounded-lg me-3">
            <VIcon icon="tabler-receipt-2" size="18" color="info" />
          </div>
          <span class="text-sm font-weight-black uppercase"
            >Gastos (Crédito Fiscal)</span
          >
          <VSpacer />
          <VChip
            color="info"
            size="x-small"
            variant="tonal"
            class="font-weight-black rounded"
          >
            {{ totalRecords }} DOCUMENTOS
          </VChip>
        </VCardTitle>

        <VDivider class="opacity-10" />

        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.expensesData"
          :items-length="props.totalRecords"
          :loading="props.loading"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
        >

          <template #item.supplier_name="{ item }">
            <div class="d-flex flex-column py-2">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase truncate"
                style="max-width: 350px"
              >
                {{ item.supplier_name || "N/A" }}
              </span>
              <div class="d-flex align-center gap-2 mt-1">
                 <VChip
                  v-if="item.category_name"
                  :color="getCategoryChipColor(item.category_name)"
                  variant="tonal"
                  size="x-small"
                  class="font-weight-black rounded"
                >
                  {{ item.category_name }}
                </VChip>
                <span class="text-super-xs text-disabled uppercase font-weight-medium">
                  {{ formatDate(item.expense_date) }}
                </span>
              </div>
            </div>
          </template>

          <template #item.iva_amount="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="text-sm font-weight-black text-high-emphasis">{{
                formatCurrency(item.iva_amount)
              }}</span>
              <span
                v-if="item.is_deductible"
                class="text-super-xs text-success font-weight-black uppercase"
                >Deducible</span
              >
            </div>
          </template>

          <template #item.total_amount="{ item }">
            <span class="text-sm font-weight-black text-info">{{
              formatCurrency(Number(item.taxable_base) + Number(item.iva_amount) + Number(item.exempt_amount))
            }}</span>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil (Cards Premium) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="info" class="mb-2" />
      
      <div v-if="props.expensesData.length === 0 && !props.loading" class="text-center py-8 text-disabled font-weight-bold uppercase">
        No se encontraron gastos con IVA en este período.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.expensesData"
          :key="item.invoice_number"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3">
                <div class="pa-2 bg-info-tonal rounded-lg">
                   <VIcon icon="tabler-receipt-2" size="18" color="info" />
                </div>
                <div class="d-flex flex-column">
                  <span class="text-primary font-weight-black text-xs uppercase mb-1">Factura</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis leading-tight truncate">
                    {{ item.invoice_number || 'S/N' }}
                  </h3>
                </div>
              </div>
              <VChip
                v-if="item.category_name"
                :color="getCategoryChipColor(item.category_name)"
                size="x-small"
                variant="flat"
                class="font-weight-black rounded"
              >
                {{ item.category_name.toUpperCase() }}
              </VChip>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Proveedor Info -->
            <div class="mb-4">
              <div class="d-flex align-center gap-2 mb-1">
                 <span class="text-super-xs font-weight-black text-disabled uppercase">RIF:</span>
                 <span class="text-xs font-weight-black text-primary uppercase">{{ item.supplier_rif || 'N/A' }}</span>
              </div>
              <span class="text-sm font-weight-black text-high-emphasis d-block leading-tight text-uppercase mb-1">
                {{ item.supplier_name }}
              </span>
            </div>

            <div class="d-grid mobile-grid gap-3 mb-4">
              <div class="stat-box">
                <span class="label">Exento</span>
                <span class="value font-weight-black text-high-emphasis">
                  {{ formatCurrency(item.exempt_amount) }}
                </span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Base Imp.</span>
                <span class="value font-weight-black text-high-emphasis">{{ formatCurrency(item.taxable_base) }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">IVA Pagado</span>
                <div class="d-flex align-center justify-end gap-1">
                   <VIcon v-if="item.is_deductible" icon="tabler-shield-check" size="12" color="success" />
                   <span class="value font-weight-black text-high-emphasis">{{ formatCurrency(item.iva_amount) }}</span>
                </div>
              </div>
            </div>

            <div class="pa-3 bg-light rounded-lg border-dashed d-flex align-center justify-space-between">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-medium-emphasis uppercase font-weight-black text-info">Total del Gasto</span>
                <div class="d-flex align-center gap-1">
                  <span class="text-xs font-weight-bold text-disabled">Bs.</span>
                  <span class="text-h6 font-weight-black text-info-darken-2 leading-none">
                    {{ formatCurrency(Number(item.taxable_base) + Number(item.iva_amount) + Number(item.exempt_amount)) }}
                  </span>
                </div>
              </div>
              <span class="text-super-xs text-disabled uppercase font-weight-black">
                {{ formatDate(item.expense_date) }}
              </span>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile Pagination -->
      <div class="d-flex justify-center mt-4 pb-2">
         <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalRecords"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(var(--v-theme-on-surface), 0.9) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 10px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.06) !important;
  color: rgba(var(--v-theme-on-surface), 0.8) !important;
}

.premium-table :deep(.v-data-table__tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

/* Eliminar bordes verticales */
.premium-table :deep(table) {
  border-spacing: 0;
  border-collapse: collapse;
}

.premium-table :deep(.v-data-table__td),
.premium-table :deep(.v-data-table-header th) {
  border-inline: none !important;
}

.bg-info-tonal {
   background-color: rgba(var(--v-theme-info), 0.1);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1;
}

.leading-tight {
  line-height: 1.25;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.mobile-grid {
  display: grid;
  align-items: center;
  grid-template-columns: 1fr 1fr 1fr;
}

.stat-box .label {
  display: block;
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-size: 0.6rem;
  font-weight: 900;
  margin-block-end: 2px;
  text-transform: uppercase;
}

.stat-box .value {
  font-size: 0.75rem;
  font-weight: 800;
}

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}
</style>
