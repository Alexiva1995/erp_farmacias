<script setup>
import { formatDateSimple, formatPrice as formatCurrency } from "@/utils/formatters";
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);
const formatDate = formatDateSimple;

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

const headers = computed(() => [
  { 
    title: "ID", 
    key: "product_id", 
    sortable: true,
    cellClass: "font-weight-black text-primary",
    width: "80px",
  },
  { title: "PRODUCTO", key: "product_name", sortable: false, width: "38%" },
  { title: "# LOTE", key: "lot_number", align: "center", sortable: false },
  { title: "VENCIMIENTO", key: "expired_at", align: "center", sortable: true },
  {
    title: "CANT. CADUCADA",
    key: "expired_quantity",
    align: "center",
    sortable: false,
  },
  {
    title: "COSTO TOTAL",
    key: "total_lost_value",
    align: "end",
    sortable: false,
  },
]);

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
  <VCard variant="flat" class="rounded-lg border shadow-sm overflow-hidden">
    <VCardText v-if="!isRestaurant" class="d-flex justify-end pa-3 bg-var-theme-background border-b">
      <VBtn
        color="success"
        variant="elevated"
        size="small"
        class="font-weight-black"
        :disabled="selected.length === 0"
        prepend-icon="tabler-gift"
        @click="emit('generate-donation')"
      >
        GENERAR DONACIÓN ({{ selected.length }})
      </VBtn>
    </VCardText>

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
        :show-select="!isRestaurant"
        density="compact"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <!-- ID con enlace a trazabilidad sin # -->
        <template #item.product_id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + (item.product?.id || item.product_id)"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.product?.id || item.product_id }}
          </a>
        </template>

        <!-- PRODUCTO: formato unificado igual a inventario -->
        <template #item.product_name="{ item }">
          <div class="d-flex align-center gap-x-2 py-1">
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate product-title-max"
                :class="{ 
                  'text-warning': item.product?.psychotropic == 1 || item.product?.psychotropic === true 
                }"
                :title="item.product_name || item.product?.name"
              >
                {{ (item.product_name || item.product?.name || '—').toUpperCase() }}
                <span v-if="item.product?.iva == 1 || item.product?.iva === true" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span v-if="!isRestaurant" class="text-disabled truncate active-ingredient-max">
                  {{ item.product?.active_ingredient || item.product?.presentation || "Sin principio" }}
                </span>
                <span v-else class="text-disabled truncate active-ingredient-max">
                  {{ item.product?.presentation || "S/P" }}{{ item.product?.unit_of_measure ? ` (${item.product?.unit_of_measure})` : '' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate lab-name-max">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.lot_number="{ item }">
          <span class="font-weight-medium text-caption">{{ item.lot_number || "—" }}</span>
        </template>

        <template #item.expired_at="{ item }">
          <span class="text-caption font-weight-medium">{{ formatDate(item.expired_at || item.created_at) }}</span>
        </template>

        <template #item.total_lost_value="{ item }">
          <span class="font-weight-black text-primary text-body-2">{{ formatCurrency(item.total_lost_value) }}</span>
        </template>

        <template #item.expired_quantity="{ item }">
          <VChip size="small" label variant="tonal" color="error" class="font-weight-black">
            {{ Math.trunc(item.expired_quantity ?? 0) }}
          </VChip>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.logs.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No hay registros en este periodo.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.logs"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden"
          :class="!isRestaurant && selected.includes(item.id) ? 'border-primary bg-primary-lighten-5' : ''"
          style="border-radius: 8px !important;"
          @click="!isRestaurant && toggleSelection(item.id)"
        >
          <div class="pa-3">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex flex-column flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <a
                    :href="'/inventory/traceability?q=' + (item.product?.id || item.product_id)"
                    target="_blank"
                    class="text-decoration-none font-weight-black text-primary text-xs"
                    @click.stop
                  >
                    {{ item.product?.id || item.product_id }}
                  </a>
                  <span class="mx-1 text-disabled">|</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate mb-0">
                    {{ item.product_name || item.product?.name || '—' }}
                  </h3>
                </div>
                <div class="d-flex align-center flex-wrap gap-1 text-super-xs">
                  <span v-if="!isRestaurant" class="text-disabled truncate" style="max-inline-size: 150px;">
                    {{ item.product?.active_ingredient || item.product?.presentation || "Sin principio" }}
                  </span>
                  <span v-else class="text-disabled truncate" style="max-inline-size: 150px;">
                    {{ item.product?.presentation || "S/P" }}{{ item.product?.unit_of_measure ? ` (${item.product?.unit_of_measure})` : '' }}
                  </span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 130px;">
                    {{ item.product?.laboratory?.name || 'S/L' }}
                  </span>
                </div>
              </div>
              <VCheckboxBtn
                v-if="!isRestaurant"
                :model-value="selected.includes(item.id)"
                density="compact"
                hide-details
                color="primary"
                @click.stop="toggleSelection(item.id)"
              />
            </div>

            <VDivider class="my-2 border-opacity-10" />

            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Venció el</span>
                <span class="text-sm font-weight-black text-error">
                  {{ formatDate(item.expired_at || item.created_at) }}
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Cant. Final</span>
                <span class="text-sm font-weight-black text-error">
                  {{ Math.trunc(item.expired_quantity ?? 0) }}
                </span>
              </div>
            </div>

            <div class="d-flex justify-space-between align-center mt-2">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Lote: {{ item.lot_number || "—" }}</span>
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
  font-size: 0.72rem !important;
  line-height: 1.1;
}

.product-title-max {
  max-inline-size: 380px;
}

.active-ingredient-max {
  max-inline-size: 180px;
}

.lab-name-max {
  max-inline-size: 150px;
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

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
