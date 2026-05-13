<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed } from "vue";

const props = defineProps({
  cashClosureData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["requestCloseCash"]);

const currencies = computed(() => {
  // Aseguramos que data sea un objeto y manejamos si llega un array por error
  const rawData = props.cashClosureData;
  const data = (Array.isArray(rawData) ? rawData[0] : rawData) || {};
  
  return [
    {
      label: 'Venta USD',
      amount: data.total_usd || "0.00",
      amountUSD: parseFloat(data.total_usd) || 0,
      approxUSD: null,
      currency: 'USD',
      color: 'success',
      icon: 'tabler-currency-dollar',
      barColor: '#28C76F',
    },
    {
      label: 'Venta Bs',
      amount: data.total_bs || "0.00",
      amountUSD: parseFloat(data.total_bs_in_usd) || 0,
      approxUSD: parseFloat(data.total_bs_in_usd) || 0,
      currency: 'BS',
      color: 'warning',
      icon: 'tabler-cash',
      barColor: '#FF9F43',
    },
    {
      label: 'Venta COP',
      amount: data.total_cop || "0.00",
      amountUSD: parseFloat(data.total_cop_in_usd) || 0,
      approxUSD: parseFloat(data.total_cop_in_usd) || 0,
      currency: 'COP',
      color: 'info',
      icon: 'tabler-coin',
      barColor: '#00CFE8',
    },
    {
      label: 'Cuentas por Cobrar',
      amount: data.total_global_debt || "0.00",
      amountUSD: 0, // No lo sumamos al total de venta de la sesión
      approxUSD: null,
      currency: 'USD',
      color: 'error',
      icon: 'tabler-credit-card',
      barColor: '#EA5455',
    },
  ];
});

// Total en USD equivalente (para la barra)
const grandTotal = computed(() => currencies.value.reduce((sum, c) => sum + c.amountUSD, 0));

const barItems = computed(() => {
  if (grandTotal.value === 0) return [];
  return currencies.value.map(c => ({
    ...c,
    pct: (c.amountUSD / grandTotal.value) * 100,
  }));
});

const isBlind = computed(() => {
  const rawData = props.cashClosureData;
  const data = (Array.isArray(rawData) ? rawData[0] : rawData) || {};
  return !!data.blind_cash_closure;
});

const hasData = computed(() => grandTotal.value > 0);
</script>

<template>
  <VCard class="border shadow-sm rounded-lg overflow-hidden" elevation="0">
    <VCardItem class="pa-5 pb-0">
      <template #prepend>
        <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg shadow-sm">
          <VIcon icon="tabler-report-money" size="24" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-black uppercase">
        {{ isBlind ? "Cierre de Caja Ciego" : "Resumen de Caja" }}
      </VCardTitle>
      <VCardSubtitle class="text-xs font-weight-medium text-disabled">
        {{ isBlind ? "Turno en curso" : "Acumulado de la jornada actual" }}
      </VCardSubtitle>
      <template #append>
        <VBtn icon variant="tonal" size="small" color="primary" class="rounded-lg shadow-sm">
          <VIcon size="20" icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList density="compact" class="rounded-lg">
              <VListItem value="closed_cash" @click="emit('requestCloseCash')">
                <template #prepend>
                  <VIcon icon="tabler-lock" size="18" class="mr-2" />
                </template>
                <VListItemTitle class="text-xs font-weight-bold uppercase">Cerrar Caja</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VBtn>
      </template>
    </VCardItem>

    <VCardText class="pa-5">
      <template v-if="isBlind">
        <VAlert type="info" variant="tonal" class="rounded-lg mb-0 font-weight-medium">
          La modalidad de cierre de caja ciego está activa. Sus reportes y totales acumulados de la jornada están ocultos. Para finalizar su turno y realizar la declaración de valores, haga clic en el botón de opciones en la esquina superior derecha y seleccione <strong>CERRAR CAJA</strong>.
        </VAlert>
      </template>
      <template v-else>
        <!-- Barra multimoneda Premium -->
        <div class="currency-bar-container mb-6">
          <div class="currency-bar rounded-lg shadow-inner overflow-hidden d-flex">
            <template v-if="hasData">
              <div
                v-for="item in barItems"
                :key="item.label"
                :style="{ width: item.pct + '%', backgroundColor: item.barColor }"
                class="d-flex align-center justify-center transition-all h-100"
              >
                <VTooltip location="top" :text="`${item.label}: ${item.pct.toFixed(1)}%`">
                  <template #activator="{ props: tooltip }">
                    <span v-bind="tooltip" v-if="item.pct > 5" class="text-super-xs font-weight-black text-white px-2">
                      {{ item.pct.toFixed(0) }}%
                    </span>
                  </template>
                </VTooltip>
              </div>
            </template>
            <template v-else>
              <div class="w-100 d-flex align-center justify-center bg-surface-variant-opacity-2 py-2">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Sin movimientos</span>
              </div>
            </template>
          </div>
        </div>

        <!-- Tarjetas por moneda Premium -->
        <VRow class="ma-0 mx-n1">
          <VCol
            v-for="item in currencies"
            :key="item.label"
            cols="12" sm="6" md="4"
            class="pa-1"
          >
            <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative">
              <div class="card-bg-decoration" :class="`bg-${item.color}-opacity-1`"></div>
              <VCardText class="pa-4 relative-content h-100 d-flex flex-column">
                <div class="d-flex align-center justify-space-between mb-3">
                  <div class="d-flex align-center gap-2">
                    <VAvatar :color="item.color" variant="tonal" size="32" class="rounded-lg">
                      <VIcon :icon="item.icon" size="16" />
                    </VAvatar>
                    <span class="text-super-xs font-weight-black text-disabled uppercase">{{ item.label }}</span>
                  </div>
                  <VChip :color="item.color" size="x-small" variant="flat" class="font-weight-black rounded-lg px-2">
                    {{ item.currency }}
                  </VChip>
                </div>
                <div class="text-h6 font-weight-black leading-tight" :class="`text-${item.color}`">
                  {{ formatCurrency(item.amount, item.currency) }}
                </div>
                <div v-if="item.approxUSD !== null" class="text-super-xs font-weight-bold text-disabled mt-auto pt-1">
                  ≈ {{ formatCurrency(item.approxUSD, 'USD') }}
                </div>
              </VCardText>
              <div class="accent-border" :class="`bg-${item.color}`"></div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Total General Master -->
        <VCard v-if="hasData" class="mt-6 border-0 overflow-hidden shadow-sm position-relative bg-primary-gradient stats-card no-hover">
          <VCardText class="pa-4 d-flex align-center justify-space-between relative-content">
            <div class="d-flex align-center gap-3">
              <VAvatar color="white" variant="tonal" size="38" class="rounded-lg shadow-sm">
                <VIcon icon="tabler-sum" color="white" size="20" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-white opacity-80 uppercase">Venta Bruta Consolidada</span>
                <span class="text-caption text-white font-weight-medium uppercase">Total aproximado en dólares</span>
              </div>
            </div>
            <span class="text-h4 font-weight-black text-white leading-tight">
              {{ formatCurrency(grandTotal, 'USD') }}
            </span>
          </VCardText>
        </VCard>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:not(.no-hover):hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 98%) !important;
  box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.15) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 60px;
  filter: blur(35px);
  inline-size: 60px;
  inset-block-start: -10px;
  inset-inline-end: -10px;
  pointer-events: none;
  opacity: 0.5;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
  opacity: 0.7;
}

.currency-bar {
  block-size: 12px;
  background: rgba(var(--v-border-color), 0.05);
}

.transition-all {
  transition: all 0.4s ease;
}

.bg-primary-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #9575cd 100%) !important;
}

.bg-success-opacity-1 { background: rgba(var(--v-theme-success), 0.1); }
.bg-warning-opacity-1 { background: rgba(var(--v-theme-warning), 0.1); }
.bg-info-opacity-1 { background: rgba(var(--v-theme-info), 0.1); }
.bg-secondary-opacity-1 { background: rgba(var(--v-theme-secondary), 0.1); }

.bg-surface-variant-opacity-2 {
  background: rgba(var(--v-theme-on-surface), 0.05) !important;
}

.text-super-xs {
  font-size: 0.625rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

.leading-tight {
  line-height: 1.2 !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06) !important;
}
</style>
