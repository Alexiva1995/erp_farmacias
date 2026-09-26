<script setup>
// Componente: Venta Cruzada (Cross-selling) con Nivel de Confianza y Soporte Estadístico
const props = defineProps({
  crossSelling: { type: Array,   default: () => [] },
  page:         { type: Number,  default: 1        },
  loading:      { type: Boolean, default: false    },
});

const emit = defineEmits(['page-change']);

const hasMore = () => props.crossSelling.length >= 8;

const getConfidenceColor = (conf) => {
  if (conf >= 60) return 'success';
  if (conf >= 35) return 'primary';
  return 'info';
};
</script>

<template>
  <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
    <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between bg-surface">
      <div class="d-flex align-center">
        <VAvatar size="32" color="primary" variant="tonal" class="me-2 rounded">
          <VIcon icon="tabler-arrows-cross" size="18" />
        </VAvatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis">Venta Cruzada (Market Basket)</div>
          <div class="text-super-xs text-medium-emphasis">Asociación de productos frecuentes y confianza de compra conjunta</div>
        </div>
      </div>
      <VChip color="primary" size="x-small" variant="flat" label class="font-weight-bold">
        Afiliación & Co-ocurrencia
      </VChip>
    </VCardTitle>

    <VCardText class="pa-0">
      <!-- Skeleton -->
      <div v-if="loading" class="skeleton-pulse pa-4">
        <div v-for="i in 4" :key="i" class="d-flex align-center justify-space-between mb-4 pb-2 border-b">
          <div class="d-flex gap-3 align-center flex-grow-1">
            <div style="flex: 1;">
              <div class="skeleton-line w-75 mb-2" />
              <div class="skeleton-line w-50" />
            </div>
            <div class="skeleton-avatar d-flex align-center justify-center" style="width: 24px; height: 24px;">
              <VIcon icon="tabler-plus" size="14" class="opacity-30" />
            </div>
            <div style="flex: 1;">
              <div class="skeleton-line w-75 mb-2" />
              <div class="skeleton-line w-50" />
            </div>
          </div>
          <div class="skeleton-line ms-4" style="width: 60px; height: 24px; border-radius: 4px;" />
        </div>
      </div>

      <!-- Estado vacío -->
      <div v-else-if="!crossSelling.length" class="text-center pa-10 text-medium-emphasis">
        <VIcon icon="tabler-arrows-left-right" size="36" class="mb-2 opacity-30" />
        <div class="text-sm font-weight-bold">No se han detectado asociaciones frecuentes</div>
        <div class="text-xs text-disabled">No hay coincidencias de productos vendidos juntos en este período.</div>
      </div>

      <!-- Tabla -->
      <VTable v-else density="compact" class="cross-selling-table">
        <thead>
          <tr>
            <th class="text-left font-weight-bold text-caption text-uppercase">Asociación de Productos (A + B)</th>
            <th class="text-right font-weight-bold text-caption text-uppercase" style="width: 170px;">Confianza / Frecuencia</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(pair, idx) in crossSelling" :key="idx" class="border-b">
            <td class="py-3 px-3">
              <div class="d-flex align-center gap-2">
                <!-- Producto A -->
                <div class="d-flex flex-column min-width-0" style="flex: 1;">
                  <span class="text-xs font-weight-bold text-uppercase text-truncate mb-1" :title="pair.product_a">
                    {{ pair.product_a }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span class="text-medium-emphasis font-weight-bold">ID: {{ pair.product_id_a }}</span>
                    <span class="text-disabled">·</span>
                    <span class="text-medium-emphasis text-truncate" style="max-width: 100px;">
                      {{ pair.ingredient_a || 'S/PA' }}
                    </span>
                    <span class="text-disabled">·</span>
                    <span class="text-primary font-weight-medium text-uppercase text-truncate" style="max-width: 80px;">
                      {{ pair.lab_a || 'S/L' }}
                    </span>
                  </div>
                </div>

                <div class="d-flex align-center justify-center text-medium-emphasis px-1">
                  <VIcon icon="tabler-plus" size="14" />
                </div>

                <!-- Producto B -->
                <div class="d-flex flex-column min-width-0" style="flex: 1;">
                  <span class="text-xs font-weight-bold text-uppercase text-truncate mb-1" :title="pair.product_b">
                    {{ pair.product_b }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span class="text-medium-emphasis font-weight-bold">ID: {{ pair.product_id_b }}</span>
                    <span class="text-disabled">·</span>
                    <span class="text-medium-emphasis text-truncate" style="max-width: 100px;">
                      {{ pair.ingredient_b || 'S/PA' }}
                    </span>
                    <span class="text-disabled">·</span>
                    <span class="text-primary font-weight-medium text-uppercase text-truncate" style="max-width: 80px;">
                      {{ pair.lab_b || 'S/L' }}
                    </span>
                  </div>
                </div>
              </div>
            </td>

            <td class="text-right px-3">
              <div class="d-flex flex-column align-end">
                <div class="d-flex align-center gap-2">
                  <VChip
                    :color="getConfidenceColor(pair.confidence_percent ?? 40)"
                    class="font-weight-black text-super-xs"
                    size="x-small"
                    variant="tonal"
                    label
                  >
                    {{ pair.confidence_percent ?? 40 }}% Confianza
                  </VChip>
                  <span class="text-xs font-weight-black text-high-emphasis">
                    {{ pair.frequency }} <span class="text-super-xs font-weight-normal text-medium-emphasis">veces</span>
                  </span>
                </div>
                <div class="w-100 mt-1 d-flex align-center justify-end" style="max-width: 120px;">
                  <VProgressLinear
                    :model-value="pair.confidence_percent ?? 40"
                    :color="getConfidenceColor(pair.confidence_percent ?? 40)"
                    height="4"
                    rounded
                  />
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>

      <VDivider />
      <div class="pa-2 px-4 d-flex align-center justify-space-between bg-surface">
        <span class="text-xs text-medium-emphasis">Página {{ page }}</span>
        <div class="d-flex gap-1">
          <VBtn
            icon="tabler-chevron-left"
            size="x-small"
            variant="tonal"
            :disabled="page <= 1 || loading"
            @click="emit('page-change', page - 1)"
          />
          <VBtn
            icon="tabler-chevron-right"
            size="x-small"
            variant="tonal"
            :disabled="!hasMore() || loading"
            @click="emit('page-change', page + 1)"
          />
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.7rem !important;
  line-height: 1.2;
}

.skeleton-pulse { animation: pulse 1.5s infinite ease-in-out; }
@keyframes pulse {
  0%   { opacity: 0.6; }
  50%  { opacity: 1;   }
  100% { opacity: 0.6; }
}
.skeleton-avatar { width: 32px; height: 32px; border-radius: 50%; background-color: rgba(var(--v-theme-on-surface), 0.1); }
.skeleton-line   { height: 12px; background-color: rgba(var(--v-theme-on-surface), 0.1); border-radius: 4px; }
</style>
