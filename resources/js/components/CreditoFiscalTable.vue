<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  expensesData: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

const { mobile } = useDisplay();

const headers = [
  { title: "Proveedor / Gasto", key: "supplier_name", sortable: true, width: "25%" },
  { title: "#Factura", key: "invoice_number", sortable: true },
  { title: "RIF / Razón Social", key: "supplier_rif", sortable: true, width: "25%" },
  { title: "Exento", key: "exempt_amount", sortable: true, align: "end" },
  { title: "Base Imponible", key: "taxable_base", sortable: true, align: "end" },
  { title: "IVA", key: "iva_amount", sortable: true, align: "end" },
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
  <div>
    <!-- Vista de Escritorio -->
    <VCard v-if="!mobile" class="rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
      <VCardTitle class="pa-4 d-flex align-center">
        <VAvatar color="info" variant="tonal" size="32" class="me-3 rounded-lg">
          <VIcon icon="tabler-receipt-2" size="18" />
        </VAvatar>
        <span class="text-sm font-weight-black uppercase">Gastos (Crédito Fiscal)</span>
        <VSpacer />
        <VChip color="info" size="x-small" variant="tonal" class="font-weight-black rounded">
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
            <span class="text-xs font-weight-black text-high-emphasis truncate" style="max-width: 200px;">
              {{ item.supplier_name || "N/A" }}
            </span>
            <VChip
              v-if="item.category_name"
              :color="getCategoryChipColor(item.category_name)"
              variant="tonal"
              size="x-small"
              class="mt-1 align-self-start font-weight-black rounded"
            >
              {{ item.category_name }}
            </VChip>
          </div>
        </template>

        <template #item.invoice_number="{ item }">
          <div class="d-flex flex-column">
            <span class="text-xs font-weight-bold">{{ item.invoice_number || "S/N" }}</span>
            <span class="text-super-xs text-disabled">{{ formatDate(item.expense_date) }}</span>
          </div>
        </template>

        <template #item.supplier_rif="{ item }">
          <div class="d-flex flex-column truncate" style="max-width: 200px;">
            <span class="text-xs font-weight-medium">{{ item.supplier_rif || "N/A" }}</span>
            <span class="text-super-xs text-disabled truncate text-capitalize">{{ item.supplier_business_name || item.supplier_name }}</span>
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
            <span v-if="item.is_deductible" class="text-super-xs text-success font-weight-bold uppercase">Deducible</span>
          </div>
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
        <VProgressCircular indeterminate color="info" />
      </div>

      <template v-else-if="props.expensesData.length > 0">
        <VCard
          v-for="item in props.expensesData"
          :key="item.invoice_number"
          class="rounded-xl border-0 shadow-md premium-card overflow-hidden"
        >
          <div class="premium-card-decoration bg-info-opacity"></div>
          
          <VCardText class="pa-5">
            <!-- Cabecera Móvil -->
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <VAvatar color="info" variant="tonal" size="38" class="rounded-lg shadow-sm">
                  <VIcon icon="tabler-receipt-2" size="18" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-xs font-weight-black text-disabled uppercase leading-tight">Gasto / Factura</span>
                  <span class="text-sm font-weight-black text-primary leading-tight truncate" style="max-width: 140px;">{{ item.invoice_number || 'S/N' }}</span>
                </div>
              </div>
              <VChip
                v-if="item.category_name"
                :color="getCategoryChipColor(item.category_name)"
                size="x-small"
                variant="flat"
                class="font-weight-black rounded"
              >
                {{ item.category_name }}
              </VChip>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <!-- Info Proveedor -->
            <div class="mb-4">
              <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Proveedor / Razón Social</span>
              <span class="text-sm font-weight-bold text-high-emphasis d-block leading-tight text-capitalize mb-1">{{ item.supplier_name }}</span>
              <span class="text-xs text-disabled leading-tight">{{ item.supplier_rif || 'RIF No disponible' }}</span>
            </div>

            <!-- Stats IVA -->
            <div class="d-flex gap-3 mb-4">
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block mb-1">Base Imponible</span>
                <span class="text-sm font-weight-black">{{ formatCurrency(item.taxable_base) }}</span>
              </div>
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-success-opacity">
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-super-xs text-success font-weight-bold uppercase">IVA Pagado</span>
                  <VIcon v-if="item.is_deductible" icon="tabler-shield-check" size="14" color="success" />
                </div>
                <span class="text-sm font-weight-black text-success">{{ formatCurrency(item.iva_amount) }}</span>
              </div>
            </div>

            <!-- Total Gasto -->
            <div class="d-flex align-center justify-space-between bg-info-opacity-2 pa-3 rounded-lg">
              <span class="text-xs font-weight-black uppercase text-info">Total del Gasto</span>
              <span class="text-h6 font-weight-black text-info-darken-2">Bs. {{ formatCurrency(Number(item.taxable_base) + Number(item.iva_amount) + Number(item.exempt_amount)) }}</span>
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

      <VAlert v-else type="info" variant="tonal" class="rounded-xl">
        No se encontraron gastos con IVA en este período.
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(.v-data-table-header th) {
  height: 44px !important;
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
  color: rgba(var(--v-theme-on-surface), 0.5) !important;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
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

.bg-info-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-info), 0.1) 0%, transparent 100%);
}

.bg-info-opacity-2 {
  background-color: rgba(var(--v-theme-info), 0.08) !important;
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
  background: rgb(var(--v-theme-info)) !important;
  color: white !important;
  border: 0 !important;
}
</style>
