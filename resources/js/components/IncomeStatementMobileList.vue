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
const emit = defineEmits(["update:page", "view-order"]);

const formatNumber = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount || 0);
};

const formatDate = (date) => {
  if (!date) return "—";
  return new Date(date).toLocaleDateString("es-VE");
};

const getMarginChipClass = (pct) => {
  const num = Number(pct || 0);
  if (num < 0) return "margin-chip-error";
  if (num < 15) return "margin-chip-warning";
  return "margin-chip-success";
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
              <div class="d-flex flex-column">
                <span
                  v-if="item.type === 'sale'"
                  class="text-xs font-weight-black text-primary font-mono cursor-pointer clickable-order"
                  @click="emit('view-order', item.id)"
                >
                  <VIcon icon="tabler-receipt" size="14" class="me-1" />
                  {{ item.voucher_number || `Order #${item.id}` }}
                </span>
                <span v-else class="text-xs font-weight-black text-secondary font-mono">
                  {{ item.voucher_number || `EGR-#${item.id}` }}
                </span>
                <span class="text-super-xs font-weight-bold text-disabled uppercase mt-0.5">
                  {{ formatDate(item.date) }}
                </span>
              </div>
              <VChip
                :color="item.type === 'sale' ? 'success' : 'error'"
                size="x-small"
                variant="tonal"
                class="font-weight-bold chip-mobile-badge"
              >
                {{ item.type === "sale" ? "INGRESO" : "EGRESO" }}
              </VChip>
            </div>

            <p class="text-xs font-weight-bold text-high-emphasis line-clamp-2 min-h-desc mb-1">
              {{ item.description }}
            </p>
            <span class="text-super-xs text-medium-emphasis d-block mb-2">
              {{ item.client }} • {{ item.channel }}
            </span>

            <VDivider class="my-2 opacity-10" />

            <div class="d-flex flex-column gap-1">
              <div class="d-flex justify-space-between align-center text-super-xs">
                <span class="text-disabled font-weight-bold">VENTA:</span>
                <span class="font-weight-black font-mono text-body-2 text-high-emphasis">
                  {{ formatNumber(item.amount) }}
                </span>
              </div>
              <div class="d-flex justify-space-between align-center text-super-xs">
                <span class="text-disabled font-weight-bold">COSTO:</span>
                <span class="font-weight-black font-mono text-body-2 text-high-emphasis">
                  {{ item.costs > 0 ? formatNumber(item.costs) : "0,00" }}
                </span>
              </div>
              <div class="d-flex justify-space-between align-center text-xs pt-1 border-t mt-1">
                <div class="d-flex align-center gap-1">
                  <span class="font-weight-bold text-disabled">MARGEN:</span>
                  <VChip
                    v-if="item.type === 'sale'"
                    size="x-small"
                    variant="tonal"
                    class="font-weight-black font-mono"
                    :class="getMarginChipClass(item.margin_percentage)"
                    style="font-size: 0.65rem; height: 18px;"
                  >
                    {{ Number(item.margin_percentage || 0).toFixed(2) }}%
                  </VChip>
                </div>
                <span :class="['font-weight-black font-mono text-body-2', item.profit >= 0 ? 'text-money-green' : 'text-error']">
                  {{ formatNumber(item.profit) }}
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

.text-profit-positive {
  color: #16a34a !important;
}

.text-profit-negative {
  color: #dc2626 !important;
}

.text-money-green {
  color: #16a34a !important;
}

.margin-chip-success {
  background-color: #dcfce7 !important;
  color: #166534 !important;
  border: 1px solid #86efac !important;
}

.margin-chip-warning {
  background-color: #fef3c7 !important;
  color: #92400e !important;
  border: 1px solid #fcd34d !important;
}

.margin-chip-error {
  background-color: #fee2e2 !important;
  color: #991b1b !important;
  border: 1px solid #fca5a5 !important;
}

.clickable-order {
  cursor: pointer;
  transition: all 0.2s ease-in-out;
}

.clickable-order:hover {
  filter: brightness(0.9);
  text-decoration: underline;
}
</style>
