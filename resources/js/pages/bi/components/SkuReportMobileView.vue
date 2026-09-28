<script setup>
defineProps({
  skus: { type: Array, default: () => [] },
  loading: Boolean,
  page: Number,
  itemsPerPage: Number
});

const emit = defineEmits(['update:page']);

const getSemaphoreColor = (status) => {
  if (!status) return 'warning';
  const s = String(status).toLowerCase().trim();
  const mapping = {
    verde: 'success',
    green: 'success',
    rentable: 'success',
    amarillo: 'warning',
    yellow: 'warning',
    medio: 'warning',
    rojo: 'error',
    red: 'error',
    peligro: 'error',
    danger: 'error',
    negro: 'secondary',
    black: 'secondary',
    perdidas: 'secondary',
    'pérdidas': 'secondary',
    critico: 'error',
    'crítico': 'error',
  };
  return mapping[s] || 'warning';
};

const getSemaphoreLabel = (status) => {
  if (!status) return 'N/A';
  const s = String(status).toLowerCase().trim();
  const mapping = {
    verde: 'Rentable',
    green: 'Rentable',
    rentable: 'Rentable',
    amarillo: 'Medio',
    yellow: 'Medio',
    medio: 'Medio',
    rojo: 'Peligro',
    red: 'Peligro',
    peligro: 'Peligro',
    danger: 'Peligro',
    negro: 'Pérdidas',
    black: 'Pérdidas',
    perdidas: 'Pérdidas',
    'pérdidas': 'Pérdidas',
    critico: 'Crítico',
    'crítico': 'Crítico',
  };
  return mapping[s] || status;
};

const formatPercent = (val) => Number(val || 0).toFixed(2) + '%';
const formatMoney = (val) => '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
  <div class="d-md-none pa-3">
    <!-- Estado de Carga Móvil con Skeleton -->
    <div v-if="loading" class="d-flex flex-column ga-3">
      <VSkeletonLoader v-for="n in 3" :key="n" type="card" class="rounded-lg" />
    </div>

    <!-- Estado Vacío -->
    <div v-else-if="skus.length === 0" class="text-center pa-8 text-medium-emphasis">
      <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
      <p class="text-body-1 font-weight-medium">Sin resultados para los filtros aplicados</p>
    </div>

    <!-- Listado de Tarjetas -->
    <div v-else>
      <VCard
        v-for="item in skus"
        :key="item.product_id || item.id"
        border
        class="product-mobile-card rounded-lg mb-3 shadow-sm bg-surface overflow-hidden"
      >
        <div class="pa-4">
          <div class="d-flex align-start justify-space-between ga-2 mb-2">
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-center ga-1 mb-1">
                <span class="text-caption font-weight-bold text-primary">#{{ item.product_id || item.id }}</span>
                <span class="text-disabled">|</span>
                <span class="text-body-2 font-weight-bold text-high-emphasis text-uppercase text-truncate">
                  {{ item.product_name }}
                </span>
              </div>
              <div class="d-flex align-center flex-wrap ga-x-2 text-caption text-medium-emphasis">
                <span>{{ item.active_ingredient || 'Sin molécula' }}</span>
                <span class="text-disabled">•</span>
                <span class="text-primary font-weight-medium">{{ item.laboratory_name || 'S/L' }}</span>
              </div>
            </div>
            <VChip
              :color="getSemaphoreColor(item.semaphore)"
              class="text-uppercase font-weight-bold flex-shrink-0"
              variant="tonal"
              size="small"
            >
              {{ getSemaphoreLabel(item.semaphore) }}
            </VChip>
          </div>

          <VDivider class="my-3 opacity-10" />

          <!-- Rejilla Financiera -->
          <div class="rounded-lg border pa-2 bg-var-theme-background">
            <VRow dense>
              <VCol cols="6" class="pa-2">
                <div class="text-caption text-disabled text-uppercase font-weight-bold">Stock Actual</div>
                <div class="text-body-2 font-weight-bold" :class="Number(item.current_stock) <= 0 ? 'text-error' : ''">
                  {{ Number(item.current_stock || 0).toFixed(0) }} uds
                </div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="text-caption text-disabled text-uppercase font-weight-bold">Costo Unit.</div>
                <div class="text-body-2 font-weight-bold">{{ formatMoney(item.current_cost) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="text-caption text-disabled text-uppercase font-weight-bold">Precio Lista</div>
                <div class="text-body-2 font-weight-bold">{{ formatMoney(item.list_price) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="text-caption text-info text-uppercase font-weight-bold">Margen Bruto</div>
                <div class="text-body-2 font-weight-bold text-info">{{ formatPercent(item.gross_margin_percent) }}</div>
                <div class="text-caption text-medium-emphasis">{{ formatMoney(item.gross_margin_value) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="text-caption text-primary text-uppercase font-weight-bold">Margen Neto</div>
                <div class="text-body-2 font-weight-bold text-primary">{{ formatPercent(item.net_margin_percent) }}</div>
                <div class="text-caption text-medium-emphasis">{{ formatMoney(item.net_margin_value) }}</div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="text-caption text-error text-uppercase font-weight-bold">Descuentos</div>
                <div class="text-body-2 font-weight-bold text-error">
                  {{ Number(item.discount_avg_percent) > 0 ? '-' + formatPercent(item.discount_avg_percent) : '0.00%' }}
                </div>
              </VCol>
              <VCol cols="6" class="pa-2">
                <div class="text-caption text-error text-uppercase font-weight-bold">Mermas / Venc.</div>
                <div class="text-body-2 font-weight-bold" :class="Number(item.loss_value) > 0 ? 'text-error' : 'text-medium-emphasis'">
                  -{{ formatMoney(item.loss_value) }}
                </div>
              </VCol>
              <VCol cols="12">
                <VDivider class="my-1 opacity-20" />
                <div class="d-flex justify-space-between align-center pt-1">
                  <div>
                    <div class="text-caption text-disabled text-uppercase font-weight-bold">Margen Real Efectivo</div>
                    <div class="text-caption text-medium-emphasis">{{ formatMoney(item.real_margin_value) }} Ganancia Real</div>
                  </div>
                  <div
                    class="text-h6 font-weight-black"
                    :class="Number(item.real_margin_percent) >= 0 ? 'text-success' : 'text-error'"
                  >
                    {{ formatPercent(item.real_margin_percent) }}
                  </div>
                </div>
              </VCol>
            </VRow>
          </div>
        </div>

        <div class="d-flex border-t">
          <VBtn 
            :href="'/inventory/traceability?q=' + (item.product_id || item.id)" 
            target="_blank"
            block 
            color="primary" 
            variant="text" 
            class="rounded-0 text-caption font-weight-bold" 
            height="42"
          >
            <VIcon icon="tabler-history" size="18" class="me-2" />
            Ver Trazabilidad de Inventario
          </VBtn>
        </div>
      </VCard>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center align-center py-3 ga-3">
        <VBtn
          icon
          variant="tonal"
          size="36"
          :disabled="page <= 1"
          @click="emit('update:page', page - 1)"
        >
          <VIcon icon="tabler-chevron-left" size="20" />
        </VBtn>
        <span class="text-caption font-weight-bold text-medium-emphasis">Página {{ page }}</span>
        <VBtn
          icon
          variant="tonal"
          size="36"
          :disabled="skus.length < itemsPerPage"
          @click="emit('update:page', page + 1)"
        >
          <VIcon icon="tabler-chevron-right" size="20" />
        </VBtn>
      </div>
    </div>
  </div>
</template>
