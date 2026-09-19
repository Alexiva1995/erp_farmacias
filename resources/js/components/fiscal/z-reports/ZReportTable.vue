<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  reports: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalReports: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "view-detail"]);

const headers = [
  {
    title: "REPORTE Z",
    key: "report_number",
    sortable: true,
    value: (item) => item.report_number_padded || `Z${String(item.report_number).padStart(6, "0")}`,
    cellProps: { class: "text-sm font-weight-black text-primary" },
  },
  {
    title: "FECHA",
    key: "report_date",
    sortable: true,
    value: (item) => {
      const dt = item.report_date;
      if (!dt) return "";
      if (typeof dt === "string" && /^\d{4}-\d{2}-\d{2}/.test(dt.trim())) {
        const parts = dt.trim().split("T")[0].split("-");
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
      }
      return new Date(dt).toLocaleDateString("es-VE");
    },
    cellProps: { class: "text-sm text-medium-emphasis font-weight-medium" },
  },
  {
    title: "FACTURAS",
    key: "invoices_count",
    sortable: true,
    align: "center",
    value: (item) => `${item.invoices_count || 0} Docs.`,
    cellProps: { class: "text-sm font-weight-bold" },
  },
  {
    title: "EXENTO (BS.)",
    key: "exempt_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.exempt_amount),
    cellProps: { class: "text-sm text-medium-emphasis" },
  },
  {
    title: "BIG 16.00% (BS.)",
    key: "base_16_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.base_16_amount),
    cellProps: { class: "text-sm text-medium-emphasis font-weight-medium" },
  },
  {
    title: "BASE IGTF / SPE (BS.)",
    key: "igtf_base_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.igtf_base_amount),
    cellProps: { class: "text-sm text-medium-emphasis" },
  },
  {
    title: "IVA G 16% (BS.)",
    key: "iva_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.iva_amount),
    cellProps: { class: "text-sm text-warning font-weight-medium" },
  },
  {
    title: "IGTF 3% (BS.)",
    key: "igtf_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.igtf_amount),
    cellProps: { class: "text-sm text-medium-emphasis" },
  },
  {
    title: "TOTAL BS.",
    key: "total_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.total_amount),
    cellProps: { class: "text-sm font-weight-black text-success" },
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
  <div class="z-report-table-container">
    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VCard border variant="flat" class="rounded-lg overflow-hidden">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.reports"
          :items-length="props.totalReports"
          :loading="props.loading"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Estado Vacío -->
          <template #no-data>
            <AppEmptyState
              icon="tabler-receipt-off"
              title="No se encontraron reportes Z"
              description="Ajusta los filtros de búsqueda o el rango de fechas para visualizar los cortes Z."
              class="py-6"
            />
          </template>

          <template #item.report_number="{ item }">
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-file-analytics" size="18" color="primary" />
              <span class="font-weight-black text-primary">
                {{ item.report_number_padded || `Z${String(item.report_number).padStart(6, "0")}` }}
              </span>
            </div>
          </template>

          <template #item.invoices_count="{ item }">
            <VChip
              size="small"
              :color="item.invoices_count > 0 ? 'info' : 'secondary'"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ item.invoices_count }} {{ item.invoices_count === 1 ? 'Doc' : 'Docs' }}
            </VChip>
          </template>

          <template #item.total_amount="{ item }">
            <span class="font-weight-black text-success">
              Bs. {{ formatCurrency(item.total_amount) }}
            </span>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-center gap-1">
              <VTooltip location="top" text="Ver Ticket Corte Z">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon
                    size="small"
                    variant="text"
                    color="primary"
                    @click="emit('view-detail', item)"
                  >
                    <VIcon icon="tabler-printer" size="20" />
                  </VBtn>
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil Responsive -->
    <div class="d-block d-md-none">
      <div v-if="props.loading" class="d-flex flex-column gap-3">
        <VCard v-for="i in 3" :key="i" border variant="flat" class="pa-4 rounded-lg">
          <VSkeletonLoader type="list-item-two-line, text" />
        </VCard>
      </div>

      <div v-else-if="props.reports.length === 0">
        <VCard border variant="flat" class="pa-6 rounded-lg text-center">
          <AppEmptyState
            icon="tabler-receipt-off"
            title="Sin Reportes Z"
            description="No hay registros para este período."
          />
        </VCard>
      </div>

      <div v-else class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.reports"
          :key="item.id"
          border
          variant="flat"
          class="pa-4 rounded-lg shadow-sm"
        >
          <div class="d-flex justify-space-between align-center mb-2 border-b pb-2">
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-file-analytics" color="primary" size="18" />
              <span class="text-subtitle-2 font-weight-black text-primary">
                {{ item.report_number_padded || `Z${String(item.report_number).padStart(6, "0")}` }}
              </span>
            </div>
            <VChip size="x-small" color="info" variant="tonal" class="font-weight-bold">
              {{ item.invoices_count }} Docs
            </VChip>
          </div>

          <div class="d-flex flex-column gap-1 text-sm mb-3">
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Fecha:</span>
              <span class="font-weight-medium">{{ item.report_date }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Exento:</span>
              <span>Bs. {{ formatCurrency(item.exempt_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Big 16%:</span>
              <span>Bs. {{ formatCurrency(item.base_16_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">IVA G (16%):</span>
              <span class="text-warning">Bs. {{ formatCurrency(item.iva_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Base IGTF / SPE:</span>
              <span>Bs. {{ formatCurrency(item.igtf_base_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">IGTF (3%):</span>
              <span>Bs. {{ formatCurrency(item.igtf_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between border-t pt-1 font-weight-bold">
              <span>Total Bs:</span>
              <span class="text-success font-weight-black">Bs. {{ formatCurrency(item.total_amount) }}</span>
            </div>
          </div>

          <VBtn
            block
            variant="tonal"
            color="primary"
            size="small"
            prepend-icon="tabler-printer"
            @click="emit('view-detail', item)"
          >
            Ver Ticket Corte Z
          </VBtn>
        </VCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(thead th) {
  background-color: rgba(var(--v-theme-surface), 0.8) !important;
  font-weight: 800 !important;
  font-size: 0.75rem !important;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.premium-table :deep(tbody tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}
</style>
