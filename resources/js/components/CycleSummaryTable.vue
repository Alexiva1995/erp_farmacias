<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { formatDateSimple, formatPrice } from "@/utils/formatters";
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
  { 
    title: "#", 
    key: "cycle_id", 
    sortable: true, 
    align: "center",
    cellClass: "font-weight-black text-primary",
  },
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

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
</script>

<template>
  <VCard class="mt-4 rounded-lg">
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
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
          <span class="font-weight-black text-primary">{{ cycle.cycle_id }}</span>
        </template>

        <template #item.start_date="{ item: cycle }">
          <div class="d-flex align-center justify-center gap-1">
            <VIcon icon="tabler-calendar" size="14" class="text-disabled" />
            <span class="text-sm font-weight-medium">{{ formatDateSimple(cycle.start_date) }}</span>
          </div>
        </template>
        
        <template #item.end_date="{ item: cycle }">
          <div class="d-flex align-center justify-center gap-1">
            <VIcon icon="tabler-calendar" size="14" class="text-disabled" />
            <span class="text-sm font-weight-medium">{{ formatDateSimple(cycle.end_date) }}</span>
          </div>
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
            {{ formatPrice(cycle.total_surplus) }}
          </span>
          <span v-else class="text-sm text-disabled">{{ formatPrice(0) }}</span>
        </template>

        <template #item.total_shortage="{ item: cycle }">
          <span
            v-if="cycle.total_shortage > 0"
            class="text-sm text-error font-weight-medium"
          >
            {{ formatPrice(cycle.total_shortage) }}
          </span>
          <span v-else class="text-sm text-disabled">{{ formatPrice(0) }}</span>
        </template>

        <template #item.net_total="{ item: cycle }">
          <span
            class="text-sm font-weight-black"
            :class="{
              'text-success': cycle.net_total > 0,
              'text-error': cycle.net_total < 0,
              'text-medium-emphasis': cycle.net_total === 0,
            }"
          >
            {{ formatPrice(cycle.net_total) }}
          </span>
        </template>

        <template #item.actions="{ item: cycle }">
          <IconBtn
            icon="tabler-eye"
            size="small"
            color="info"
            @click="viewCycleDetails(cycle.cycle_id)"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent">Ver Detalles</VTooltip>
          </IconBtn>
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
    </div>

    <!-- Vista de Móvil (Tarjetas Compactas) -->
    <div class="d-block d-md-none pa-2">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.cycles.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron ciclos.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="cycle in props.cycles"
          :key="cycle.cycle_id"
          variant="flat"
          class="cycle-mobile-card border mb-2 premium-card"
        >
          <div class="pa-3">
            <!-- Cabecera Compacta: ID + Fechas | Acciones + Estado -->
            <div class="d-flex align-start justify-space-between mb-3">
              <div class="d-flex flex-column min-width-0">
                <div class="d-flex align-center gap-2 mb-1">
                  <span class="text-sm font-weight-black text-primary">#{{ cycle.cycle_id }}</span>
                  <VChip
                    :color="getCycleStatusColor(cycle.cycle_status)"
                    size="x-small"
                    label
                    variant="flat"
                    class="text-super-xs font-weight-bold"
                  >
                    {{ getCycleStatusText(cycle.cycle_status).toUpperCase() }}
                  </VChip>
                </div>
                <div class="text-super-xs text-medium-emphasis d-flex align-center flex-wrap gap-x-2">
                  <span class="d-flex align-center">
                    <VIcon icon="tabler-calendar-plus" size="10" class="me-1" />
                    {{ formatDateSimple(cycle.start_date) }}
                  </span>
                  <span class="text-disabled">|</span>
                  <span class="d-flex align-center">
                    <VIcon icon="tabler-calendar-check" size="10" class="me-1" />
                    {{ formatDateSimple(cycle.end_date) }}
                  </span>
                </div>
              </div>

              <div class="d-flex align-center">
                <IconBtn
                  variant="tonal"
                  color="info"
                  size="32"
                  class="rounded"
                  @click="viewCycleDetails(cycle.cycle_id)"
                >
                  <VIcon icon="tabler-eye" size="18" />
                </IconBtn>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Resumen Financiero -->
            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Bal. Neto</span>
                <span 
                  class="text-base font-weight-black"
                  :class="{
                    'text-success': cycle.net_total > 0,
                    'text-error': cycle.net_total < 0
                  }"
                >
                  {{ formatPrice(cycle.net_total) }}
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Productos</span>
                <span class="text-base font-weight-black text-primary">
                  {{ cycle.total_products || 0 }} <small class="text-super-xs">UNDS</small>
                </span>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4">
         <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalCycles"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.cycle-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-4 { gap: 16px !important; }

:deep(.v-data-table) {
  font-size: 0.875rem;
}

:deep(.v-data-table td) {
  block-size: auto !important;
  padding-block: 10px !important;
  padding-inline: 16px !important;
}

:deep(.v-data-table th) {
  font-size: 0.75rem !important;
  font-weight: 600 !important;
  padding-block: 10px !important;
  padding-inline: 16px !important;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}
</style>
