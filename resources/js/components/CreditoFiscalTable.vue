<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  expensesData: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const formatCurrency = (amount) => {
  const number = parseFloat(amount) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(number);
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  if (typeof dateString === "string" && /^\d{4}-\d{2}-\d{2}/.test(dateString.trim())) {
    const parts = dateString.trim().split("T")[0].split("-");
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return new Date(dateString).toLocaleDateString("es-VE");
};

const headers = [
  { 
    title: "N° FACTURA", 
    key: "invoice_number", 
    sortable: true,
    value: item => item.invoice_number ? `${item.invoice_number}` : '—',
    cellProps: { class: 'text-sm font-weight-black', style: 'color: #e91e63 !important;' }
  },
  { 
    title: "PROVEEDOR / RAZÓN SOCIAL", 
    key: "supplier_name", 
    sortable: true, 
    width: "25%"
  },
  { 
    title: "FECHA", 
    key: "expense_date", 
    sortable: true,
    value: item => formatDate(item.expense_date || item.created_at),
    cellProps: { class: 'text-sm text-medium-emphasis' }
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
    title: "BASE", 
    key: "taxable_base", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(item.taxable_base),
    cellProps: { class: 'text-sm text-medium-emphasis' }
  },
  { 
    title: "SUBTOTAL", 
    key: "subtotal", 
    sortable: false, 
    align: "end",
    value: item => formatCurrency((Number(item.taxable_base) || 0) + (Number(item.exempt_amount) || 0)),
    cellProps: { class: 'text-sm font-weight-bold text-high-emphasis' }
  },
  { 
    title: "TOTAL", 
    key: "total_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(Number(item.taxable_base) + Number(item.iva_amount) + Number(item.exempt_amount)),
    cellProps: { class: 'text-sm font-weight-black text-high-emphasis' }
  },
  { 
    title: "IVA", 
    key: "iva_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(item.iva_amount),
    cellProps: { class: 'text-sm font-weight-black text-success iva-highlight-cell' }
  },
];

const getCategoryChipColor = (categoryName) => {
  if (!categoryName) return "primary";
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
          <span class="text-sm font-weight-black uppercase">
            Gastos (Crédito Fiscal)
          </span>
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
          <!-- Estado Vacío -->
          <template #no-data>
            <AppEmptyState
              icon="tabler-receipt-off"
              title="No se encontraron gastos con IVA"
              description="Intenta ajustando el rango de fechas o los filtros seleccionados."
              class="py-6"
            />
          </template>

          <template #item.supplier_name="{ item }">
            <div class="d-flex flex-column py-1">
              <span
                class="text-sm font-weight-bold text-high-emphasis text-uppercase truncate"
                style="max-width: 300px"
              >
                {{ item.supplier_name || "N/A" }}
              </span>
              <div class="d-flex align-center gap-2">
                <span class="text-xs text-medium-emphasis text-uppercase">
                  {{ item.supplier_rif || "N/A" }}
                </span>
                <VChip
                  v-if="item.category_name"
                  :color="getCategoryChipColor(item.category_name)"
                  variant="tonal"
                  size="x-small"
                  class="font-weight-bold rounded"
                >
                  {{ item.category_name }}
                </VChip>
              </div>
            </div>
          </template>

          <template #item.iva_amount="{ item }">
            <span class="text-sm font-weight-black text-success">
              Bs. {{ formatCurrency(item.iva_amount) }}
            </span>
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
          :key="item.invoice_number || item.id"
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
                  <span class="text-primary font-weight-black text-xs uppercase mb-1">N° Factura</span>
                  <h3 class="text-sm font-weight-black leading-tight truncate" style="color: #e91e63;">
                    {{ item.invoice_number || item.id || 'S/N' }}
                  </h3>
                </div>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-sm font-weight-black text-success leading-none mb-1">
                  Bs. {{ formatCurrency(item.iva_amount) }}
                </span>
                <span class="text-super-xs font-weight-black text-disabled uppercase">IVA Crédito</span>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Proveedor Info -->
            <div class="mb-4">
              <div class="d-flex align-center gap-2 mb-1">
                 <span class="text-super-xs font-weight-black text-disabled uppercase">RIF:</span>
                 <span class="text-xs font-weight-black text-primary uppercase">{{ item.supplier_rif || 'N/A' }}</span>
                 <VChip
                   v-if="item.category_name"
                   :color="getCategoryChipColor(item.category_name)"
                   size="x-small"
                   variant="tonal"
                   class="font-weight-bold rounded ms-auto"
                 >
                   {{ item.category_name }}
                 </VChip>
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
                <span class="label">Base</span>
                <span class="value font-weight-black text-high-emphasis">{{ formatCurrency(item.taxable_base) }}</span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Subtotal</span>
                <span class="value font-weight-black text-high-emphasis">{{ formatCurrency((Number(item.taxable_base) || 0) + (Number(item.exempt_amount) || 0)) }}</span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Total</span>
                <span class="value font-weight-black text-high-emphasis">{{ formatCurrency(Number(item.taxable_base) + Number(item.iva_amount) + Number(item.exempt_amount)) }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Fecha</span>
                <span class="value font-weight-black text-disabled uppercase">{{ formatDate(item.expense_date) }}</span>
              </div>
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
</style>
