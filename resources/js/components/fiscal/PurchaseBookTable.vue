<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  books: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalBooks: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "view-detail",
  "export-excel",
  "export-pdf",
  "create-period",
]);

const tableHeaders = [
  { title: "PERÍODO", key: "period_label", sortable: false, minWidth: "170px" },
  { title: "TIPO / OPS", key: "period_type", sortable: false, align: "center", minWidth: "120px" },
  { title: "TOT. COMPRAS", key: "total_purchases_with_vat", sortable: false, align: "end", minWidth: "135px" },
  { title: "EXENTO", key: "total_exempt_purchases", sortable: false, align: "end", minWidth: "125px" },
  { title: "B.I. (16%)", key: "total_general_taxable_base", sortable: false, align: "end", minWidth: "125px" },
  { title: "CRÉDITO (IVA)", key: "total_general_vat", sortable: false, align: "end", minWidth: "125px" },
  { title: "RET. IVA", key: "total_vat_withheld", sortable: false, align: "end", minWidth: "115px" },
  { title: "ESTADO", key: "status", sortable: false, align: "center", minWidth: "110px" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center", width: "130px", minWidth: "130px" },
];

const formatNumber = (val) => {
  const num = Number(val) || 0;
  return new Intl.NumberFormat("es-VE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
};
</script>

<template>
  <VCard class="rounded-lg">
    <VDataTableServer
      :headers="tableHeaders"
      :items="books"
      :items-length="totalBooks"
      :loading="loading"
      :page="page"
      :items-per-page="itemsPerPage"
      density="compact"
      hover
      class="purchase-books-table elevation-0"
      @update:options="emit('update:options', $event)"
    >
      <!-- Período + Rango de Fechas Integrado Compacto -->
      <template #item.period_label="{ item }">
        <div class="d-flex flex-column py-1">
          <span class="font-weight-bold text-high-emphasis text-body-2">{{ item.period_label }}</span>
          <span class="text-caption text-medium-emphasis">
            {{ item.start_date }} al {{ item.end_date }}
          </span>
        </div>
      </template>

      <!-- Tipo Quincenal/Mensual + Cantidad de Operaciones en Stack -->
      <template #item.period_type="{ item }">
        <div class="d-flex flex-column align-center gap-1 py-1">
          <VChip
            size="x-small"
            variant="tonal"
            :color="item.period_type === 'fortnightly' ? 'info' : 'primary'"
            class="font-weight-medium"
          >
            {{ item.period_type === 'fortnightly' ? 'Quincenal' : 'Mensual' }}
          </VChip>
          <span class="text-caption text-medium-emphasis font-weight-medium">
            {{ item.entries_count }} ops
          </span>
        </div>
      </template>

      <!-- Totales Numéricos Formateados con Tipografía Tabular Compacta -->
      <template #item.total_purchases_with_vat="{ item }">
        <span class="font-weight-bold text-high-emphasis text-body-2 font-tabular">
          {{ formatNumber(item.total_purchases_with_vat) }}
        </span>
      </template>

      <template #item.total_exempt_purchases="{ item }">
        <span class="text-body-2 font-tabular">{{ formatNumber(item.total_exempt_purchases) }}</span>
      </template>

      <template #item.total_general_taxable_base="{ item }">
        <span class="text-body-2 font-tabular">{{ formatNumber(item.total_general_taxable_base) }}</span>
      </template>

      <template #item.total_general_vat="{ item }">
        <span class="text-success font-weight-medium text-body-2 font-tabular">
          {{ formatNumber(item.total_general_vat) }}
        </span>
      </template>

      <template #item.total_vat_withheld="{ item }">
        <span
          class="font-weight-bold text-body-2 font-tabular"
          :class="Number(item.total_vat_withheld) > 0 ? 'text-error' : 'text-medium-emphasis'"
        >
          {{ formatNumber(item.total_vat_withheld) }}
        </span>
      </template>

      <template #item.status="{ item }">
        <VChip
          size="x-small"
          :color="item.is_closed ? 'error' : 'success'"
          variant="tonal"
          class="font-weight-bold"
        >
          <VIcon start size="12">{{ item.is_closed ? 'tabler-lock' : 'tabler-lock-open' }}</VIcon>
          {{ item.is_closed ? 'Cerrado' : 'Abierto' }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-center gap-1">
          <!-- Ver Detalle del Libro -->
          <VTooltip text="Ver Transacciones del Libro" location="top">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                icon
                variant="tonal"
                size="x-small"
                color="primary"
                @click="emit('view-detail', item.id)"
              >
                <VIcon size="16">tabler-eye</VIcon>
              </VBtn>
            </template>
          </VTooltip>

          <!-- Exportar Excel -->
          <VTooltip text="Exportar a Excel" location="top">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                icon
                variant="text"
                size="x-small"
                color="success"
                @click="emit('export-excel', item.id)"
              >
                <VIcon size="16">tabler-file-spreadsheet</VIcon>
              </VBtn>
            </template>
          </VTooltip>

          <!-- Exportar PDF -->
          <VTooltip text="Exportar a PDF" location="top">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                icon
                variant="text"
                size="x-small"
                color="error"
                @click="emit('export-pdf', item.id)"
              >
                <VIcon size="16">tabler-file-type-pdf</VIcon>
              </VBtn>
            </template>
          </VTooltip>
        </div>
      </template>

      <!-- Estado Vacío Estándar -->
      <template #no-data>
        <AppEmptyState
          icon="tabler-book-off"
          title="No se encontraron libros de compras"
          message="No hay registros para los filtros seleccionados o aún no se ha generado un período fiscal."
        >
          <template #actions>
            <VBtn
              color="primary"
              variant="tonal"
              size="small"
              prepend-icon="tabler-calendar-plus"
              @click="emit('create-period')"
            >
              Generar Primer Período
            </VBtn>
          </template>
        </AppEmptyState>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
.font-tabular {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.purchase-books-table :deep(th) {
  font-weight: 700 !important;
  text-transform: uppercase;
  font-size: 0.72rem !important;
  letter-spacing: 0.04em;
}
</style>
