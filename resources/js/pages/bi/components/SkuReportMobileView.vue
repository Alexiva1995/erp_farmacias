<script setup>
defineProps({
  skus: { type: Array, default: () => [] },
  loading: Boolean,
  page: Number,
  itemsPerPage: Number
});

const emit = defineEmits(['update:page']);

const getSemaphoreColor = (status) => {
  const mapping = { verde: 'success', amarillo: 'warning', rojo: 'error', negro: 'dark' };
  return mapping[status] || 'default';
};

const getSemaphoreLabel = (status) => {
  const mapping = { verde: 'Rentable', amarillo: 'Medio', rojo: 'Peligro', negro: 'Pérdidas' };
  return mapping[status] || status;
};

const formatPercent = (val) => Number(val || 0).toFixed(2) + '%';
const formatMoney = (val) => '$' + Number(val || 0).toFixed(2);
</script>

<template>
  <div class="d-md-none">
    <VProgressLinear v-if="loading" indeterminate color="primary" />
    <div v-if="skus.length === 0 && !loading" class="text-center pa-8 text-medium-emphasis">
      <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
      <p>Sin resultados para los filtros aplicados</p>
    </div>
    <div v-for="item in skus" :key="item.product_id || item.id" class="px-2 py-1">
      <VCard variant="flat" class="product-mobile-card border mb-2">
        <div class="pa-3">
          <div class="d-flex align-start justify-space-between gap-2">
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-center gap-1 mb-1">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                  <span class="text-primary text-xs">#{{ item.product_id || item.id }}</span>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.product_name }}
                </h3>
              </div>
              <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory_name || 'S/L' }}
                </span>
              </div>
            </div>
            <VChip
              :color="getSemaphoreColor(item.semaphore)"
              class="text-uppercase font-weight-black flex-shrink-0"
              variant="elevated"
              size="x-small"
              label
            >
              {{ getSemaphoreLabel(item.semaphore) }}
            </VChip>
          </div>

          <VDivider class="my-3 border-opacity-10" />

          <div class="metrics-grid rounded border-dashed-thin bg-var-theme-background">
            <VRow dense class="ma-0">
              <VCol cols="6" class="pa-2 border-r border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Costo Unit.</div>
                <div class="text-sm font-weight-black">{{ formatMoney(item.current_cost) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2 border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Precio Lista / Venta</div>
                <div class="text-sm font-weight-black">{{ formatMoney(item.list_price) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2 border-r border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1 text-info">Margen Bruto</div>
                <div class="text-sm font-weight-black text-info">{{ formatPercent(item.gross_margin_percent) }}</div>
                <div class="text-super-xs text-disabled" v-if="item.discount_avg_percent > 0">Desc: -{{ formatPercent(item.discount_avg_percent) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2 border-b border-opacity-10">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1 text-primary">Margen Neto</div>
                <div class="text-sm font-weight-black text-primary">{{ formatPercent(item.net_margin_percent) }}</div>
                <div class="text-super-xs text-disabled" :class="{'text-error': item.loss_value > 0}">Mermas: {{ formatMoney(item.loss_value) }}</div>
              </VCol>
              <VCol cols="12" class="pa-2 d-flex justify-space-between align-center">
                <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">M. Real Efectivo</div>
                <div class="text-base font-weight-black" :class="`text-${getSemaphoreColor(item.semaphore)}`">
                  {{ formatPercent(item.real_margin_percent) }}
                </div>
              </VCol>
            </VRow>
          </div>
        </div>

        <div class="d-flex border-t border-opacity-10">
          <VBtn 
            :href="'/inventory/traceability?q=' + (item.product_id || item.id)" 
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
      <VBtn icon variant="text" size="32" :disabled="skus.length < itemsPerPage" @click="emit('update:page', page + 1)">
        <VIcon icon="tabler-chevron-right" size="18" />
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.6rem !important;
  line-height: 1.1;
}
</style>
