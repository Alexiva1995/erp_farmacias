<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/plugins/axios";

const props = defineProps({
  sections:         { type: Array,   default: () => [] },
  totalUsd:         { type: Number,  default: 0 },
  dateFiltered:     { type: Boolean, default: false },
  loading:          { type: Boolean, default: false },
  selectedCurrency: { type: String,  default: '' },
  selectedOption:   { type: String,  default: '' },
  rates:            { type: Object,  default: () => ({ bcv: { rate: 0 }, cop: { rate: 0 } }) },
  cashStatus:       { type: Object,  default: () => null },
});

const emit = defineEmits(['select', 'adjust']);
const authStore = useAuthStore();

// ─── Estado colapsable ───────────────────────────────────────────────────────
const isCollapsed = ref(false);

// ─── Config por moneda ─────────────────────────────────────────────────────────
const C = {
  USD: { color: 'warning',  vuetify: 'var(--v-theme-warning)',  hex: '#F9A825', icon: 'tabler-currency-dollar',  label: 'Dólar',   ticker: 'USD', decimals: 2, prefix: 'USD' },
  BS:  { color: 'error',    vuetify: 'var(--v-theme-error)',    hex: '#E53935', icon: 'tabler-currency-real',    label: 'Bolívar', ticker: 'Bs.', decimals: 2, prefix: 'Bs.' },
  COP: { color: 'primary',  vuetify: 'var(--v-theme-primary)',  hex: '#1565C0', icon: 'tabler-currency-peso',    label: 'Peso',    ticker: 'COP', decimals: 0, prefix: 'COP' },
};

const M = {
  CASH:     { icon: 'tabler-cash',            label: 'Efectivo'      },
  CARD:     { icon: 'tabler-credit-card',      label: 'Tarjeta'       },
  MOBILE:   { icon: 'tabler-device-mobile',    label: 'Pago Móvil'    },
  TRANSFER: { icon: 'tabler-building-bank',    label: 'Transferencia' },
  BINANCE:  { icon: 'tabler-currency-bitcoin', label: 'Binance'       },
  PAYPAL:   { icon: 'tabler-brand-paypal',     label: 'PayPal'        },
  CREDIT:   { icon: 'tabler-receipt-2',        label: 'Crédito'       },
  CAMBISTA: { icon: 'tabler-arrows-exchange',  label: 'Cambista'      },
};

// ─── Helpers ─────────────────────────────────────────────────────────────────
const fmt = (amount, currency) => {
  const cfg = C[currency] || { decimals: 2 };
  return new Intl.NumberFormat('es-ES', {
    minimumFractionDigits:  cfg.decimals,
    maximumFractionDigits:  cfg.decimals,
  }).format(amount || 0);
};

const fmtUsd = (n) =>
  new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);

const methodLabel = (wallet) =>
  (wallet.currency === 'BS' && wallet.method === 'TRANSFER') ? 'Banco' : (M[wallet.method]?.label || wallet.method);

const isSelected  = (w) => props.selectedCurrency === w.currency && props.selectedOption === w.key;
const handleSelect = (w) => emit('select', { currency: w.currency, option: w.key });
const canAdjust    = computed(() => !props.dateFiltered && authStore.isAdmin && !authStore.isSupervisor && authStore.user?.role_id === 1);

// ─── Helper: hex → "r,g,b" para usar en rgba() ──────────────────────────────
const hexToRgb = (hex) => {
  if (!hex) return '128,128,128';
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `${r},${g},${b}`;
};

// ─── Estado de cierre de caja ────────────────────────────────────────────────
const statusColor = computed(() => !props.cashStatus ? 'secondary' : props.cashStatus.open_closings_count > 0 ? 'warning' : 'success');
const statusIcon  = computed(() => !props.cashStatus ? 'tabler-help-circle' : props.cashStatus.open_closings_count > 0 ? 'tabler-lock-open-2' : 'tabler-lock-check');
const statusLabel = computed(() => {
  if (!props.cashStatus) return 'Sin datos';
  const n = props.cashStatus.open_closings_count;
  return n > 0 ? `${n} turno${n > 1 ? 's' : ''} abierto${n > 1 ? 's' : ''}` : `Cerrado · ${props.cashStatus.last_closed_date ?? 'N/A'}`;
});

// ─── Label de la tasa activa ─────────────────────────────────────────────────
const rateTypeLabel = computed(() => {
  const t = props.rates?.bcv?.type;
  if (t === 'BINANCE') return 'Binance';
  if (t === 'EUR')     return 'EUR';
  return 'BCV';
});

const walletIconColor = (method) => {
  switch (method) {
    case 'CASH':
    case 'TRANSFER':
      return 'success';
    case 'MOBILE':
    case 'BINANCE':
      return 'info';
    case 'CAMBISTA':
    case 'CREDIT':
    case 'PAYPAL':
      return 'secondary';
    default:
      return 'primary';
  }
};
</script>

<template>
  <div class="cwrap">

    <!-- ╔══════════════════ TOPBAR ══════════════════╗ -->
    <div class="topbar d-flex flex-wrap align-center justify-space-between gap-3 mb-4">

      <!-- Título -->
      <div class="d-flex align-center gap-2 cursor-pointer" @click="isCollapsed = !isCollapsed">
        <div class="topbar-icon">
          <VIcon icon="tabler-wallet" size="15" class="text-white" />
        </div>
        <span class="topbar-title">Estado de Cajas</span>
        <VChip v-if="dateFiltered" size="x-small" color="info" variant="elevated" class="font-weight-black px-2 rounded">
          FILTRADO
        </VChip>
      </div>

      <!-- Controles derechos -->
      <div v-if="!isCollapsed" class="d-flex flex-wrap align-center gap-2">

        <!-- Tasa BS (BCV/Binance/EUR) -->
        <VTooltip location="bottom">
          <template #activator="{ props: tp }">
            <div v-bind="tp" class="rate-pill rate-pill--bs">
              <span class="rate-pill__label">{{ rateTypeLabel }}</span>
              <span class="rate-pill__sep">·</span>
              <span class="rate-pill__value">{{ rates.bcv?.rate > 0 ? Number(rates.bcv.rate).toLocaleString('es-ES', { minimumFractionDigits: 2 }) : '—' }}</span>
            </div>
          </template>
          <span>Tasa {{ rateTypeLabel }} para Bs. · Act: {{ rates.bcv?.updated_at ?? 'N/A' }}</span>
        </VTooltip>

        <!-- Tasa COP -->
        <VTooltip location="bottom">
          <template #activator="{ props: tp }">
            <div v-bind="tp" class="rate-pill rate-pill--cop">
              <span class="rate-pill__label">COP</span>
              <span class="rate-pill__sep">·</span>
              <span class="rate-pill__value">{{ rates.cop?.rate > 0 ? Number(rates.cop.rate).toLocaleString('es-ES', { maximumFractionDigits: 0 }) : '—' }}</span>
            </div>
          </template>
          <span>Tasa COP/USD · Act: {{ rates.cop?.updated_at ?? 'N/A' }}</span>
        </VTooltip>

        <!-- Total USD -->
        <div class="total-pill">
          <span class="total-pill__label">Total USD</span>
          <span class="total-pill__value">{{ fmtUsd(totalUsd) }} USD</span>
        </div>
      </div>
    </div>
    <!-- ╚════════════════════════════════════════════╝ -->

    <!-- ╔══════════════════ CONTENIDO ══════════════════╗ -->
    <VExpandTransition>
      <div v-show="!isCollapsed">

        <!-- Cargador durante la carga inicial -->
        <div v-if="loading" class="pa-8 text-center rounded bg-white border my-2">
          <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
          <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando estado de cajas...</div>
        </div>

        <!-- ══ GRILLA POR MONEDA (3 COLUMNAS) ══ -->
        <div v-else>
          <VRow>
            <VCol
              v-for="section in sections"
              :key="section.currency"
              cols="12"
              md="4"
            >
              <!-- Tarjeta Contenedora de Moneda -->
              <VCard class="currency-container-card rounded-lg border h-100 d-flex flex-column bg-surface pa-3">
                <!-- Cabecera de la moneda -->
                <div class="cur-header d-flex align-center gap-2 mb-3">
                  <VAvatar :color="C[section.currency]?.color || 'primary'" variant="tonal" size="30" class="rounded flex-shrink-0">
                    <VIcon :icon="C[section.currency]?.icon" size="16" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="cur-header__name">{{ C[section.currency]?.label }}</span>
                    <span class="cur-header__ticker">{{ C[section.currency]?.ticker }}</span>
                  </div>
                  <VSpacer />
                  <div class="cur-header__total" :class="section.section_total < 0 ? 'text-error' : (section.section_total > 0 ? 'text-high-emphasis' : 'text-medium-emphasis')">
                    {{ C[section.currency]?.prefix }} {{ fmt(section.section_total, section.currency) }}
                  </div>
                </div>

                <!-- Sub-cajas dentro de la columna -->
                <div class="d-flex flex-column gap-2 flex-grow-1">
                  <div
                    v-for="wallet in section.wallets"
                    :key="wallet.key"
                    :class="['mcard', isSelected(wallet) ? 'mcard--sel' : '', wallet.balance < 0 ? 'mcard--neg' : '']"
                    @click="handleSelect(wallet)"
                  >
                    <div class="d-flex align-center justify-space-between w-100">
                      <!-- Ícono y Nombre de Método -->
                      <div class="d-flex align-center gap-2">
                        <VAvatar
                          :color="walletIconColor(wallet.method)"
                          variant="tonal"
                          size="32"
                          class="rounded"
                        >
                          <VIcon :icon="M[wallet.method]?.icon || 'tabler-cash'" size="16" />
                        </VAvatar>
                        <div class="d-flex flex-column">
                          <span class="mcard__label">{{ methodLabel(wallet) }}</span>
                          <div class="mcard__amount-row">
                            <span :class="['mcard__amount', wallet.balance < 0 ? 'text-error' : (wallet.balance > 0 ? 'text-high-emphasis' : 'text-medium-emphasis')]">
                              {{ fmt(wallet.balance, wallet.currency) }} <span class="mcard__prefix">{{ C[section.currency]?.prefix }}</span>
                            </span>
                          </div>
                        </div>
                      </div>

                      <!-- Botón ajuste contable -->
                      <VTooltip v-if="canAdjust" location="top">
                        <template #activator="{ props: tp }">
                          <VBtn
                            v-bind="tp"
                            icon="tabler-scale"
                            variant="text"
                            size="x-small"
                            color="medium-emphasis"
                            class="mcard__adj"
                            @click.stop="emit('adjust', wallet)"
                          />
                        </template>
                        <span>Ajuste contable de saldo</span>
                      </VTooltip>
                    </div>

                    <!-- Footer hover con entradas y salidas -->
                    <div class="mcard__footer">
                      <span class="mcard__in"><VIcon icon="tabler-arrow-up" size="11" />{{ fmt(wallet.total_in, wallet.currency) }}</span>
                      <span class="mcard__out"><VIcon icon="tabler-arrow-down" size="11" />{{ fmt(wallet.total_out, wallet.currency) }}</span>
                    </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>

      </div>
    </VExpandTransition>
    <!-- ╚════════════════════════════════════════════╝ -->
  </div>
</template>

<style scoped>
/* ══════════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════════ */
.topbar-icon {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  border-radius: 8px;
  background: rgb(var(--v-theme-primary));
  flex-shrink: 0;
}
.topbar-title {
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -0.2px;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
}

/* Rate pills */
.rate-pill {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  font-weight: 600;
  cursor: default;
  user-select: none;
  background: rgb(var(--v-theme-surface));
}
.rate-pill:hover { border-color: rgba(var(--v-theme-on-surface), 0.16); }
.rate-pill--bs, .rate-pill--cop { color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)); }

.rate-pill__label { font-size: 0.6875rem; letter-spacing: 0.05em; text-transform: uppercase; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-weight: 600; }
.rate-pill__sep   { font-size: 0.6875rem; opacity: 0.3; }
.rate-pill__value { font-size: 0.8125rem; font-weight: 700; color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)); }

/* Total pill */
.total-pill { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2; }
.total-pill__label { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); margin-bottom: 2px; }
.total-pill__value { font-size: 1.25rem; font-weight: 700; color: rgb(var(--v-theme-primary)); letter-spacing: -0.3px; }

/* ══════════════════════════════════════════════════
   VISTA EXPANDIDA — cabecera de moneda
══════════════════════════════════════════════════ */
.cur-header {
  padding: 8px 14px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.03);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.06);
}
.cur-header__name   { font-size: 0.875rem; font-weight: 700; color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)); }
.cur-header__ticker { font-size: 0.6875rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); text-transform: uppercase; }
.cur-header__total  { font-size: 1.125rem; font-weight: 700; letter-spacing: -0.2px; color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)); }

/* ── Method Card (expanded) ── */
.mcard {
  position: relative;
  display: flex; flex-direction: column; gap: 8px;
  padding: 16px;
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  cursor: pointer;
  overflow: hidden;
  transition: all 0.2s ease;
  min-height: 116px;
}
.mcard:hover {
  transform: translateY(-2px);
  border-color: rgba(var(--v-theme-primary), 0.4);
  box-shadow: 0 4px 16px -2px rgba(0,0,0,0.08);
}
.mcard:hover .mcard__footer {
  opacity: 1;
  max-height: 30px;
}
.mcard--sel { border-color: rgb(var(--v-theme-primary)) !important; box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.15) !important; }
.mcard--neg { border-left: 3px solid rgb(var(--v-theme-error)); }

.mcard__adj {
  position: absolute; inset-block-start: 6px; inset-inline-end: 6px;
  z-index: 5; opacity: 0; transition: opacity 0.2s;
}
.mcard:hover .mcard__adj { opacity: 0.7; }
.mcard__adj:hover { opacity: 1 !important; }

.mcard__icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  flex-shrink: 0;
}

.mcard__label {
  font-size: 0.6875rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.5px;
  color: #6B7280;
  line-height: 1.2;
}

.mcard__amount-row { display: flex; align-items: baseline; gap: 4px; }
.mcard__prefix { font-size: 0.75rem; font-weight: 600; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1; }
.mcard__amount { font-size: 1.125rem; font-weight: 700; letter-spacing: -0.3px; color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)); }
.mcard__amount--neg { color: rgb(var(--v-theme-error)); }

.mcard__footer {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: auto; padding-top: 8px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.06);
  opacity: 0.75;
  transition: opacity 0.2s ease;
}
.mcard__in, .mcard__out {
  display: flex; align-items: center; gap: 2px;
  font-size: 0.6875rem; font-weight: 600;
}
.mcard__in  { color: rgb(var(--v-theme-success)); }
.mcard__out { color: rgb(var(--v-theme-error));   }

.mcard__bar {
  display: none;
}
</style>
