<script setup>
// Componente: Venta Cruzada (Cross-selling) paginada
const props = defineProps({
  crossSelling: { type: Array,   default: () => [] },
  page:         { type: Number,  default: 1        },
  loading:      { type: Boolean, default: false    },
});

const emit = defineEmits(['page-change']);

const hasMore = () => props.crossSelling.length >= 8;
</script>

<template>
  <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
    <VCardTitle class="pa-4 border-b d-flex align-center">
      <span class="text-h6 font-weight-bold">Venta Cruzada (Cross-selling)</span>
      <VChip color="success" size="x-small" label class="ms-2">Parejas Frecuentes</VChip>
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
          <div class="skeleton-line ms-4" style="width: 40px; height: 24px; border-radius: 4px;" />
        </div>
      </div>

      <!-- Estado vacío -->
      <div v-else-if="!crossSelling.length" class="text-center pa-10 text-medium-emphasis">
        <VIcon icon="tabler-arrows-left-right" size="40" class="mb-2 opacity-30" />
        <div class="text-sm font-weight-bold">No se han detectado asociaciones frecuentes</div>
        <div class="text-xs text-disabled">No hay coincidencias de productos vendidos juntos en este periodo.</div>
      </div>

      <!-- Tabla -->
      <VTable v-else density="compact" class="cross-selling-table">
        <thead>
          <tr>
            <th class="text-left font-weight-black uppercase">Vínculo de Productos (A + B)</th>
            <th class="text-right font-weight-black uppercase">Frecuencia</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(pair, idx) in crossSelling" :key="idx">
            <td class="py-3 px-2">
              <div class="d-flex align-center gap-3">
                <!-- Producto A -->
                <div class="d-flex flex-column min-width-0" style="flex: 1;">
                  <span class="text-sm font-weight-black text-uppercase text-truncate mb-1" :title="pair.product_a">
                    {{ pair.product_a }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs font-weight-bold">
                    <span class="text-primary">ID: {{ pair.product_id_a }}</span>
                    <span class="opacity-30">|</span>
                    <span class="text-medium-emphasis uppercase text-truncate" style="max-width: 120px;">
                      {{ pair.ingredient_a || 'S/PA' }}
                    </span>
                    <span class="opacity-30">|</span>
                    <span class="text-primary uppercase text-truncate" style="max-width: 100px;">
                      {{ pair.lab_a || 'S/L' }}
                    </span>
                  </div>
                </div>

                <div class="d-flex align-center justify-center" style="width: 30px;">
                  <VIcon icon="tabler-plus" size="18" color="primary" />
                </div>

                <!-- Producto B -->
                <div class="d-flex flex-column min-width-0" style="flex: 1;">
                  <span class="text-sm font-weight-black text-uppercase text-truncate mb-1" :title="pair.product_b">
                    {{ pair.product_b }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs font-weight-bold">
                    <span class="text-primary">ID: {{ pair.product_id_b }}</span>
                    <span class="opacity-30">|</span>
                    <span class="text-medium-emphasis uppercase text-truncate" style="max-width: 120px;">
                      {{ pair.ingredient_b || 'S/PA' }}
                    </span>
                    <span class="opacity-30">|</span>
                    <span class="text-primary uppercase text-truncate" style="max-width: 100px;">
                      {{ pair.lab_b || 'S/L' }}
                    </span>
                  </div>
                </div>
              </div>
            </td>
            <td class="text-right px-2">
              <VChip color="primary" class="font-weight-black" size="small">{{ pair.frequency }}</VChip>
              <div class="text-super-xs text-medium-emphasis mt-1">Juntos</div>
            </td>
          </tr>
        </tbody>
      </VTable>

      <VDivider />
      <div class="pa-2 d-flex align-center justify-space-between bg-light-primary">
        <span class="text-xs font-weight-medium ms-2">Página {{ page }}</span>
        <div class="d-flex gap-1">
          <VBtn
            icon="tabler-chevron-left"
            size="small"
            variant="text"
            :disabled="page <= 1 || loading"
            @click="emit('page-change', page - 1)"
          />
          <VBtn
            icon="tabler-chevron-right"
            size="small"
            variant="text"
            :disabled="!hasMore() || loading"
            @click="emit('page-change', page + 1)"
          />
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.bg-light-primary { background-color: rgba(115, 103, 240, 0.15); }
.text-super-xs { font-size: 0.65rem !important; line-height: 1; }
.text-xs      { font-size: 0.75rem !important; }

.skeleton-pulse { animation: pulse 1.5s infinite ease-in-out; }
@keyframes pulse {
  0%   { opacity: 0.6; }
  50%  { opacity: 1;   }
  100% { opacity: 0.6; }
}
.skeleton-avatar { width: 32px; height: 32px; border-radius: 50%; background-color: rgba(var(--v-theme-on-surface), 0.1); }
.skeleton-line   { height: 12px; background-color: rgba(var(--v-theme-on-surface), 0.1); border-radius: 4px; }
</style>
