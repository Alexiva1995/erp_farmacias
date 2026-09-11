<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
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
    title: "ID", 
    key: "cycle_id", 
    sortable: true, 
    align: "center",
    width: "70px",
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell",
  },
  { title: "Fec. Inicio", key: "start_date", sortable: true, align: "center" },
  { title: "Fec. Fin", key: "end_date", sortable: true, align: "center" },
  { title: "Estado", key: "cycle_status", sortable: true, align: "center" },
  {
    title: "Productos",
    key: "total_products",
    sortable: true,
    align: "center",
  },
  {
    title: "Sobrante",
    key: "total_surplus",
    sortable: true,
    align: "end",
  },
  {
    title: "Faltante",
    key: "total_shortage",
    sortable: true,
    align: "end",
  },
  { title: "Total Neto", key: "net_total", sortable: true, align: "end" },
  { title: "Acciones", key: "actions", sortable: false, align: "center", width: "90px" },
]);

const getCycleStatusColor = (status) => {
  if (status === "active") return "success";
  if (status === "closed") return "info";
  if (status === "cancelled") return "error";
  return "secondary";
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

const viewCycleDetails = (item) => {
  const id = item.cycle_id || item.id;
  if (id) {
    emit("view-cycle-details", id);
  }
};
</script>

<template>
  <VCard class="mt-4 rounded-lg border shadow-sm overflow-hidden">
    <!-- Cabecera Estándar (igual a Inventario / Productos) -->
    <VCardTitle class="d-flex align-center pa-4">
      <span class="text-h6 font-weight-bold">Historial de Ciclos de Inventario</span>
      <VSpacer />
      <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
        {{ props.totalCycles }} CICLOS
      </VChip>
    </VCardTitle>

    <VDivider />

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
          <div class="d-flex justify-center">
            <span class="font-weight-black text-primary">
              {{ cycle.cycle_id }}
            </span>
          </div>
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
            variant="tonal"
            label
            class="font-weight-black text-uppercase"
          >
            {{ getCycleStatusText(cycle.cycle_status) }}
          </VChip>
        </template>

        <template #item.total_products="{ item: cycle }">
          <VChip
            :color="Number(cycle.total_products || 0) > 0 ? 'primary' : 'secondary'"
            size="x-small"
            variant="tonal"
            label
            class="font-weight-black"
          >
            {{ cycle.total_products || 0 }}
          </VChip>
        </template>

        <template #item.total_surplus="{ item: cycle }">
          <span
            v-if="Number(cycle.total_surplus || 0) > 0"
            class="text-sm text-success font-weight-bold"
          >
            +{{ formatPrice(cycle.total_surplus) }}
          </span>
          <span v-else class="text-sm text-disabled">{{ formatPrice(0) }}</span>
        </template>

        <template #item.total_shortage="{ item: cycle }">
          <span
            v-if="Number(cycle.total_shortage || 0) > 0"
            class="text-sm text-error font-weight-bold"
          >
            -{{ formatPrice(cycle.total_shortage) }}
          </span>
          <span v-else class="text-sm text-disabled">{{ formatPrice(0) }}</span>
        </template>

        <template #item.net_total="{ item: cycle }">
          <span
            class="text-sm font-weight-black"
            :class="{
              'text-success': Number(cycle.net_total || 0) > 0,
              'text-error': Number(cycle.net_total || 0) < 0,
              'text-medium-emphasis': Number(cycle.net_total || 0) === 0,
            }"
          >
            {{ formatPrice(cycle.net_total) }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn
              color="primary"
              size="small"
              @click.stop="viewCycleDetails(item)"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent" location="top">Ver detalles</VTooltip>
            </IconBtn>
          </div>
        </template>

        <template #no-data>
          <AppEmptyState
            title="No se encontraron ciclos de inventario"
            message="No hay ciclos registrados o no coinciden con los filtros aplicados."
            icon="tabler-clipboard-list"
          />
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas Compactas con estilo de inventario) -->
    <div class="d-block d-md-none pa-2">
      <div v-if="props.loading" class="d-flex flex-column gap-2">
        <VProgressLinear indeterminate color="primary" class="mb-2" />
        <VSkeletonLoader v-for="i in 3" :key="i" type="list-item-two-line" class="mb-2 rounded-lg border" />
      </div>
      
      <div v-else-if="props.cycles.length" class="d-flex flex-column gap-2">
        <VCard
          v-for="cycle in props.cycles"
          :key="cycle.cycle_id"
          variant="flat"
          class="border mb-1 rounded-lg pa-3"
        >
          <!-- Cabecera Compacta: ID + Fechas | Acciones + Estado -->
          <div class="d-flex align-start justify-space-between mb-2">
            <div class="d-flex flex-column min-width-0">
              <div class="d-flex align-center gap-2 mb-1">
                <span class="text-xs font-weight-black text-primary bg-primary-lighten-5 px-2 py-0-5 rounded">
                  ID: {{ cycle.cycle_id }}
                </span>
                <VChip
                  :color="getCycleStatusColor(cycle.cycle_status)"
                  size="x-small"
                  label
                  variant="tonal"
                  class="font-weight-black text-uppercase"
                >
                  {{ getCycleStatusText(cycle.cycle_status) }}
                </VChip>
              </div>
              <div class="text-super-xs text-medium-emphasis d-flex align-center flex-wrap gap-x-2">
                <span class="d-flex align-center">
                  <VIcon icon="tabler-calendar-plus" size="12" class="me-1 text-disabled" />
                  {{ formatDateSimple(cycle.start_date) }}
                </span>
                <span class="text-disabled">|</span>
                <span class="d-flex align-center">
                  <VIcon icon="tabler-calendar-check" size="12" class="me-1 text-disabled" />
                  {{ formatDateSimple(cycle.end_date) }}
                </span>
              </div>
            </div>

            <div class="d-flex align-center">
              <IconBtn
                variant="tonal"
                color="info"
                size="small"
                @click.stop="viewCycleDetails(cycle)"
              >
                <VIcon icon="tabler-eye" size="18" />
              </IconBtn>
            </div>
          </div>

          <VDivider class="my-2" />

          <!-- Resumen Financiero y Productos -->
          <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded">
            <div class="d-flex flex-column">
              <span class="text-super-xs text-disabled text-uppercase font-weight-black">Bal. Neto</span>
              <span 
                class="text-sm font-weight-black"
                :class="{
                  'text-success': Number(cycle.net_total || 0) > 0,
                  'text-error': Number(cycle.net_total || 0) < 0
                }"
              >
                {{ formatPrice(cycle.net_total) }}
              </span>
            </div>
            <div class="d-flex flex-column align-end">
              <span class="text-super-xs text-disabled text-uppercase font-weight-black">Productos</span>
              <VChip size="x-small" color="primary" variant="tonal" label class="font-weight-black mt-1">
                {{ cycle.total_products || 0 }}
              </VChip>
            </div>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalCycles"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>

      <div v-else>
        <AppEmptyState
          title="No se encontraron ciclos"
          message="No hay registros de ciclos de inventario."
          icon="tabler-clipboard-list"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.py-0-5 {
  padding-top: 2px !important;
  padding-bottom: 2px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

:deep(.v-data-table th) {
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
