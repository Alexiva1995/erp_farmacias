<script setup>
// Componente: Ranking TOP Productos (Volumen y Venta Bruta)
import { computed } from 'vue';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const { formatCurrency } = useCurrencyConverter();

const props = defineProps({
  // Datos de volumen
  topVolume: { type: Array, default: () => [] },
  volumePage: { type: Number, default: 1 },
  loadingVolume: { type: Boolean, default: false },
  // Datos de ingresos
  topRevenue: { type: Array, default: () => [] },
  revenuePage: { type: Number, default: 1 },
  loadingRevenue: { type: Boolean, default: false },
});

const emit = defineEmits(['page-volume', 'page-revenue']);

// Posición absoluta según página
const volOffset  = computed(() => (props.volumePage  - 1) * 10);
const revOffset  = computed(() => (props.revenuePage - 1) * 10);

// ¿Hay más páginas?
const hasMoreVolume  = computed(() => props.topVolume.length  >= 10);
const hasMoreRevenue = computed(() => props.topRevenue.length >= 10);
</script>

<template>
  <VRow>
    <!-- TOP por Volumen -->
    <VCol cols="12" md="6">
      <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
        <VCardTitle class="pa-4 border-b d-flex align-center bg-light-primary">
          <VIcon icon="tabler-package" class="me-2 text-primary" />
          <span class="text-h6 font-weight-bold text-high-emphasis">TOP Productos (Volumen)</span>
        </VCardTitle>

        <VCardText class="pa-0">
          <!-- Cargador limpio -->
          <div v-if="loadingVolume" class="pa-8 text-center">
            <VProgressCircular indeterminate color="primary" size="32" width="2" class="mb-2" />
            <div class="text-xs text-primary font-weight-black">Cargando productos...</div>
          </div>

          <!-- Estado Vacío -->
          <div v-else-if="!topVolume.length" class="text-center pa-8 text-medium-emphasis">
            <VIcon icon="tabler-package-off" size="40" class="mb-2 opacity-30" />
            <div class="text-sm font-weight-bold">Sin registros de volumen</div>
            <div class="text-xs text-disabled">No hay ventas registradas en este periodo.</div>
          </div>

          <!-- Lista -->
          <div v-else>
            <VList lines="one" class="px-0">
              <VListItem
                v-for="(item, idx) in topVolume"
                :key="item?.id ? `vol-${item.id}` : `idxv-${idx}`"
                class="border-b px-2"
              >
                <template #prepend>
                  <VAvatar color="primary" variant="tonal" size="32" class="me-3 font-weight-black">
                    {{ volOffset + idx + 1 }}
                  </VAvatar>
                </template>

                <div class="d-flex flex-column min-width-0 py-2">
                  <span
                    class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                    style="max-width: 200px;"
                    :title="item?.name"
                  >
                    {{ item?.name || 'Desconocido' }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span class="text-primary font-weight-black">ID: {{ item?.id }}</span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-disabled text-truncate" style="max-width: 150px;">
                      {{ item?.active_ingredient || 'Sin principio activo' }}
                    </span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-width: 120px;">
                      {{ item?.laboratory_name || 'S/L' }}
                    </span>
                  </div>
                </div>

                <template #append>
                  <div class="text-right">
                    <div class="text-body-2 font-weight-bold text-primary">{{ Math.trunc(item?.total_sold ?? 0).toLocaleString() }} Unds</div>
                    <div class="text-super-xs text-medium-emphasis">Ventas realizadas</div>
                  </div>
                </template>
              </VListItem>
            </VList>
          </div>

          <VDivider />
          <div class="pa-2 d-flex align-center justify-space-between bg-light-primary">
            <span class="text-xs font-weight-medium ms-2">Página {{ volumePage }}</span>
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-chevron-left"
                size="small"
                variant="text"
                :disabled="volumePage <= 1 || loadingVolume"
                @click="emit('page-volume', volumePage - 1)"
              />
              <VBtn
                icon="tabler-chevron-right"
                size="small"
                variant="text"
                :disabled="!hasMoreVolume || loadingVolume"
                @click="emit('page-volume', volumePage + 1)"
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- TOP por Venta Bruta -->
    <VCol cols="12" md="6">
      <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
        <VCardTitle class="pa-4 border-b d-flex align-center bg-light-success">
          <VIcon icon="tabler-currency-dollar" class="me-2 text-success" />
          <span class="text-h6 font-weight-bold text-high-emphasis">TOP Productos (Venta Bruta)</span>
        </VCardTitle>

        <VCardText class="pa-0">
          <!-- Cargador limpio -->
          <div v-if="loadingRevenue" class="pa-8 text-center">
            <VProgressCircular indeterminate color="success" size="32" width="2" class="mb-2" />
            <div class="text-xs text-success font-weight-black">Cargando productos...</div>
          </div>

          <!-- Estado Vacío -->
          <div v-else-if="!topRevenue.length" class="text-center pa-8 text-medium-emphasis">
            <VIcon icon="tabler-package-off" size="40" class="mb-2 opacity-30" />
            <div class="text-sm font-weight-bold">Sin registros de venta bruta</div>
            <div class="text-xs text-disabled">No hay ventas registradas en este periodo.</div>
          </div>

          <!-- Lista -->
          <div v-else>
            <VList lines="one" class="px-0">
              <VListItem
                v-for="(item, idx) in topRevenue"
                :key="item?.id ? `rev-${item.id}` : `idxr-${idx}`"
                class="border-b px-2"
              >
                <template #prepend>
                  <VAvatar color="success" variant="tonal" size="32" class="me-3 font-weight-black">
                    {{ revOffset + idx + 1 }}
                  </VAvatar>
                </template>

                <div class="d-flex flex-column min-width-0 py-2">
                  <span
                    class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                    style="max-width: 200px;"
                    :title="item?.name"
                  >
                    {{ item?.name || 'Desconocido' }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span class="text-success font-weight-black">ID: {{ item?.id }}</span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-disabled text-truncate" style="max-width: 150px;">
                      {{ item?.active_ingredient || 'Sin principio activo' }}
                    </span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-success font-weight-black text-uppercase text-truncate" style="max-width: 120px;">
                      {{ item?.laboratory_name || 'S/L' }}
                    </span>
                  </div>
                </div>

                <template #append>
                  <div class="text-right">
                    <div class="text-body-2 font-weight-bold text-success">{{ formatCurrency(item?.total_revenue ?? 0) }}</div>
                    <div class="text-super-xs text-medium-emphasis">Total recaudado</div>
                  </div>
                </template>
              </VListItem>
            </VList>
          </div>

          <VDivider />
          <div class="pa-2 d-flex align-center justify-space-between bg-light-success">
            <span class="text-xs font-weight-medium ms-2">Página {{ revenuePage }}</span>
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-chevron-left"
                size="small"
                variant="text"
                :disabled="revenuePage <= 1 || loadingRevenue"
                @click="emit('page-revenue', revenuePage - 1)"
              />
              <VBtn
                icon="tabler-chevron-right"
                size="small"
                variant="text"
                :disabled="!hasMoreRevenue || loadingRevenue"
                @click="emit('page-revenue', revenuePage + 1)"
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.bg-light-primary { background-color: rgba(115, 103, 240, 0.15); }
.bg-light-success { background-color: rgba(40, 199, 111, 0.15); }
.text-super-xs { font-size: 0.65rem !important; line-height: 1; }
.text-xs      { font-size: 0.75rem !important; }
</style>
