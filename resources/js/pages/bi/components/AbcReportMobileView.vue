<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  page: { type: Number, default: 1 },
  itemsPerPage: { type: Number, default: 10 },
  getColorClass: { type: Function, required: true },
  getGmroiColor: { type: Function, required: true },
});

const emit = defineEmits(['update:page']);
</script>

<template>
  <div class="d-md-none">
    <VProgressLinear v-if="loading" indeterminate color="primary" />
    <div v-if="items.length === 0 && !loading" class="text-center pa-8 text-medium-emphasis">
      <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
      <p class="text-body-2 font-weight-medium mb-0">Sin resultados para los filtros aplicados</p>
    </div>
    <div v-for="item in items" :key="item.id" class="px-2 py-1">
      <VCard variant="flat" class="product-mobile-card border mb-2">
        <div class="pa-3">
          <!-- Cabecera -->
          <div class="d-flex align-start justify-space-between gap-2">
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-center gap-1 mb-1">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                  <span class="text-primary text-xs">#{{ item.id }}</span>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.name }}
                </h3>
              </div>
              <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory_name || 'S/L' }}
                </span>
                <span class="text-disabled">|</span>
                <span class="text-disabled truncate" style="max-inline-size: 120px;">
                  {{ item.active_ingredient || 'Sin ingrediente' }}
                </span>
              </div>
            </div>
            <VChip
              :color="getColorClass(item.final_classification)"
              class="text-uppercase font-weight-black flex-shrink-0"
              variant="elevated"
              size="x-small"
              label
            >
              {{ item.final_classification }}
            </VChip>
          </div>

          <VDivider class="my-3 border-opacity-10" />

          <!-- Métricas en grilla -->
          <div class="metrics-grid rounded border-dashed-thin bg-var-theme-background">
            <VRow dense class="ma-0">
              <VCol cols="6" class="pa-2 border-r border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">
                  Ventas ({{ item.contribution_sales_pct ? item.contribution_sales_pct.toFixed(1) : '0.0' }}%)
                </div>
                <div class="text-sm font-weight-black text-success">{{ formatCurrency(item.total_sales) }}</div>
                <div class="text-super-xs text-disabled">{{ item.sold_units }} uds</div>
              </VCol>
              <VCol cols="6" class="pa-2 border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">
                  Margen ({{ item.contribution_margin_pct ? item.contribution_margin_pct.toFixed(1) : '0.0' }}%)
                </div>
                <div class="text-sm font-weight-black" :class="item.margin_percentage > 0 ? 'text-primary' : 'text-error'">
                  {{ item.margin_percentage }}%
                </div>
                <div class="text-super-xs text-disabled">{{ formatCurrency(item.margin_amount) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2 border-r border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">ROI Anual</div>
                <div class="text-sm font-weight-black" :class="getGmroiColor(item.gmroi)">
                  {{ item.gmroi >= 9999 ? 'MAX' : Math.round(item.gmroi) + '%' }}
                </div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Stock / Cobertura</div>
                <div class="text-sm font-weight-black" :class="item.current_stock === 0 ? 'text-error' : ''">{{ item.current_stock }} uds</div>
                <div class="text-super-xs font-weight-bold" :class="item.inventory_days < 10 ? 'text-error' : 'text-disabled'">
                  {{ item.inventory_days === 9999 ? 'Sin rotación' : Math.round(item.inventory_days) + ' días' }}
                </div>
              </VCol>
            </VRow>
          </div>
        </div>

        <!-- Acciones -->
        <div class="d-flex border-t border-opacity-10">
          <VBtn 
            :href="'/inventory/traceability?q=' + item.id" 
            target="_blank"
            block 
            color="primary" 
            variant="text" 
            class="rounded-0 text-caption font-weight-bold" 
            height="40"
          >
            <VIcon icon="tabler-history" size="18" class="me-2" />
            Ver Trazabilidad
          </VBtn>
        </div>
      </VCard>
    </div>

    <!-- Paginación móvil -->
    <div class="d-flex justify-center align-center pa-3 gap-3">
      <VBtn icon variant="text" size="32" :disabled="page <= 1" @click="emit('update:page', page - 1)">
        <VIcon icon="tabler-chevron-left" size="18" />
      </VBtn>
      <span class="text-caption text-medium-emphasis">Pág. {{ page }}</span>
      <VBtn icon variant="text" size="32" :disabled="items.length < itemsPerPage" @click="emit('update:page', page + 1)">
        <VIcon icon="tabler-chevron-right" size="18" />
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.product-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.metrics-grid {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
