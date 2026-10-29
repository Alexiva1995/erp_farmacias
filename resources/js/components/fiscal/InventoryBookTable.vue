<script setup>
import { computed } from "vue";

const props = defineProps({
  books: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalItems: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  itemsPerPage: { type: Number, default: 15 },
});

const emit = defineEmits([
  "update:options",
  "view-detail",
  "export-excel",
  "export-pdf",
  "export-acta",
]);

const headers = [
  { title: "Período", key: "period_label", sortable: false },
  { title: "Rango de Fechas", key: "dates", sortable: false },
  { title: "Total Unidades", key: "total_final_units", align: "end", sortable: false },
  { title: "Valor Inventario (Bs)", key: "total_final_inventory_value_bs", align: "end", sortable: false },
  { title: "Retiros / Débito IVA", key: "withdrawals_vat", align: "end", sortable: false },
  { title: "Estado", key: "status", align: "center", sortable: false },
  { title: "Acciones", key: "actions", align: "center", sortable: false },
];

const formatCurrency = (val) => {
  return Number(val || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatUnits = (val) => {
  return Number(val || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
};
</script>

<template>
  <VCard class="rounded-lg">
    <VDataTableServer
      :headers="headers"
      :items="books"
      :items-length="totalItems"
      :loading="loading"
      :page="page"
      :items-per-page="itemsPerPage"
      @update:options="emit('update:options', $event)"
    >
      <!-- Slot Período -->
      <template #item.period_label="{ item }">
        <div class="d-flex align-center gap-2">
          <VAvatar
            :color="item.is_closed ? 'success' : 'primary'"
            variant="tonal"
            size="32"
          >
            <VIcon :icon="item.is_closed ? 'tabler-lock' : 'tabler-lock-open'" size="18" />
          </VAvatar>
          <div>
            <div class="font-weight-bold text-subtitle-2">{{ item.period_label }}</div>
            <span class="text-caption text-medium-emphasis">Art. 177 LISR</span>
          </div>
        </div>
      </template>

      <!-- Slot Fechas -->
      <template #item.dates="{ item }">
        <span class="text-caption">{{ item.start_date }} al {{ item.end_date }}</span>
      </template>

      <!-- Slot Total Unidades -->
      <template #item.total_final_units="{ item }">
        <span class="font-weight-bold">{{ formatUnits(item.total_final_units) }}</span>
      </template>

      <!-- Slot Valor Total en Bs -->
      <template #item.total_final_inventory_value_bs="{ item }">
        <span class="font-weight-bold text-primary">{{ formatCurrency(item.total_final_inventory_value_bs) }} Bs.</span>
      </template>

      <!-- Slot Retiros / Débito IVA -->
      <template #item.withdrawals_vat="{ item }">
        <div v-if="item.total_withdrawal_units > 0">
          <div class="text-caption font-weight-medium text-warning">
            {{ formatUnits(item.total_withdrawal_units) }} u. ret.
          </div>
          <div class="text-caption text-error font-weight-bold">
            IVA: {{ formatCurrency(item.total_withdrawal_vat_bs) }} Bs.
          </div>
        </div>
        <span v-else class="text-caption text-medium-emphasis">Sin retiros</span>
      </template>

      <!-- Slot Estado -->
      <template #item.status="{ item }">
        <VChip
          :color="item.is_closed ? 'success' : 'warning'"
          size="small"
          label
          class="font-weight-bold"
        >
          {{ item.is_closed ? "Cerrado" : "Abierto" }}
        </VChip>
      </template>

      <!-- Slot Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-center gap-1">
          <VBtn
            icon
            variant="text"
            size="small"
            color="primary"
            title="Ver Detalle"
            @click="emit('view-detail', item.id)"
          >
            <VIcon size="18">tabler-eye</VIcon>
          </VBtn>

          <VBtn
            icon
            variant="text"
            size="small"
            color="success"
            title="Exportar Excel SENIAT"
            @click="emit('export-excel', item.id)"
          >
            <VIcon size="18">tabler-file-spreadsheet</VIcon>
          </VBtn>

          <VBtn
            icon
            variant="text"
            size="small"
            color="error"
            title="Exportar PDF Libro"
            @click="emit('export-pdf', item.id)"
          >
            <VIcon size="18">tabler-file-type-pdf</VIcon>
          </VBtn>

          <VBtn
            v-if="item.total_withdrawal_units > 0"
            icon
            variant="text"
            size="small"
            color="warning"
            title="Descargar Acta de Desincorporación y Débito IVA"
            @click="emit('export-acta', item.id)"
          >
            <VIcon size="18">tabler-file-certificate</VIcon>
          </VBtn>
        </div>
      </template>

      <template #no-data>
        <div class="py-6 text-center text-medium-emphasis">
          <VIcon icon="tabler-box-off" size="36" class="mb-2" />
          <div>No hay libros de inventario registrados para los filtros seleccionados.</div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
