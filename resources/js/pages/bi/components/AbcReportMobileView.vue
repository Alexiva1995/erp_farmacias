<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  page: { type: Number, default: 1 },
  itemsPerPage: { type: Number, default: 10 },
  selectedAnalysisType: { type: String, default: 'all' },
  isSimplifiedView: { type: Boolean, default: false },
  getColorClass: { type: Function, required: false, default: () => 'default' },
  getAbcBadgeStyle: { type: Function, required: false, default: () => ({}) },
  getGmroiColor: { type: Function, required: true },
});

const emit = defineEmits(['update:page', 'openOffer', 'openAssign']);
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
              
              <!-- Badges de Alerta (Margen Negativo / Por Caducar / Oferta Individual) -->
              <div
                v-if="selectedAnalysisType === 'negative_margin' || item.margin_percentage < 0 || item.margin_amount < 0"
                class="d-flex align-center flex-wrap gap-1 mb-2"
              >
                <VChip
                  v-if="item.is_expiring_soon || (item.days_to_expiration !== null && item.days_to_expiration <= 180)"
                  color="error"
                  size="x-small"
                  variant="flat"
                  density="compact"
                  class="font-weight-bold"
                >
                  <VIcon icon="tabler-clock-exclamation" size="12" class="me-1" />
                  {{ item.days_to_expiration <= 0 ? 'Vencido' : (item.months_to_expiration ? `Vence en ${item.months_to_expiration} m` : 'Por vencer') }}
                </VChip>

                <VChip
                  v-if="item.has_individual_offer || item.individual_offer_discount"
                  color="warning"
                  size="x-small"
                  variant="flat"
                  density="compact"
                  class="font-weight-bold"
                >
                  <VIcon icon="tabler-tag" size="12" class="me-1" />
                  Oferta Ind. -{{ Math.round(item.individual_offer_discount) }}%
                </VChip>
              </div>

              <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 200px;">
                  {{ item.laboratory_name || 'S/L' }}
                </span>
              </div>
            </div>
            <span
              v-if="!isSimplifiedView"
              class="abc-badge-pill flex-shrink-0"
              :style="getAbcBadgeStyle(item.final_classification)"
            >
              {{ item.final_classification }}
            </span>
          </div>

          <VDivider class="my-3 border-opacity-10" />

          <!-- Grilla Simplificada de Capital Parado -->
          <div v-if="isSimplifiedView" class="metrics-grid rounded border-dashed-thin bg-var-theme-background">
            <VRow dense class="ma-0">
              <VCol cols="6" class="pa-2 border-r border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Stock Actual</div>
                <div class="text-sm font-weight-black text-high-emphasis">{{ item.current_stock }} unds</div>
                <div class="text-super-xs text-medium-emphasis">Costo: {{ formatCurrency(item.last_cost) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2 border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Ventas en Periodo</div>
                <div class="text-sm font-weight-black" :class="item.sold_units > 0 ? 'text-success' : 'text-error'">
                  {{ item.sold_units }} unds
                </div>
                <div class="text-super-xs text-medium-emphasis">Fact: {{ formatCurrency(item.total_sales) }}</div>
              </VCol>
              <VCol cols="12" class="pa-2.5 bg-error-lighten-5 d-flex justify-space-between align-center">
                <div>
                  <span class="text-super-xs text-error font-weight-black text-uppercase d-block leading-tight">Total Capital Parado</span>
                  <span class="text-super-xs text-medium-emphasis">Dinero en bodega</span>
                </div>
                <div class="text-h6 font-weight-black text-error leading-none">
                  {{ formatCurrency(item.inventory_value) }}
                </div>
              </VCol>
            </VRow>
          </div>

          <!-- Métricas en grilla completa -->
          <div v-else class="metrics-grid rounded border-dashed-thin bg-var-theme-background">
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
                <div class="text-sm font-weight-black" :class="(item.margin_percentage ?? 0) >= 0 ? 'text-success' : 'text-error'">
                  {{ typeof item.margin_percentage === 'number' ? item.margin_percentage.toFixed(2) : item.margin_percentage }}%
                </div>
                <div class="text-super-xs text-medium-emphasis">
                  {{ (item.margin_amount ?? 0) > 0 ? '+' : '' }}{{ formatCurrency(item.margin_amount) }}
                </div>
              </VCol>
              <VCol cols="6" class="pa-2 border-r border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">ROI Anual</div>
                <div class="text-sm font-weight-black" :class="getGmroiColor(item.gmroi)">
                  {{ item.gmroi >= 9999 ? 'MAX' : Math.round(item.gmroi) + '%' }}
                </div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black">Cobertura</span>
                </div>
                <div class="text-sm font-weight-black" :class="item.inventory_days < 10 || item.current_stock === 0 ? 'text-error' : 'text-high-emphasis'">
                  {{ item.inventory_days === 9999 ? 'Sin rotación' : Math.round(item.inventory_days) + ' días' }}
                </div>
                <div class="text-super-xs font-weight-medium text-medium-emphasis">
                  {{ item.current_stock }} unds | {{ formatCurrency(item.inventory_value ?? (item.current_stock * (item.last_cost ?? 0))) }}
                </div>
              </VCol>
            </VRow>
          </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="d-flex border-t border-opacity-10">
          <VBtn 
            color="warning" 
            variant="text" 
            class="rounded-0 text-caption font-weight-bold flex-grow-1 border-r border-opacity-10" 
            height="40"
            @click="emit('openOffer', item)"
          >
            <VIcon icon="tabler-tag" size="16" class="me-1" />
            Oferta
          </VBtn>
          <VBtn 
            color="info" 
            variant="text" 
            class="rounded-0 text-caption font-weight-bold flex-grow-1 border-r border-opacity-10" 
            height="40"
            @click="emit('openAssign', item)"
          >
            <VIcon icon="tabler-user-plus" size="16" class="me-1" />
            Asignar
          </VBtn>
          <VBtn 
            :href="'/inventory/traceability?q=' + item.id" 
            target="_blank"
            color="primary" 
            variant="text" 
            class="rounded-0 text-caption font-weight-bold flex-grow-1" 
            height="40"
          >
            <VIcon icon="tabler-history" size="16" class="me-1" />
            Trazabilidad
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

.abc-badge-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.5px;
  line-height: 1.2;
}

.bg-error-lighten-5 {
  background-color: rgba(var(--v-theme-error), 0.08) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
