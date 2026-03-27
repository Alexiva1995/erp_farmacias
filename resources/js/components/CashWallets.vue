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
  <div class="cash-wallets-wrapper">
    <!-- Título del Panel -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center gap-2">
        <VAvatar size="32" color="primary" variant="tonal" class="rounded-lg">
          <VIcon icon="tabler-wallet" size="18" />
        </VAvatar>
        <span class="text-subtitle-1 font-weight-black uppercase letter-spacing-1">Estado de Cajas</span>
        <VChip v-if="dateFiltered" size="x-small" color="info" variant="elevated" class="ms-1 font-weight-black px-2 py-0">
          FILTRADO
        </VChip>
      </div>
      <div class="d-flex flex-column align-end">
        <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Total Consolidado</span>
        <span class="text-h6 font-weight-black text-success">
          USD {{ new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalUsd) }}
        </span>
      </div>
    </div>

    <!-- Secciones por moneda -->
    <div v-if="!loading" class="d-flex flex-column gap-6">
      <div v-for="section in sections" :key="section.currency" class="currency-section">
        <!-- Encabezado de moneda Premium -->
        <div class="d-flex align-center mb-3">
          <VAvatar
            :color="currencyConfig[section.currency]?.color || 'secondary'"
            variant="tonal"
            size="28"
            class="me-2 rounded-lg"
          >
            <VIcon :icon="currencyConfig[section.currency]?.icon || 'tabler-coin'" size="16" />
          </VAvatar>
          <span class="text-xs font-weight-black text-uppercase text-medium-emphasis letter-spacing-1">
            {{ currencyConfig[section.currency]?.label || section.currency }}
          </span>
          <VDivider class="ms-4 flex-grow-1 opacity-10" />
          <div class="ms-4 px-3 py-1 bg-surface-variant-light rounded-pill border">
            <span class="text-xs font-weight-black text-medium-emphasis">
              Σ {{ currencyConfig[section.currency]?.prefix }} {{ formatAmount(section.section_total, section.currency) }}
            </span>
          </div>
        </div>

        <!-- Grid de billeteras -->
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
              :variant="isSelected(wallet) ? 'flat' : 'outlined'"
              :class="[
                'cursor-pointer wallet-card pa-3 h-100 rounded-lg border-0 shadow-sm transition-all',
                isSelected(wallet) ? `bg-${currencyConfig[section.currency]?.color}-lighten-5 ring-primary` : 'bg-white'
              ]"
              @click="handleSelect(wallet)"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <VAvatar 
                  size="32" 
                  :color="isSelected(wallet) ? currencyConfig[section.currency]?.color : 'secondary'"
                  variant="tonal"
                  class="rounded-lg"
                >
                  <VIcon
                    :icon="methodConfig[wallet.method]?.icon || 'tabler-cash'"
                    size="18"
                  />
                </VAvatar>
                <VChip
                  v-if="wallet.balance < 0"
                  size="x-small"
                  color="error"
                  variant="elevated"
                  class="font-weight-black rounded-lg px-2"
                >DÉFICIT</VChip>
              </div>

              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">
                  {{ methodConfig[wallet.method]?.label || wallet.method }}
                </span>
                <span :class="['text-h6 font-weight-black', wallet.balance < 0 ? 'text-error' : 'text-high-emphasis']">
                  {{ formatAmount(wallet.balance, wallet.currency) }}
                </span>
                
                <div class="mt-2 d-flex gap-2">
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-arrow-up" size="12" color="success" />
                    <span class="text-super-xs font-weight-bold text-success">{{ formatAmount(wallet.total_in, wallet.currency) }}</span>
                  </div>
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-arrow-down" size="12" color="error" />
                    <span class="text-super-xs font-weight-bold text-error">{{ formatAmount(wallet.total_out, wallet.currency) }}</span>
                  </div>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </div>

    <!-- Skeleton loader -->
    <VRow v-else>
      <VCol v-for="i in 6" :key="i" cols="6" sm="4" md="3" lg="2">
        <VSkeletonLoader type="card" height="120" class="rounded-lg" />
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.625rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.05);
}

.wallet-card {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid rgba(var(--v-theme-surface-variant), 0.1) !important;
}

.wallet-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 20px -10px rgba(var(--v-theme-primary), 0.2) !important;
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}

.ring-primary {
  box-shadow: 0 0 0 2px rgb(var(--v-theme-primary), 0.2) !important;
  border: 1px solid rgb(var(--v-theme-primary), 0.5) !important;
}

.currency-section:not(:last-child) {
  margin-bottom: 0.5rem;
}
</style>
