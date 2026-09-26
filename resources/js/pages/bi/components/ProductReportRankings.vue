<script setup>
// Componente: Ranking TOP Productos (Volumen, Venta Bruta y Rentabilidad) — Rediseño Corporativo
import { computed, ref } from 'vue';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const { formatCurrency } = useCurrencyConverter();

const props = defineProps({
  // Datos de volumen
  topVolume: { type: Array, default: () => [] },
  volumePage: { type: Number, default: 1 },
  loadingVolume: { type: Boolean, default: false },
  // Datos de ingresos / rentabilidad
  topRevenue: { type: Array, default: () => [] },
  revenuePage: { type: Number, default: 1 },
  loadingRevenue: { type: Boolean, default: false },
});

const emit = defineEmits(['page-volume', 'page-revenue']);

// Posición absoluta según página
const volOffset = computed(() => (props.volumePage - 1) * 10);
const revOffset = computed(() => (props.revenuePage - 1) * 10);

// ¿Hay más páginas?
const hasMoreVolume  = computed(() => props.topVolume.length >= 10);
const hasMoreRevenue = computed(() => props.topRevenue.length >= 10);

// Cálculo seguro de % de margen bruto
const getMarginPercent = (revenue, margin) => {
  const rev = Number(revenue ?? 0);
  const mgn = Number(margin ?? 0);
  if (rev <= 0) return 0;
  return Math.round((mgn / rev) * 100);
};

const getBadgeColor = (rank) => {
  if (rank === 1) return 'primary';
  if (rank === 2) return 'info';
  if (rank === 3) return 'warning';
  return 'secondary';
};
</script>

<template>
  <VRow>
    <!-- TOP por Volumen -->
    <VCol cols="12" md="6">
      <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm ranking-card">
        <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between bg-surface">
          <div class="d-flex align-center">
            <VAvatar size="32" color="primary" variant="tonal" class="me-2 rounded">
              <VIcon icon="tabler-package" size="18" />
            </VAvatar>
            <div>
              <div class="text-subtitle-1 font-weight-bold text-high-emphasis">TOP Productos por Demanda</div>
              <div class="text-super-xs text-medium-emphasis">Ranking clasificado por volumen de unidades vendidas</div>
            </div>
          </div>
          <VChip size="x-small" color="primary" variant="flat" label class="font-weight-bold">
            Volumen
          </VChip>
        </VCardTitle>

        <VCardText class="pa-0">
          <!-- Cargador -->
          <div v-if="loadingVolume" class="pa-8 text-center">
            <VProgressCircular indeterminate color="primary" size="32" width="2" class="mb-2" />
            <div class="text-xs text-primary font-weight-bold">Cargando productos...</div>
          </div>

          <!-- Estado Vacío -->
          <div v-else-if="!topVolume.length" class="text-center pa-8 text-medium-emphasis">
            <VIcon icon="tabler-package-off" size="36" class="mb-2 opacity-30" />
            <div class="text-sm font-weight-bold">Sin registros de volumen</div>
            <div class="text-xs text-disabled">No hay ventas registradas en este período.</div>
          </div>

          <!-- Lista -->
          <div v-else>
            <VList lines="one" class="px-0 py-0">
              <VListItem
                v-for="(item, idx) in topVolume"
                :key="item?.id ? `vol-${item.id}` : `idxv-${idx}`"
                class="border-b px-4 py-2 ranking-item"
              >
                <template #prepend>
                  <VAvatar
                    :color="getBadgeColor(volOffset + idx + 1)"
                    variant="tonal"
                    size="28"
                    class="me-3 font-weight-black text-caption"
                  >
                    {{ volOffset + idx + 1 }}
                  </VAvatar>
                </template>

                <div class="d-flex flex-column min-width-0">
                  <span
                    class="text-sm font-weight-bold text-high-emphasis text-uppercase text-truncate"
                    style="max-width: 230px;"
                    :title="item?.name"
                  >
                    {{ item?.name || 'Desconocido' }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span class="text-medium-emphasis font-weight-bold">ID: {{ item?.id }}</span>
                    <span class="text-disabled">·</span>
                    <span class="text-medium-emphasis text-truncate" style="max-width: 130px;">
                      {{ item?.active_ingredient || 'Sin principio activo' }}
                    </span>
                    <span class="text-disabled">·</span>
                    <span class="text-primary font-weight-medium text-uppercase text-truncate" style="max-width: 110px;">
                      {{ item?.laboratory_name || 'S/L' }}
                    </span>
                  </div>
                </div>

                <template #append>
                  <div class="text-right">
                    <div class="text-subtitle-2 font-weight-black text-high-emphasis">
                      {{ Math.trunc(item?.total_sold ?? 0).toLocaleString() }} <span class="text-caption font-weight-normal text-medium-emphasis">Unds</span>
                    </div>
                    <div class="text-super-xs text-medium-emphasis">
                      Venta: {{ formatCurrency(item?.total_revenue ?? 0) }}
                    </div>
                  </div>
                </template>
              </VListItem>
            </VList>
          </div>

          <VDivider />
          <div class="pa-2 px-4 d-flex align-center justify-space-between bg-surface">
            <span class="text-xs text-medium-emphasis">Página {{ volumePage }}</span>
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-chevron-left"
                size="x-small"
                variant="tonal"
                :disabled="volumePage <= 1 || loadingVolume"
                @click="emit('page-volume', volumePage - 1)"
              />
              <VBtn
                icon="tabler-chevron-right"
                size="x-small"
                variant="tonal"
                :disabled="!hasMoreVolume || loadingVolume"
                @click="emit('page-volume', volumePage + 1)"
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- TOP por Venta Bruta & Rentabilidad -->
    <VCol cols="12" md="6">
      <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm ranking-card">
        <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between bg-surface">
          <div class="d-flex align-center">
            <VAvatar size="32" color="success" variant="tonal" class="me-2 rounded">
              <VIcon icon="tabler-currency-dollar" size="18" />
            </VAvatar>
            <div>
              <div class="text-subtitle-1 font-weight-bold text-high-emphasis">TOP Facturación y Margen</div>
              <div class="text-super-xs text-medium-emphasis">Ranking por recaudación con contexto de rentabilidad bruta</div>
            </div>
          </div>
          <VChip size="x-small" color="success" variant="flat" label class="font-weight-bold">
            Ingresos & Margen
          </VChip>
        </VCardTitle>

        <VCardText class="pa-0">
          <!-- Cargador -->
          <div v-if="loadingRevenue" class="pa-8 text-center">
            <VProgressCircular indeterminate color="success" size="32" width="2" class="mb-2" />
            <div class="text-xs text-success font-weight-bold">Cargando productos...</div>
          </div>

          <!-- Estado Vacío -->
          <div v-else-if="!topRevenue.length" class="text-center pa-8 text-medium-emphasis">
            <VIcon icon="tabler-package-off" size="36" class="mb-2 opacity-30" />
            <div class="text-sm font-weight-bold">Sin registros financieros</div>
            <div class="text-xs text-disabled">No hay ventas registradas en este período.</div>
          </div>

          <!-- Lista -->
          <div v-else>
            <VList lines="one" class="px-0 py-0">
              <VListItem
                v-for="(item, idx) in topRevenue"
                :key="item?.id ? `rev-${item.id}` : `idxr-${idx}`"
                class="border-b px-4 py-2 ranking-item"
              >
                <template #prepend>
                  <VAvatar
                    :color="getBadgeColor(revOffset + idx + 1)"
                    variant="tonal"
                    size="28"
                    class="me-3 font-weight-black text-caption"
                  >
                    {{ revOffset + idx + 1 }}
                  </VAvatar>
                </template>

                <div class="d-flex flex-column min-width-0">
                  <span
                    class="text-sm font-weight-bold text-high-emphasis text-uppercase text-truncate"
                    style="max-width: 220px;"
                    :title="item?.name"
                  >
                    {{ item?.name || 'Desconocido' }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span class="text-medium-emphasis font-weight-bold">ID: {{ item?.id }}</span>
                    <span class="text-disabled">·</span>
                    <span class="text-medium-emphasis text-truncate" style="max-width: 120px;">
                      {{ item?.active_ingredient || 'Sin principio activo' }}
                    </span>
                    <span class="text-disabled">·</span>
                    <span class="text-primary font-weight-medium text-uppercase text-truncate" style="max-width: 110px;">
                      {{ item?.laboratory_name || 'S/L' }}
                    </span>
                  </div>
                </div>

                <template #append>
                  <div class="text-right d-flex flex-column align-end">
                    <div class="d-flex align-center gap-2">
                      <VChip
                        size="x-small"
                        :color="getMarginPercent(item?.total_revenue, item?.total_margin) >= 25 ? 'success' : 'warning'"
                        variant="tonal"
                        label
                        class="font-weight-black text-super-xs"
                      >
                        {{ getMarginPercent(item?.total_revenue, item?.total_margin) }}% Mgn
                      </VChip>
                      <div class="text-subtitle-2 font-weight-black text-high-emphasis">
                        {{ formatCurrency(item?.total_revenue ?? 0) }}
                      </div>
                    </div>
                    <div class="text-super-xs text-medium-emphasis mt-1">
                      Margen: <strong class="text-success">{{ formatCurrency(item?.total_margin ?? 0) }}</strong> ({{ Math.trunc(item?.total_sold ?? 0).toLocaleString() }} unds)
                    </div>
                  </div>
                </template>
              </VListItem>
            </VList>
          </div>

          <VDivider />
          <div class="pa-2 px-4 d-flex align-center justify-space-between bg-surface">
            <span class="text-xs text-medium-emphasis">Página {{ revenuePage }}</span>
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-chevron-left"
                size="x-small"
                variant="tonal"
                :disabled="revenuePage <= 1 || loadingRevenue"
                @click="emit('page-revenue', revenuePage - 1)"
              />
              <VBtn
                icon="tabler-chevron-right"
                size="x-small"
                variant="tonal"
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
.text-super-xs {
  font-size: 0.7rem !important;
  line-height: 1.2;
}
.ranking-item:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}
</style>
