<script setup>
const props = defineProps({
  sections: { type: Array, default: () => [] },
  totalUsd: { type: Number, default: 0 },
  dateFiltered: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  selectedCurrency: { type: String, default: '' },
  selectedOption: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const currencyConfig = {
  USD: {
    color: 'warning',
    icon: 'tabler-currency-dollar',
    label: 'Dólar (USD)',
    decimals: 2,
    prefix: 'USD',
  },
  BS: {
    color: 'error',
    icon: 'tabler-currency-bolivian',
    label: 'Bolívar (Bs.)',
    decimals: 2,
    prefix: 'Bs.',
  },
  COP: {
    color: 'primary',
    icon: 'tabler-currency-peso',
    label: 'Peso (COP)',
    decimals: 0,
    prefix: 'COP',
  },
};

const methodConfig = {
  CASH: { icon: 'tabler-cash', label: 'Efectivo' },
  CARD: { icon: 'tabler-credit-card', label: 'Tarjeta' },
  MOBILE: { icon: 'tabler-device-mobile', label: 'Pago Móvil' },
  TRANSFER: { icon: 'tabler-building-bank', label: 'Transferencia' },
  BINANCE: { icon: 'tabler-currency-bitcoin', label: 'Binance' },
  PAYPAL: { icon: 'tabler-brand-paypal', label: 'PayPal' },
  CREDIT: { icon: 'tabler-receipt-2', label: 'Crédito' },
};

const formatAmount = (amount, currency) => {
  const cfg = currencyConfig[currency] || { decimals: 2 };
  return new Intl.NumberFormat('es-ES', {
    minimumFractionDigits: cfg.decimals,
    maximumFractionDigits: cfg.decimals,
  }).format(amount || 0);
};

const isSelected = (wallet) =>
  props.selectedCurrency === wallet.currency && props.selectedOption === wallet.key;

const handleSelect = (wallet) => {
  emit('select', {
    currency: wallet.currency,
    option: wallet.key,
  });
};
</script>

<template>
  <div class="mb-6">
    <!-- Título del Panel -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-wallet" color="primary" />
        <span class="text-subtitle-1 font-weight-bold">Estado actual de Cajas</span>
        <VChip v-if="dateFiltered" size="x-small" color="info" variant="tonal" class="ms-1">
          Rango filtrado
        </VChip>
      </div>
      <div class="text-body-2 text-medium-emphasis">
        Total equiv. USD:
        <span class="font-weight-black text-success ms-1">
          USD {{ new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalUsd) }}
        </span>
      </div>
    </div>

    <!-- Secciones por moneda -->
    <VRow v-if="!loading">
      <template v-for="section in sections" :key="section.currency">
        <VCol cols="12">
          <!-- Encabezado de moneda -->
          <div class="d-flex align-center mb-2 mt-1">
            <VAvatar
              :color="currencyConfig[section.currency]?.color || 'secondary'"
              variant="tonal"
              size="28"
              class="me-2"
            >
              <VIcon :icon="currencyConfig[section.currency]?.icon || 'tabler-coin'" size="16" />
            </VAvatar>
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              {{ currencyConfig[section.currency]?.label || section.currency }}
            </span>
            <VDivider class="ms-3 flex-1" />
            <span class="text-caption text-medium-emphasis ms-3">
              Total: {{ currencyConfig[section.currency]?.prefix }}
              {{ formatAmount(section.section_total, section.currency) }}
            </span>
          </div>

          <!-- Tarjetas de billeteras -->
          <VRow dense>
            <VCol
              v-for="wallet in section.wallets"
              :key="wallet.key"
              cols="6"
              sm="4"
              md="3"
              lg="2"
            >
              <VCard
                :color="isSelected(wallet) ? currencyConfig[section.currency]?.color : undefined"
                :variant="isSelected(wallet) ? 'tonal' : 'outlined'"
                class="cursor-pointer wallet-card pa-3 h-100"
                :class="{
                  'border-opacity-25': !isSelected(wallet),
                  'border-opacity-75': isSelected(wallet),
                }"
                @click="handleSelect(wallet)"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <VIcon
                    :icon="methodConfig[wallet.method]?.icon || 'tabler-cash'"
                    size="18"
                    :color="isSelected(wallet) ? currencyConfig[section.currency]?.color : 'default'"
                    class="opacity-80"
                  />
                  <VChip
                    v-if="wallet.balance < 0"
                    size="x-small"
                    color="error"
                    variant="flat"
                  >—</VChip>
                </div>
                <div
                  class="text-caption font-weight-bold text-truncate mb-1"
                  :class="isSelected(wallet) ? `text-${currencyConfig[section.currency]?.color}` : 'text-medium-emphasis'"
                >
                  {{ methodConfig[wallet.method]?.label || wallet.method }}
                </div>
                <div
                  class="font-weight-black"
                  :class="[
                    wallet.balance >= 0 ? 'text-body-2' : 'text-caption text-error',
                    isSelected(wallet) ? `text-${currencyConfig[section.currency]?.color}` : '',
                  ]"
                >
                  {{ formatAmount(wallet.balance, wallet.currency) }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1 d-flex gap-1">
                  <span class="text-success">↑{{ formatAmount(wallet.total_in, wallet.currency) }}</span>
                  <span class="text-error">↓{{ formatAmount(wallet.total_out, wallet.currency) }}</span>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </VCol>
      </template>
    </VRow>

    <!-- Skeleton loader -->
    <VRow v-else>
      <VCol v-for="i in 8" :key="i" cols="6" sm="4" md="3" lg="2">
        <VSkeletonLoader type="card" height="100" />
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.wallet-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.wallet-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 12%);
  transform: translateY(-2px);
}
</style>
