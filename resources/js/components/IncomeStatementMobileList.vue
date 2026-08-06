<script setup>
defineProps({
  transactions: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  totalItems: {
    type: Number,
    default: 0,
  },
  itemsPerPage: {
    type: Number,
    default: 50,
  },
});

const page = defineModel("page", { type: Number, default: 1 });
const emit = defineEmits(["update:page"]);

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
  }).format(amount || 0);
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("es-VE");
};
</script>

<template>
  <div class="pa-2">
    <div v-if="loading" class="text-center py-12">
      <VProgressCircular indeterminate color="primary" size="32" />
      <p class="text-caption mt-2 font-weight-bold text-disabled uppercase">
        Cargando transacciones...
      </p>
    </div>

    <template v-else-if="transactions.length > 0">
      <VRow dense>
        <VCol
          v-for="item in transactions"
          :key="item.id + '-' + item.type"
          cols="12"
          sm="6"
          md="3"
          class="pa-1"
        >
          <VCard
            variant="flat"
            class="h-100 border rounded-lg pa-3 bg-white shadow-xs position-relative overflow-hidden"
          >
            <div
              :class="['position-absolute top-0 left-0 h-1 w-100', item.type === 'sale' ? 'bg-success' : 'bg-error']"
            ></div>
            
            <div class="d-flex justify-space-between align-start mb-2 mt-1">
              <span class="text-super-xs font-weight-black text-disabled uppercase">
                {{ formatDate(item.date) }}
              </span>
              <VChip
                :color="item.type === 'sale' ? 'success' : 'error'"
                size="x-small"
                variant="tonal"
                class="font-weight-bold chip-mobile-badge"
              >
                {{ item.type === "sale" ? "ING" : "EGR" }}
              </VChip>
            </div>

            <p class="text-xs font-weight-bold text-high-emphasis line-clamp-2 min-h-desc mb-2">
              {{ item.description }}
            </p>

            <VDivider class="my-2 opacity-10" />

            <div class="d-flex flex-column gap-1">
              <div class="d-flex justify-space-between align-center text-super-xs">
                <span class="text-disabled">MONTO:</span>
                <span :class="['font-weight-black', item.type === 'sale' ? 'text-success' : 'text-error']">
                  {{ item.type === 'sale' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
                </span>
              </div>
              <div v-if="item.costs > 0" class="d-flex justify-space-between align-center text-super-xs">
                <span class="text-disabled">COSTO:</span>
                <span class="font-weight-black text-warning">-{{ formatCurrency(item.costs) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center text-xs pt-1 border-t mt-1">
                <span class="font-weight-bold text-disabled">UTIL:</span>
                <span :class="['font-weight-black', item.profit >= 0 ? 'text-info' : 'text-error']">
                  {{ formatCurrency(item.profit) }}
                </span>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Paginación Móvil -->
      <div class="pa-4 d-flex justify-center mt-4">
        <VPagination
          v-model="page"
          :length="Math.ceil(totalItems / itemsPerPage)"
          density="compact"
          total-visible="3"
          active-color="primary"
          @update:model-value="emit('update:page', $event)"
        />
      </div>
    </template>

    <div
      v-else
      class="text-center py-12 text-disabled border-2 border-dashed rounded-lg"
    >
      <VIcon icon="tabler-database-x" size="40" class="mb-2" />
      <p class="text-body-2 font-weight-bold">No hay registros</p>
    </div>
  </div>
</template>

<style scoped>
.chip-mobile-badge {
  block-size: 18px;
  font-size: 9px;
}

.min-h-desc {
  min-block-size: 2.5em;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.shadow-xs {
  box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 5%) !important;
}

.border-dashed {
  border-style: dashed !important;
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
</style>
