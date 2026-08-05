<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed } from "vue";

const props = defineProps({
  loading: { type: Boolean, default: false },
  items: { type: Array, required: true },
  totalItems: { type: Number, default: 0 },
  itemsPerPage: { type: Number, default: 10 },
  page: { type: Number, default: 1 },
  submittingItems: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:page", "add-units"]);

const pageModel = computed({
  get: () => props.page,
  set: (val) => emit("update:page", val),
});
</script>

<template>
  <div class="pa-2 bg-light-gray">
    <div v-if="loading" class="d-flex justify-center pa-8">
      <VProgressCircular indeterminate color="primary" />
    </div>
    <div
      v-else-if="items.length === 0"
      class="text-center pa-8 text-disabled"
    >
      No se encontraron oportunidades
    </div>
    <div v-else class="d-flex flex-column gap-2">
      <VCard
        v-for="item in items"
        :key="item.id"
        variant="flat"
        class="mb-1 rounded-lg border shadow-sm bg-white"
      >
        <VCardText class="pa-4">
          <div class="d-flex justify-space-between align-start mb-3">
            <div class="flex-grow-1 overflow-hidden">
              <div class="d-flex align-center gap-1 mb-1">
                <a
                  :href="'/inventory/traceability?q=' + item.product_id"
                  target="_blank"
                  class="text-decoration-none text-xs font-weight-black text-primary"
                >
                  #{{ item.product_id }}
                </a>
                <div
                  class="text-subtitle-2 font-weight-black leading-tight truncate-2-lines"
                  :title="item.product_name_inventory"
                >
                  {{ item.product_name_inventory }}
                </div>
              </div>
              <div class="d-flex flex-column ga-1 text-super-xs text-disabled">
                <span class="truncate">{{ item.active_ingredient_inventory }}</span>
                <span class="text-primary font-weight-bold">{{ item.laboratory_name }}</span>
              </div>
            </div>
            
            <div class="text-right d-flex flex-column align-end ms-2">
              <VChip 
                color="success" 
                variant="flat" 
                size="small" 
                class="font-weight-black mb-1"
              >
                {{ item.saving_percentage }}% AHORRO
              </VChip>
            </div>
          </div>

          <VDivider class="my-2 border-opacity-10" />

          <!-- Grid de Métricas de Análisis -->
          <div class="grid-mobile-info mb-3">
            <div class="info-item">
              <span class="label">Stock</span>
              <span class="text-sm font-weight-bold" :class="item.lote_quantity > 0 ? 'text-secondary' : 'text-error'">
                {{ item.lote_quantity || 0 }}
              </span>
            </div>
            <div class="info-item">
              <span class="label">AO</span>
              <span class="text-sm font-weight-bold text-warning">
                {{ item.totalQuantityInAutoOrder || 0 }}
              </span>
            </div>
            <div class="info-item">
              <span class="label">Ventas</span>
              <span class="text-sm font-weight-bold">{{ item.total_sold_completed || 0 }}</span>
            </div>
            <div class="info-item">
              <span class="label">Prom.</span>
              <span class="text-sm font-weight-bold">{{ item.promedio_calculado || 0 }}</span>
            </div>
          </div>

          <!-- Detalles de Precios -->
          <VRow dense class="mb-3 bg-var-theme-background rounded pa-2 border-dashed-thin mx-0">
            <VCol cols="4">
              <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Histórico</div>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-bold text-error">
                  MAX: {{ formatCurrency(item.effective_max_cost, "USD") }}
                </span>
                <span class="text-xs font-weight-black text-high-emphasis my-0.5">
                  ACT: {{ formatCurrency(item.inventory_unit_cost, "USD") }}
                </span>
                <span class="text-super-xs font-weight-bold text-success">
                  MIN: {{ formatCurrency(item.effective_min_cost, "USD") }}
                </span>
              </div>
            </VCol>
            
            <VCol cols="4" class="border-s border-dashed px-2">
              <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Oferta</div>
              <div class="text-sm font-weight-bold text-success">{{ formatCurrency(item.unit_cost_usd, "USD") }}</div>
              <div class="text-super-xs text-primary truncate font-weight-black">{{ item.supplier_name }}</div>
            </VCol>
            
            <VCol cols="4" class="border-s border-dashed px-2">
              <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">% Ahorro</div>
              <div class="text-sm font-weight-bold text-success">{{ item.saving_percentage }}%</div>
            </VCol>
          </VRow>
          
          <!-- Acciones -->
          <div class="d-flex align-center justify-space-between bg-var-theme-background-soft pa-2 rounded-lg border border-dashed">
            <span class="text-xs font-weight-black text-disabled">AÑADIR A ORDEN:</span>
            <div class="d-flex align-center ga-2">
              <VTextField
                v-model="item.quantity_to_add"
                type="number"
                density="compact"
                hide-details
                variant="outlined"
                class="quantity-input-mobile"
                bg-color="white"
                :disabled="!!submittingItems[item.id]"
              />
              <VBtn
                :icon="!submittingItems[item.id] ? 'tabler-plus' : undefined"
                color="primary"
                variant="tonal"
                size="32"
                class="rounded-lg shadow-sm"
                :loading="!!submittingItems[item.id]"
                :disabled="!!submittingItems[item.id]"
                @click="emit('add-units', item)"
              />
            </div>
          </div>
        </VCardText>
      </VCard>

      <VPagination
        v-model="pageModel"
        :length="Math.ceil(totalItems / itemsPerPage)"
        density="compact"
        class="mt-4"
      />
    </div>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.bg-light-gray {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.quantity-input-mobile {
  inline-size: 70px;
}

.bg-var-theme-background-soft {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.border-dashed {
  border-style: dashed !important;
}

.leading-tight {
  line-height: 1.25;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.grid-mobile-info {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.info-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  background-color: rgba(var(--v-theme-on-surface), 0.02);
  padding: 8px;
  border-radius: 8px;
}

.info-item .label {
  font-size: 0.6rem;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 800;
  margin-bottom: 2px;
}
</style>
