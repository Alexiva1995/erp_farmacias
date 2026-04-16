<script setup>
const props = defineProps({
  histories: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalHistories: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "show-detailHistory"]);

const headers = [
  { 
    title: "ID", 
    key: "fiscal_id", 
    sortable: true,
    value: item => item.fiscal_id ? `#${item.fiscal_id}` : '',
    cellProps: { class: 'text-sm font-weight-black text-primary' }
  },
  { 
    title: "IDENTIFICACIÓN", 
    key: "identification", 
    sortable: true,
    value: item => (item.identification || 'N/A').toUpperCase(),
    cellProps: { class: 'text-sm text-medium-emphasis' }
  },
  { 
    title: "RAZÓN SOCIAL", 
    key: "business_name", 
    sortable: true, 
    width: "35%",
    value: item => (item.business_name || 'N/A').toUpperCase(),
    cellProps: { class: 'text-sm text-medium-emphasis text-uppercase truncate' }
  },
  { 
    title: "FECHA", 
    key: "invoice_date", 
    sortable: true,
    value: item => item.invoice_date ? new Date(item.invoice_date).toLocaleDateString("es-VE") : '',
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
    title: "IVA", 
    key: "iva_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(item.iva_amount),
    cellProps: { class: 'text-sm text-medium-emphasis' }
  },
  { 
    title: "TOTAL", 
    key: "total_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(item.total_amount),
    cellProps: { class: 'text-sm font-weight-black text-high-emphasis' }
  },
  { title: "ACCIÓN", key: "actions", sortable: false, align: "center" },
];

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};
</script>

<template>
  <div class="history-table-container">
    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VCard border variant="flat" class="rounded-lg overflow-hidden">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.histories"
          :items-length="props.totalHistories"
          :loading="props.loading"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
        >
          <template #item.actions="{ item }">
            <div class="d-flex justify-center">
              <VBtn
                icon="tabler-eye"
                color="primary"
                variant="tonal"
                size="small"
                rounded="circle"
                @click="emit('show-detailHistory', item)"
              >
                <VIcon size="18" icon="tabler-eye" />
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil (Premium Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.histories.length === 0 && !props.loading" class="text-center py-8 text-disabled font-weight-bold uppercase">
        No se encontraron registros fiscales.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.histories"
          :key="item.id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
          @click="emit('show-detailHistory', item)"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3">
                <div class="pa-2 bg-primary-tonal rounded-lg">
                   <VIcon icon="tabler-receipt-2" size="20" color="primary" />
                </div>
                <div class="d-flex flex-column">
                  <span class="text-primary font-weight-black text-xs uppercase mb-1">Documento</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis leading-tight truncate">
                    #{{ item.invoice_number }}
                  </h3>
                </div>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-sm font-weight-black text-success leading-none mb-1">
                  {{ formatCurrency(item.total_amount) }}
                </span>
                <span class="text-super-xs font-weight-black text-disabled uppercase">Monto Total</span>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="mb-4">
              <div class="d-flex align-center gap-2 mb-1">
                 <span class="text-super-xs font-weight-black text-disabled uppercase">Identificación:</span>
                 <span class="text-xs font-weight-black text-primary">{{ item.identification || 'N/A' }}</span>
              </div>
              <span class="text-sm font-weight-black text-high-emphasis d-block leading-tight text-uppercase mb-1">
                {{ item.business_name }}
              </span>
              <span class="text-xs text-medium-emphasis leading-tight truncate-2-lines uppercase">{{ item.address || "Dirección no registrada" }}</span>
            </div>

            <div class="d-grid mobile-grid gap-3 mb-1">
              <div class="stat-box">
                <span class="label">Exento</span>
                <span class="value font-weight-black text-high-emphasis">{{ formatCurrency(item.exempt_amount) }}</span>
              </div>
              <div class="stat-box text-center">
                <span class="label">IVA</span>
                <span class="value font-weight-black text-high-emphasis">{{ formatCurrency(item.iva_amount) }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Fecha</span>
                <span class="value font-weight-black text-disabled uppercase">{{ item.invoice_date }}</span>
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
            :total-items="props.totalHistories"
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

.bg-primary-tonal {
   background-color: rgba(var(--v-theme-primary), 0.1);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.truncate-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
  cursor: pointer;
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

.bg-light {
  background-color: #f8fafc !important;
}
</style>
