<script setup>
import { ref } from "vue";

const props = defineProps({
  sections: { type: Array, default: () => [] },
  totalUsd: { type: Number, default: 0 },
  dateFiltered: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  selectedCurrency: { type: String, default: '' },
  selectedOption: { type: String, default: '' },
});

const emit = defineEmits(['select', 'adjust']);

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

const isCollapsed = ref(false);
</script>

<template>
  <div class="cash-wallets-wrapper">
    <!-- Título del Panel -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center gap-2 cursor-pointer" @click="isCollapsed = !isCollapsed">
        <VAvatar size="32" color="primary" variant="tonal" class="rounded-lg transition-all" :class="{ 'rotate-180': isCollapsed }">
          <VIcon :icon="isCollapsed ? 'tabler-chevron-down' : 'tabler-wallet'" size="18" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-subtitle-1 font-weight-black uppercase letter-spacing-1">Estado de Cajas</span>
          <span v-if="isCollapsed" class="text-super-xs text-primary font-weight-bold uppercase">Click para expandir</span>
        </div>
        <VChip v-if="dateFiltered" size="x-small" color="info" variant="elevated" class="ms-1 font-weight-black px-2 py-0">
          FILTRADO
        </VChip>
      </div>
      <div v-if="!isCollapsed" class="d-flex flex-column align-end transition-all">
        <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Total Consolidado</span>
        <span class="text-h6 font-weight-black text-success">
          USD {{ new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalUsd) }}
        </span>
      </div>
    </div>

    <!-- Contenido Colapsable -->
    <VExpandTransition>
      <div v-show="!isCollapsed">
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
            md="4"
            lg="3"
          >
            <VCard
              :class="[
                'stats-card h-100 border-0 overflow-hidden shadow-sm cursor-pointer position-relative',
                isSelected(wallet) ? `ring-${currencyConfig[section.currency]?.color}` : ''
              ]"
              @click="handleSelect(wallet)"
            >
              <!-- Botón de Ajuste -->
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="x-small"
                color="medium-emphasis"
                class="position-absolute wallet-edit-btn"
                @click.stop="emit('adjust', wallet)"
                v-if="!dateFiltered"
              />

              <div
                class="card-bg-decoration"
                :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${currencyConfig[section.currency]?.color}), 0.1), transparent)` }"
              ></div>
              
              <VCardText class="pa-4 relative-content d-flex flex-column h-100">
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar 
                    size="36" 
                    :color="currencyConfig[section.currency]?.color"
                    variant="tonal"
                    class="rounded-lg shadow-sm"
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
                    variant="flat"
                    class="font-weight-black rounded-lg px-2"
                  >DÉFICIT</VChip>
                </div>

                <div class="d-flex flex-column mb-3">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">
                    {{ methodConfig[wallet.method]?.label || wallet.method }}
                  </span>
                  <div class="d-flex align-baseline gap-1">
                    <span class="text-xs font-weight-black text-medium-emphasis">
                      {{ currencyConfig[section.currency]?.prefix }}
                    </span>
                    <span :class="['text-h5 font-weight-black', wallet.balance < 0 ? 'text-error' : 'text-high-emphasis']">
                      {{ formatAmount(wallet.balance, wallet.currency) }}
                    </span>
                  </div>
                </div>
                
                <VDivider class="mb-3 opacity-10" />

                <div class="mt-auto d-flex justify-space-between align-center">
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-arrow-up" size="12" color="success" class="opacity-70" />
                    <span class="text-xs font-weight-black text-success">{{ formatAmount(wallet.total_in, wallet.currency) }}</span>
                  </div>
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-arrow-down" size="12" color="error" class="opacity-70" />
                    <span class="text-xs font-weight-black text-error">{{ formatAmount(wallet.total_out, wallet.currency) }}</span>
                  </div>
                </div>
              </VCardText>
              <div :class="['accent-border', `bg-${currencyConfig[section.currency]?.color}`]"></div>
            </VCard>
          </VCol>
        </VRow>
        </div>
      </div>
      <!-- Skeleton loader -->
      <VRow v-else>
        <VCol v-for="i in 4" :key="i" cols="6" md="4" lg="3">
          <VSkeletonLoader type="card" height="140" class="rounded-lg shadow-sm" />
        </VCol>
      </VRow>
    </div>
  </VExpandTransition>
</div>
</template>

<style scoped>
.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  min-height: 155px;
}

.stats-card:hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 98%) !important;
  box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.15) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
  opacity: 0.8;
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

.text-super-xs {
  font-size: 0.625rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

/* Indicador de selección sutil */
.ring-warning { border: 1px solid rgba(var(--v-theme-warning), 0.5) !important; }
.ring-error { border: 1px solid rgba(var(--v-theme-error), 0.5) !important; }
.ring-primary { border: 1px solid rgba(var(--v-theme-primary), 0.5) !important; }

.currency-section:not(:last-child) {
  margin-bottom: 0.25rem;
}

.wallet-edit-btn {
  inset-block-start: 8px;
  inset-inline-end: 8px;
  z-index: 10;
  opacity: 0.3;
  transition: opacity 0.2s ease;
}

.stats-card:hover .wallet-edit-btn {
  opacity: 1;
}

.rotate-180 {
  transform: rotate(180deg);
}

.transition-all {
  transition: all 0.3s ease;
}
</style>
