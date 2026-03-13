<script setup>
import { formatDate, formatPrice as formatCurrency } from "@/utils/formatters";
import { computed } from "vue";

const props = defineProps({
  logs: { type: Array, required: true },
  totalLogs: { type: Number, required: true },
  loading: { type: Boolean, required: true },
  page: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  selectedLogs: { type: Array, required: true },
});

const emit = defineEmits([
  "update:options",
  "update:selectedLogs",
  "generate-donation",
]);

const headers = [
  { title: "ID", key: "product.id", sortable: true },
  { title: "Producto", key: "product_name", sortable: false },
  { 
    title: "Laboratorio", 
    key: "laboratory_name", 
    sortable: false,
    value: (item) => item.product?.laboratory?.name || "—"
  },
  { title: "Lote", key: "lot_number", align: "center", sortable: false },
  { title: "Vencimiento", key: "expired_at", align: "center", sortable: true },
  {
    title: "Cant. Caducada",
    key: "expired_quantity",
    align: "center",
    sortable: false,
  },
  {
    title: "Costo Total",
    key: "total_lost_value",
    align: "end",
    sortable: false,
  },
];

const selected = computed({
  get: () => props.selectedLogs,
  set: (value) => emit("update:selectedLogs", value),
});

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};

const toggleSelection = (id) => {
  const current = [...props.selectedLogs];
  const index = current.indexOf(id);
  if (index > -1) {
    current.splice(index, 1);
  } else {
    current.push(id);
  }
  emit("update:selectedLogs", current);
};
</script>

<template>
  <VCard variant="flat">
    <VCardText class="d-flex justify-end pa-4 bg-var-theme-background">
      <VBtn
        color="success"
        variant="elevated"
        :disabled="selected.length === 0"
        prepend-icon="tabler-gift"
        @click="emit('generate-donation')"
      >
        GENERAR DONACIÓN ({{ selected.length }})
      </VBtn>
    </VCardText>

    <VDivider />

    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        v-model="selected"
        :headers="headers"
        :items="props.logs"
        :items-length="props.totalLogs"
        :loading="props.loading"
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        item-value="id"
        show-select
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.product_name="{ item }">
          <div class="d-flex align-center">
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium text-uppercase">{{
                item.product_name || ""
              }}</span>
              <span v-if="item.product" class="text-sm text-disabled">{{
                item.product.active_ingredient
              }}</span>
            </div>
          </div>
        </template>

        <template #item.laboratory_name="{ item }">
          <span class="text-uppercase">{{ item.product?.laboratory?.name || "—" }}</span>
        </template>

        <template #item.expired_at="{ item }">
          <span class="font-weight-medium">{{ formatDate(item.expired_at) }}</span>
        </template>

        <template #item.total_lost_value="{ item }">
          <span class="font-weight-black text-primary">{{ formatCurrency(item.total_lost_value) }}</span>
        </template>

        <template #item.expired_quantity="{ item }">
          <VChip size="small" label variant="tonal" color="error" class="font-weight-bold">
            {{ item.expired_quantity }} UNDS
          </VChip>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.logs.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No hay registros en este periodo.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.logs"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden"
          :class="selected.includes(item.id) ? 'border-primary bg-primary-lighten-5' : ''"
          style="border-radius: 8px !important;"
          @click="toggleSelection(item.id)"
        >
          <div class="pa-3">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex flex-column flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                    <span class="text-primary">#{{ item.product?.id || '—' }}</span>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.product_name }}
                  </h3>
                </div>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                  <span class="text-medium-emphasis font-weight-medium">{{ item.product?.active_ingredient }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-bold text-uppercase">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
              <VCheckboxBtn
                :model-value="selected.includes(item.id)"
                density="compact"
                hide-details
                color="primary"
                @click.stop="toggleSelection(item.id)"
              />
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Venció el</span>
                <span class="text-base font-weight-black text-error">
                  {{ formatDate(item.expired_at) }}
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Cant. Final</span>
                <span class="text-base font-weight-black text-error">
                  {{ item.expired_quantity }} <small class="text-super-xs">UNDS</small>
                </span>
              </div>
            </div>

            <div class="d-flex justify-space-between align-center mt-3">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Lote: {{ item.lot_number }}</span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Pérdida (Costo)</span>
                <span class="text-sm font-weight-black text-primary">{{ formatCurrency(item.total_lost_value) }}</span>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalLogs / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          @update:model-value="handleMobilePageChange"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-primary {
  border: 1.5px solid rgb(var(--v-theme-primary)) !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
