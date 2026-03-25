<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  fiscalData: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const { mobile } = useDisplay();

const headers = [
  { title: "#Orden", key: "order_id", sortable: true },
  { title: "#Factura", key: "invoice_number", sortable: true },
  { title: "Cliente / Razón Social", key: "business_name", sortable: true, width: "30%" },
  { title: "Exento", key: "exempt_amount", sortable: true, align: "end" },
  { title: "Base Imponible", key: "taxable_base", sortable: true, align: "end" },
  { title: "IVA", key: "iva_amount", sortable: true, align: "end" },
  { title: "Total", key: "total_amount", sortable: true, align: "end" },
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

const getInitials = (name) => {
  if (!name) return "C";
  return name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
};
</script>

<template>
  <div class="mb-6">
    <!-- Vista de Escritorio -->
    <VCard v-if="!mobile" class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardTitle class="pa-4 d-flex align-center">
        <VAvatar color="warning" variant="tonal" size="32" class="me-3 rounded-lg">
          <VIcon icon="tabler-receipt" size="18" />
        </VAvatar>
        <span class="text-sm font-weight-black uppercase">Ventas (Débito Fiscal)</span>
        <VSpacer />
        <VChip color="warning" size="x-small" variant="tonal" class="font-weight-black rounded">
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
        <template #item.order_id="{ item }">
          <span class="font-weight-black text-primary">#{{ item.order_id }}</span>
        </template>

        <template #item.invoice_number="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-black text-primary">{{ item.invoice_number }}</span>
            <span class="text-super-xs text-disabled">{{ formatDate(item.invoice_date) }}</span>
          </div>
        </template>

        <template #item.business_name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar color="warning" variant="tonal" size="30" class="rounded-lg">
              <span class="text-super-xs font-weight-black">{{ getInitials(item.business_name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column truncate" style="max-width: 250px;">
              <span class="text-sm font-weight-bold text-high-emphasis text-capitalize truncate">{{ item.business_name }}</span>
              <span class="text-super-xs text-primary font-weight-black uppercase truncate">{{ item.identification }}</span>
            </div>
          </div>
        </template>

        <template #item.exempt_amount="{ item }">
          <span class="text-xs font-weight-medium" :class="Number(item.exempt_amount) > 0 ? 'text-info' : 'text-disabled'">
            {{ formatCurrency(item.exempt_amount) }}
          </span>
        </template>

        <template #item.taxable_base="{ item }">
          <span class="text-xs font-weight-medium">{{ formatCurrency(item.taxable_base) }}</span>
        </template>

        <template #item.iva_amount="{ item }">
          <div class="d-flex flex-column align-end">
            <span class="text-xs font-weight-black text-success">{{ formatCurrency(item.iva_amount) }}</span>
            <span v-if="item.spe" class="text-super-xs text-warning font-weight-black">+SPE</span>
          </div>
        </template>

        <template #item.total_amount="{ item }">
          <span class="text-sm font-weight-black text-warning-darken-2">{{ formatCurrency(item.total_amount) }}</span>
        </template>

        <template #bottom>
          <VDivider class="opacity-10" />
          <div class="d-flex align-center justify-space-between pa-4">
            <VPagination
              :model-value="props.page"
              :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
              size="small"
              class="premium-pagination"
              @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
            />
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil (Cards) -->
    <div v-else class="d-flex flex-column gap-4">
      <div v-if="props.loading" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="warning" />
      </div>

      <template v-else-if="props.fiscalData.length > 0">
        <VCard
          v-for="item in props.fiscalData"
          :key="item.order_id"
          class="rounded-lg border shadow-sm premium-card overflow-hidden"
        >
          <div class="premium-card-decoration bg-warning-opacity"></div>
          
          <VCardText class="pa-5">
            <!-- Cabecera Móvil -->
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <VAvatar color="warning" variant="tonal" size="38" class="rounded-lg shadow-sm">
                  <VIcon icon="tabler-receipt" size="18" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-xs font-weight-black text-disabled uppercase leading-tight">Factura #</span>
                  <span class="text-sm font-weight-black text-primary leading-tight">{{ item.invoice_number }}</span>
                </div>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-xs font-weight-black text-disabled uppercase leading-tight">Orden</span>
                <span class="text-xs font-weight-bold leading-tight">#{{ item.order_id }}</span>
              </div>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <!-- Info Cliente -->
            <div class="mb-4">
              <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Cliente / RIF</span>
              <span class="text-sm font-weight-bold text-high-emphasis d-block leading-tight text-capitalize mb-1">{{ item.business_name }}</span>
              <span class="text-xs text-disabled leading-tight">{{ item.identification }}</span>
            </div>

            <!-- Stats IVA -->
            <div class="d-flex gap-3 mb-4">
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-info-opacity">
                <span class="text-super-xs text-info font-weight-bold uppercase d-block mb-1">Exento</span>
                <span class="text-sm font-weight-black">{{ formatCurrency(item.exempt_amount) }}</span>
              </div>
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-success-opacity">
                <span class="text-super-xs text-success font-weight-bold uppercase d-block mb-1">IVA Cobrado</span>
                <span class="text-sm font-weight-black">{{ formatCurrency(item.iva_amount) }}</span>
              </div>
            </div>

            <!-- Total -->
            <div class="d-flex align-center justify-space-between bg-surface-variant-opacity-2 pa-3 rounded-lg">
              <span class="text-xs font-weight-black uppercase">Monto Total</span>
              <span class="text-h6 font-weight-black text-warning-darken-2">Bs. {{ formatCurrency(item.total_amount) }}</span>
            </div>
          </VCardText>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            rounded="circle"
            class="premium-pagination"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>

      <VAlert v-else type="info" variant="tonal" class="rounded-lg">
        No hay registros de ventas para este período.
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-tight {
  line-height: 1.25;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.premium-card {
  position: relative;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.premium-card-decoration {
  position: absolute;
  top: 0;
  right: 0;
  width: 80px;
  height: 80px;
  border-radius: 0 0 0 100%;
}

.bg-warning-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-warning), 0.1) 0%, transparent 100%);
}

.bg-info-opacity {
  background-color: rgba(var(--v-theme-info), 0.05) !important;
}

.bg-success-opacity {
  background-color: rgba(var(--v-theme-success), 0.05) !important;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.premium-pagination :deep(.v-btn) {
  background-color: white !important;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-pagination :deep(.v-pagination__item--active .v-btn) {
  background: rgb(var(--v-theme-warning)) !important;
  color: white !important;
  border: 0 !important;
}
</style>
