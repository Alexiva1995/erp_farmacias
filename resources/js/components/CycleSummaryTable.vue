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
  { title: "ID Ciclo", key: "cycle_id", sortable: true, align: "center" },
  { title: "Fecha Inicio", key: "start_date", sortable: true, align: "center" },
  { title: "Fecha Fin", key: "end_date", sortable: true, align: "center" },
  { title: "Estado", key: "cycle_status", sortable: true, align: "center" },
  {
    title: "Total Productos",
    key: "total_products",
    sortable: true,
    align: "center",
  },
  {
    title: "Total Sobrante",
    key: "total_surplus",
    sortable: true,
    align: "center",
  },
  {
    title: "Total Faltante",
    key: "total_shortage",
    sortable: true,
    align: "center",
  },
  { title: "Total Cierre", key: "net_total", sortable: true, align: "center" },
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
    <VCardTitle>Resumen de Ciclos de Inventario</VCardTitle>
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
    >
      <template #item.cycle_id="{ item: cycle }">
        <VChip color="primary" variant="tonal" size="small" label>
          #{{ cycle.cycle_id }}
        </VChip>
      </template>

      <template #item.start_date="{ item: cycle }">
        <span>{{ formatDate(cycle.start_date) }}</span>
      </template>

      <template #item.end_date="{ item: cycle }">
        <span>{{ formatDate(cycle.end_date) }}</span>
      </template>

      <template #item.cycle_status="{ item: cycle }">
        <VChip
          :color="getCycleStatusColor(cycle.cycle_status)"
          size="small"
          label
        >
          {{ getCycleStatusText(cycle.cycle_status) }}
        </VChip>
      </template>

      <template #item.total_products="{ item: cycle }">
        <span class="font-weight-medium">
          {{ cycle.total_products || 0 }}
        </span>
      </template>

      <template #item.total_surplus="{ item: cycle }">
        <span
          v-if="cycle.total_surplus > 0"
          class="text-success font-weight-bold"
        >
          {{ formatCurrency(cycle.total_surplus) }}
        </span>
        <span v-else class="text-disabled">{{ formatCurrency(0) }}</span>
      </template>

      <template #item.total_shortage="{ item: cycle }">
        <span
          v-if="cycle.total_shortage > 0"
          class="text-error font-weight-bold"
        >
          {{ formatCurrency(cycle.total_shortage) }}
        </span>
        <span v-else class="text-disabled">{{ formatCurrency(0) }}</span>
      </template>

      <template #item.net_total="{ item: cycle }">
        <span
          class="font-weight-bold"
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
        <div class="d-flex gap-2">
          <VTooltip text="Ver Detalles del Ciclo">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                icon="tabler-eye"
                size="small"
                variant="text"
                color="primary"
                @click="viewCycleDetails(cycle.cycle_id)"
              />
            </template>
          </VTooltip>
        </div>
      </template>

      <template #bottom>
        <VDivider />
        <div class="d-flex align-center justify-space-between pa-4">
          <div class="text-sm text-disabled">
            Mostrando {{ props.cycles.length }} de
            {{ props.totalCycles }} ciclos
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
