<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed } from "vue";

const props = defineProps({
  cashClosureData: {
    type: Object,
    default: () => ({
      total_usd: "0.00",
      total_bs:  "0.00",
      total_cop: "0.00",
      usd_credit: "0.00",
      total_bs_in_usd: "0.00",
      total_cop_in_usd: "0.00",
    }),
  },
});

const emit = defineEmits(["requestCloseCash"]);

const currencies = computed(() => {
  const data = props.cashClosureData;
  const totalUsd    = parseFloat(data.total_usd) || 0;
  const totalBs     = parseFloat(data.total_bs) || 0;
  const totalBsUSD  = parseFloat(data.total_bs_in_usd) || 0;
  const totalCop    = parseFloat(data.total_cop) || 0;
  const totalCopUSD = parseFloat(data.total_cop_in_usd) || 0;
  const totalCred   = parseFloat(data.usd_credit) || 0;

  return [
    {
      label: 'Total USD',
      amount: totalUsd,
      amountUSD: totalUsd,
      approxUSD: null,
      currency: 'USD',
      color: 'success',
      icon: 'tabler-currency-dollar',
      barColor: '#28C76F',
    },
    {
      label: 'Total Bs',
      amount: totalBs,
      amountUSD: totalBsUSD,
      approxUSD: totalBsUSD,
      currency: 'BS',
      color: 'warning',
      icon: 'tabler-cash',
      barColor: '#FF9F43',
    },
    {
      label: 'Total COP',
      amount: totalCop,
      amountUSD: totalCopUSD,
      approxUSD: totalCopUSD,
      currency: 'COP',
      color: 'info',
      icon: 'tabler-coin',
      barColor: '#00CFE8',
    },
    {
      label: 'Créditos',
      amount: totalCred,
      amountUSD: totalCred,
      approxUSD: null,
      currency: 'USD',
      color: 'secondary',
      icon: 'tabler-credit-card',
      barColor: '#82868B',
    },
  ].filter(c => c.amountUSD > 0);
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

const hasData = computed(() => grandTotal.value > 0);
</script>

<template>
  <VCard class="rounded-xl border" elevation="0">
    <VCardItem class="pa-5 pb-0">
      <template #prepend>
        <VAvatar color="primary" variant="tonal" rounded>
          <VIcon icon="tabler-report-money" />
        </VAvatar>
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">Resumen de Caja</VCardTitle>
      <VCardSubtitle class="text-caption">Acumulado de la jornada actual</VCardSubtitle>
      <template #append>
        <VBtn icon variant="text" size="small" color="default">
          <VIcon size="22" icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList density="compact">
              <VListItem value="closed_cash" @click="emit('requestCloseCash')">
                <template #prepend>
                  <VIcon icon="tabler-lock" size="16" class="mr-2" />
                </template>
                <VListItemTitle class="text-body-2">Cerrar Caja</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VBtn>
      </template>
    </VCardItem>

    <VCardText class="pa-5">
      <!-- Barra multimoneda -->
      <div class="currency-bar mb-5 rounded-lg overflow-hidden" style=" display: flex;block-size: 48px;">
        <template v-if="hasData">
          <div
            v-for="item in barItems"
            :key="item.label"
            :style="{ width: item.pct + '%', backgroundColor: item.barColor }"
            class="d-flex align-center justify-center"
          >
            <span v-if="item.pct > 8" class="text-xs font-weight-bold text-white" style="font-size: 11px; white-space: nowrap;">
              {{ item.pct.toFixed(0) }}% {{ item.label.replace('Total ', '') }}
            </span>
          </div>
        </template>
        <template v-else>
          <div class="w-100 d-flex align-center justify-center bg-grey-lighten-2 rounded-lg">
            <span class="text-caption text-medium-emphasis">Sin ventas registradas</span>
          </div>
        </template>
      </div>

      <!-- Tarjetas por moneda -->
      <VRow dense>
        <VCol
          v-for="item in currencies"
          :key="item.label"
          cols="12" sm="6"
        >
          <VCard variant="outlined" class="rounded-lg" :class="`border-${item.color}`">
            <VCardText class="pa-3">
              <div class="d-flex align-center justify-space-between mb-1">
                <div class="d-flex align-center gap-2">
                  <VIcon :icon="item.icon" :color="item.color" size="18" />
                  <span class="text-caption font-weight-bold text-medium-emphasis">{{ item.label }}</span>
                </div>
                <VChip :color="item.color" size="x-small" variant="tonal" label>
                  {{ item.currency }}
                </VChip>
              </div>
              <div class="text-h6 font-weight-bold" :class="`text-${item.color}`">
                {{ formatCurrency(item.amount, item.currency) }}
              </div>
              <div v-if="item.approxUSD !== null" class="text-caption text-medium-emphasis mt-1">
                ≈ {{ formatCurrency(item.approxUSD, 'USD') }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Total General -->
      <VCard v-if="hasData" variant="flat" color="primary" class="mt-4 rounded-lg">
        <VCardText class="pa-3 d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-sum" color="white" size="18" />
            <span class="text-caption font-weight-bold text-white opacity-80">VENTA BRUTA (≈ USD)</span>
          </div>
          <span class="text-h6 font-weight-bold text-white">
            {{ formatCurrency(grandTotal, 'USD') }}
          </span>
        </VCardText>
      </VCard>

      <!-- Estado vacío -->
      <VCard v-else variant="tonal" color="secondary" class="mt-2 rounded-lg">
        <VCardText class="pa-4 d-flex align-center gap-3">
          <VIcon icon="tabler-shopping-cart-off" size="28" color="secondary" />
          <div>
            <div class="text-body-2 font-weight-bold">Sin ventas en esta jornada</div>
            <div class="text-caption text-medium-emphasis">Registra órdenes para ver el resumen</div>
          </div>
        </VCardText>
      </VCard>
    </VCardText>
  </VCard>
</template>

<style scoped>
.currency-bar > div {
  transition: inline-size 0.4s ease;
}
.border-success { border-color: #28c76f !important; }
.border-warning { border-color: #ff9f43 !important; }
.border-info { border-color: #00cfe8 !important; }
.border-secondary { border-color: #82868b !important; }
</style>
