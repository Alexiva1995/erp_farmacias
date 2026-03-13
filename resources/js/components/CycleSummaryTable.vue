<script setup>
import { ref } from "vue";

const props = defineProps({
  cycles: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  totalCycles: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
  page: {
    type: Number,
    default: 1,
  },
});

const emit = defineEmits(["update:options", "view-cycle-details"]);

const headers = ref([
  { title: "#", key: "cycle_id", sortable: true, align: "center" },
  { title: "Fec. Inicio", key: "start_date", sortable: true, align: "center" },
  { title: "Fec. Fin", key: "end_date", sortable: true, align: "center" },
  { title: "Estado", key: "cycle_status", sortable: true, align: "center" },
  {
    title: "Tot. Productos",
    key: "total_products",
    sortable: true,
    align: "center",
  },
  {
    title: "Tot. Sobrante",
    key: "total_surplus",
    sortable: true,
    align: "center",
  },
  {
    title: "Tot. Faltante",
    key: "total_shortage",
    sortable: true,
    align: "center",
  },
  { title: "Total", key: "net_total", sortable: true, align: "center" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
]);

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, "0");
    const day = date.getDate().toString().padStart(2, "0");

    return `${year}-${month}-${day}`;
  } catch (error) {
    return "N/A";
  }
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value || 0);
};

const getCycleStatusColor = (status) => {
  if (status === "active") return "success";
  if (status === "closed") return "info";
  if (status === "cancelled") return "error";
  return "grey";
};

const getCycleStatusText = (status) => {
  if (status === "active") return "Activo";
  if (status === "closed") return "Cerrado";
  if (status === "cancelled") return "Cancelado";
  return "Desconocido";
};

const updateOptions = (options) => {
  emit("update:options", options);
};

const viewCycleDetails = (cycleId) => {
  emit("view-cycle-details", cycleId);
};
</script>

<template>
  <VCard class="mt-4">
    <VCardTitle class="py-3">Resumen de Ciclos de Inventario</VCardTitle>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.cycles"
      :items-length="props.totalCycles"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="updateOptions"
      item-value="cycle_id"
      hover
      density="compact"
    >
      <template #item.cycle_id="{ item: cycle }">
        <VChip color="primary" variant="tonal" size="x-small" label class="text-xs">
          {{ cycle.cycle_id }}
        </VChip>
      </template>

      <template #item.start_date="{ item: cycle }">
        <span class="text-sm">{{ formatDate(cycle.start_date) }}</span>
      </template>

      <template #item.end_date="{ item: cycle }">
        <span class="text-sm">{{ formatDate(cycle.end_date) }}</span>
      </template>

      <template #item.cycle_status="{ item: cycle }">
        <VChip
          :color="getCycleStatusColor(cycle.cycle_status)"
          size="x-small"
          label
          class="text-xs"
        >
          {{ getCycleStatusText(cycle.cycle_status) }}
        </VChip>
      </template>

      <template #item.total_products="{ item: cycle }">
        <span class="text-sm font-weight-medium">
          {{ cycle.total_products || 0 }}
        </span>
      </template>

      <template #item.total_surplus="{ item: cycle }">
        <span
          v-if="cycle.total_surplus > 0"
          class="text-sm text-success font-weight-medium"
        >
          {{ formatCurrency(cycle.total_surplus) }}
        </span>
        <span v-else class="text-sm text-disabled">{{ formatCurrency(0) }}</span>
      </template>

      <template #item.total_shortage="{ item: cycle }">
        <span
          v-if="cycle.total_shortage > 0"
          class="text-sm text-error font-weight-medium"
        >
          {{ formatCurrency(cycle.total_shortage) }}
        </span>
        <span v-else class="text-sm text-disabled">{{ formatCurrency(0) }}</span>
      </template>

      <template #item.net_total="{ item: cycle }">
        <span
          class="text-sm font-weight-medium"
          :class="{
            'text-success': cycle.net_total > 0,
            'text-error': cycle.net_total < 0,
            'text-medium-emphasis': cycle.net_total === 0,
          }"
        >
          {{ formatCurrency(cycle.net_total) }}
        </span>
      </template>

      <template #item.actions="{ item: cycle }">
        <VBtn
          icon="tabler-eye"
          size="x-small"
          variant="text"
          color="info"
          @click="viewCycleDetails(cycle.cycle_id)"
        />
      </template>

      <template #bottom>
        <VDivider />
        <div class="d-flex align-center justify-space-between pa-2">
          <div class="text-xs text-disabled">
            Mostrando {{ props.cycles.length }} de
            {{ props.totalCycles }} ciclos
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
:deep(.v-data-table) {
  font-size: 0.875rem;
}

:deep(.v-data-table td) {
  block-size: auto !important;
  padding-block: 8px !important;
  padding-inline: 16px !important;
}

:deep(.v-data-table th) {
  font-size: 0.75rem !important;
  font-weight: 600 !important;
  padding-block: 10px !important;
  padding-inline: 16px !important;
}

:deep(.v-data-table__wrapper) {
  overflow-x: auto;
}

:deep(.v-chip) {
  block-size: 20px !important;
  font-size: 0.7rem !important;
}
</style>
