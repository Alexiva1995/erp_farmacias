<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  fiscalData: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "show-detailHistory"]);

const headers = [
  { 
    title: "N° FACTURA", 
    key: "invoice_number", 
    sortable: true,
    value: item => item.invoice_number ? `${item.invoice_number}` : '—',
    cellProps: { class: 'text-sm font-weight-black', style: 'color: #e91e63 !important;' }
  },
  { 
    title: "RAZÓN SOCIAL", 
    key: "business_name", 
    sortable: true, 
    width: "25%"
  },
  { 
    title: "FECHA", 
    key: "invoice_date", 
    sortable: true,
    value: item => {
      const dt = item.invoice_date || item.created_at;
      if (!dt) return '';
      if (typeof dt === 'string' && /^\d{4}-\d{2}-\d{2}/.test(dt.trim())) {
        const parts = dt.trim().split('T')[0].split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
      }
      return new Date(dt).toLocaleDateString("es-VE");
    },
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
    key: "taxable_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(item.taxable_amount ?? item.taxable_base),
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
    title: "SUBTOTAL", 
    key: "subtotal", 
    sortable: false, 
    align: "end",
    value: item => formatCurrency((Number(item.total_amount) || 0) - (Number(item.spe_surcharge_amount) || 0)),
    cellProps: { class: 'text-sm font-weight-bold text-high-emphasis' }
  },
  { 
    title: "IGTF", 
    key: "spe_surcharge_amount", 
    sortable: true, 
    align: "end",
    value: item => formatCurrency(item.spe_surcharge_amount),
    cellProps: { class: 'text-sm font-weight-bold text-error' }
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
</script>

<template>
  <div class="fiscal-table-container">
    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VCard border variant="flat" class="rounded-lg overflow-hidden">
        <VCardTitle class="pa-4 d-flex align-center">
          <div class="pa-2 bg-warning-tonal rounded-lg me-3">
            <VIcon icon="tabler-receipt" size="18" color="warning" />
          </div>
          <span class="text-sm font-weight-black uppercase"
            >Ventas (Débito Fiscal)</span
          >
          <VSpacer />
          <VChip
            color="warning"
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
          :items="props.fiscalData"
          :items-length="props.totalRecords"
          :loading="props.loading"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Estado Vacío -->
          <template #no-data>
            <AppEmptyState
              icon="tabler-receipt-off"
              title="No se encontraron registros de ventas"
              description="Intenta ajustando el rango de fechas o los filtros seleccionados."
              class="py-6"
            />
          </template>

          <template #item.business_name="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="text-sm font-weight-bold text-high-emphasis text-uppercase truncate" style="max-width: 250px;">
                {{ item.business_name || 'N/A' }}
              </span>
              <span class="text-xs text-medium-emphasis text-uppercase">
                {{ item.identification || 'N/A' }}
              </span>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center gap-1">
              <IconBtn
                color="primary"
                title="Ver Detalle"
                :aria-label="`Ver detalle de factura ${item.invoice_number || item.id}`"
                @click="emit('show-detailHistory', item)"
              >
                <VIcon icon="tabler-eye" />
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil (Cards Premium) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="warning" class="mb-2" />
      
      <div v-if="props.fiscalData.length === 0 && !props.loading" class="text-center py-8 text-disabled font-weight-bold uppercase">
        No hay registros de ventas para este período.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.fiscalData"
          :key="item.order_id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3">
                <div class="pa-2 bg-warning-tonal rounded-lg">
                   <VIcon icon="tabler-receipt" size="18" color="warning" />
                </div>
                <div class="d-flex flex-column">
                  <span class="text-primary font-weight-black text-xs uppercase mb-1">Factura</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis leading-tight truncate">
                    {{ item.invoice_number }}
                  </h3>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Cliente Info -->
            <div class="mb-4">
               <div class="d-flex align-center gap-2 mb-1">
                 <span class="text-super-xs font-weight-black text-disabled uppercase">Identificación:</span>
                 <span class="text-xs font-weight-black text-primary uppercase">{{ item.identification }}</span>
              </div>
              <span class="text-sm font-weight-black text-high-emphasis d-block leading-tight text-uppercase mb-1">
                {{ item.business_name }}
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
                <span class="label">IVA Cobrado</span>
                <span class="value font-weight-black text-high-emphasis">{{ formatCurrency(item.iva_amount) }}</span>
              </div>
            </div>

            <div class="pa-3 bg-light rounded-lg border-dashed d-flex align-center justify-space-between">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-medium-emphasis uppercase font-weight-black">Monto Total</span>
                <div class="d-flex align-center gap-1">
                  <span class="text-xs font-weight-bold text-disabled">Bs.</span>
                  <span class="text-h6 font-weight-black text-success leading-none">
                    {{ formatCurrency(item.total_amount) }}
                  </span>
                </div>
              </div>
              <span class="text-super-xs text-disabled uppercase font-weight-black">
                {{ formatDate(item.invoice_date) }}
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

.bg-warning-tonal {
   background-color: rgba(var(--v-theme-warning), 0.1);
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
