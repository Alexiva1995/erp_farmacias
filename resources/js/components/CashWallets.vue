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

// ─── Preferencia de vista persistida en BD ────────────────────────────────────
const isCompact   = ref(false);
const isCollapsed = ref(false);
let prefSaveTimer = null;

onMounted(async () => {
  try {
    const { data } = await axios.get('/user/ui-preferences');
    const prefs = data?.data?.ui_preferences ?? {};
    if (typeof prefs?.cash_wallets_compact === 'boolean') {
      isCompact.value = prefs.cash_wallets_compact;
    }
  } catch (e) {
    // Ignorar silenciosamente — no crítico
  }
});

// Guardar preferencia en BD con debounce de 600ms para no saturar
watch(isCompact, (val) => {
  clearTimeout(prefSaveTimer);
  prefSaveTimer = setTimeout(() => {
    axios.post('/user/ui-preferences', { key: 'cash_wallets_compact', value: val }).catch(() => {});
  }, 600);
});

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
              <VIcon icon="tabler-currency-real" size="13" />
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
              <VIcon icon="tabler-currency-peso" size="13" />
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
          <span class="total-pill__value">USD {{ fmtUsd(totalUsd) }}</span>
        </div>

        <!-- Toggle vista compacta / expandida -->
        <VBtnToggle v-model="isCompact" density="compact" variant="outlined" rounded="0" color="primary" mandatory class="view-toggle">
          <VBtn :value="false" size="small" icon="tabler-layout-grid" class="rounded" />
          <VBtn :value="true"  size="small" icon="tabler-layout-list" class="rounded" />
        </VBtnToggle>
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

        <!-- ══ VISTA EXPANDIDA ══ -->
        <div v-else-if="!isCompact" class="d-flex flex-column gap-5">
          <div v-for="section in sections" :key="section.currency">

            <!-- Cabecera de moneda -->
            <div
              class="cur-header d-flex align-center gap-2 mb-3"
              :style="{ background: `linear-gradient(90deg, rgba(${hexToRgb(C[section.currency]?.hex)},0.12) 0%, transparent 100%)`, borderLeft: `3px solid ${C[section.currency]?.hex}` }"
            >
              <VAvatar :color="C[section.currency]?.color" variant="flat" size="26" class="rounded flex-shrink-0">
                <VIcon :icon="C[section.currency]?.icon" size="14" />
              </VAvatar>
              <div class="d-flex align-baseline gap-2">
                <span class="cur-header__name">{{ C[section.currency]?.label }}</span>
                <span class="cur-header__ticker">{{ C[section.currency]?.ticker }}</span>
              </div>
              <VSpacer />
              <div class="cur-header__total" :style="{ color: C[section.currency]?.hex }">
                {{ C[section.currency]?.prefix }} {{ fmt(section.section_total, section.currency) }}
              </div>
            </div>

            <!-- Cards -->
            <VRow dense>
              <VCol v-for="wallet in section.wallets" :key="wallet.key" cols="6" sm="4" md="3" lg="2">
                <div
                  :class="['mcard', isSelected(wallet) ? 'mcard--sel' : '', wallet.balance < 0 ? 'mcard--neg' : '']"
                  :style="{ '--mc': C[section.currency]?.hex }"
                  @click="handleSelect(wallet)"
                >
                  <!-- Botón ajuste -->
                  <VTooltip v-if="canAdjust" location="top">
                    <template #activator="{ props: tp }">
                      <VBtn v-bind="tp" icon="tabler-scale" variant="text" size="x-small"
                        color="medium-emphasis" class="mcard__adj"
                        @click.stop="emit('adjust', wallet)" />
                    </template>
                    <span>Ajuste contable de saldo</span>
                  </VTooltip>

                  <!-- Ícono método -->
                  <div class="mcard__icon" :style="{ background: `rgba(${hexToRgb(C[section.currency]?.hex)}, 0.15)`, color: C[section.currency]?.hex }">
                    <VIcon :icon="M[wallet.method]?.icon || 'tabler-cash'" size="16" />
                  </div>

                  <span class="mcard__label">{{ methodLabel(wallet) }}</span>

                  <div class="mcard__amount-row">
                    <span class="mcard__prefix">{{ C[section.currency]?.prefix }}</span>
                    <span :class="['mcard__amount', wallet.balance < 0 ? 'mcard__amount--neg' : '']">
                      {{ fmt(wallet.balance, wallet.currency) }}
                    </span>
                  </div>

                  <div class="mcard__footer">
                    <span class="mcard__in"><VIcon icon="tabler-arrow-up" size="9" />{{ fmt(wallet.total_in, wallet.currency) }}</span>
                    <span class="mcard__out"><VIcon icon="tabler-arrow-down" size="9" />{{ fmt(wallet.total_out, wallet.currency) }}</span>
                  </div>

                  <div class="mcard__bar" :style="{ background: C[section.currency]?.hex }" />
                </div>
              </VCol>
            </VRow>

          </div>
        </div>

        <!-- ══ VISTA COMPACTA ══ -->
        <div v-else class="d-flex flex-column gap-2">
          <div
            v-for="section in sections"
            :key="section.currency"
            class="cblock"
            :style="{ '--cb': C[section.currency]?.hex, '--cbalpha': `rgba(${hexToRgb(C[section.currency]?.hex)},0.1)` }"
          >
            <!-- Cabecera compacta -->
            <div class="cblock__head">
              <div class="cblock__icon">
                <VIcon :icon="C[section.currency]?.icon" size="14" class="text-white" />
              </div>
              <span class="cblock__name">{{ C[section.currency]?.label }}</span>
              <span class="cblock__ticker">{{ C[section.currency]?.ticker }}</span>
              <!-- Total a la derecha: label micro + monto grande, nunca en 2 líneas -->
              <div class="cblock__total-wrap ms-auto">
                <span class="cblock__total-label">Total</span>
                <span class="cblock__total">
                  {{ C[section.currency]?.prefix }} {{ fmt(section.section_total, section.currency) }}
                </span>
              </div>
            </div>

            <!-- Wallets fila -->
            <div class="cblock__row">
              <div
                v-for="wallet in section.wallets"
                :key="wallet.key"
                :class="['cwallet', isSelected(wallet) ? 'cwallet--active' : '', wallet.balance < 0 ? 'cwallet--neg' : '']"
                @click="handleSelect(wallet)"
              >
                <!-- Ícono -->
                <div class="cwallet__icon" :style="{ background: `rgba(${hexToRgb(C[section.currency]?.hex)}, 0.14)`, color: C[section.currency]?.hex }">
                  <VIcon :icon="M[wallet.method]?.icon || 'tabler-cash'" size="14" />
                </div>

                <!-- Textos -->
                <div class="cwallet__body">
                  <span class="cwallet__method">{{ methodLabel(wallet) }}</span>
                  <span :class="['cwallet__balance', wallet.balance < 0 ? 'cwallet__balance--neg' : '']">
                    {{ C[section.currency]?.prefix }} {{ fmt(wallet.balance, wallet.currency) }}
                  </span>
                </div>

                <!-- Punto selección activa -->
                <div v-if="isSelected(wallet)" class="cwallet__dot" :style="{ background: C[section.currency]?.hex }" />

                <!-- Ajuste contable -->
                <VTooltip v-if="canAdjust" location="top">
                  <template #activator="{ props: tp }">
                    <VBtn v-bind="tp" icon="tabler-scale" variant="text" size="x-small"
                      color="medium-emphasis" class="cwallet__adj"
                      @click.stop="emit('adjust', wallet)" />
                  </template>
                  <span>Ajuste contable</span>
                </VTooltip>
              </div>
            </div>
          </div>
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
  width: 28px; height: 28px;
  border-radius: 5px;
  background: rgb(var(--v-theme-primary));
  flex-shrink: 0;
}
.topbar-title {
  font-size: 0.8rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 1.5px;
}

/* Rate pills */
.rate-pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px;
  border-radius: 5px;
  border: 1px solid;
  font-weight: 800;
  cursor: default;
  user-select: none;
  transition: opacity 0.15s;
  background: rgba(var(--v-theme-surface), 0.9);
}
.rate-pill:hover { opacity: 0.8; }
.rate-pill--bs  { border-color: rgba(var(--v-theme-error),   0.3); color: rgb(var(--v-theme-error)); }
.rate-pill--cop { border-color: rgba(var(--v-theme-primary), 0.3); color: rgb(var(--v-theme-primary)); }

.rate-pill__label { font-size: 0.6rem; letter-spacing: 0.07em; text-transform: uppercase; opacity: 0.7; }
.rate-pill__sep   { font-size: 0.65rem; opacity: 0.35; }
.rate-pill__value { font-size: 0.75rem; font-weight: 900; color: rgb(var(--v-theme-on-surface)); }

/* Total pill */
.total-pill { display: flex; flex-direction: column; align-items: flex-end; line-height: 1; }
.total-pill__label { font-size: 0.55rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.4; margin-bottom: 1px; }
.total-pill__value { font-size: 0.95rem; font-weight: 900; color: rgb(var(--v-theme-success)); letter-spacing: -0.3px; }

/* ══════════════════════════════════════════════════
   VISTA EXPANDIDA — cabecera de moneda
══════════════════════════════════════════════════ */
.cur-header {
  padding: 6px 12px;
  border-radius: 5px;
}
.cur-header__name   { font-size: 0.72rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.8px; }
.cur-header__ticker { font-size: 0.58rem; font-weight: 700; opacity: 0.4; text-transform: uppercase; }
.cur-header__total  { font-size: 0.88rem; font-weight: 900; letter-spacing: -0.2px; }

/* ── Method Card (expanded) ── */
.mcard {
  position: relative;
  display: flex; flex-direction: column; gap: 3px;
  padding: 10px 10px 8px;
  border-radius: 5px;
  background: rgba(var(--v-theme-surface), 0.95);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.07);
  cursor: pointer;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  min-height: 108px;
}
.mcard:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px -6px rgba(0,0,0,0.14);
}
.mcard--sel { border-color: var(--mc); box-shadow: 0 0 0 1.5px color-mix(in srgb, var(--mc) 25%, transparent); }
.mcard--neg { border-color: rgba(var(--v-theme-error), 0.35); }

.mcard__adj {
  position: absolute; inset-block-start: 4px; inset-inline-end: 4px;
  z-index: 5; opacity: 0; transition: opacity 0.2s;
}
.mcard:hover .mcard__adj { opacity: 1; }

.mcard__icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px;
  border-radius: 5px;
  margin-bottom: 3px;
  flex-shrink: 0;
}

.mcard__label {
  font-size: 0.58rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.07em;
  opacity: 0.45;
  line-height: 1;
}

.mcard__amount-row { display: flex; align-items: baseline; gap: 2px; margin-top: 1px; }
.mcard__prefix { font-size: 0.55rem; font-weight: 800; opacity: 0.4; line-height: 1; }
.mcard__amount { font-size: 0.95rem; font-weight: 900; letter-spacing: -0.3px; }
.mcard__amount--neg { color: rgb(var(--v-theme-error)); }

.mcard__footer {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: auto; padding-top: 5px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.06);
}
.mcard__in, .mcard__out {
  display: flex; align-items: center; gap: 2px;
  font-size: 0.58rem; font-weight: 800;
}
.mcard__in  { color: rgb(var(--v-theme-success)); }
.mcard__out { color: rgb(var(--v-theme-error));   }

.mcard__bar {
  position: absolute; inset-block-start: 0; inset-inline-start: 0;
  width: 3px; height: 100%;
  border-radius: 5px 0 0 5px;
  opacity: 0.5;
}

/* ══════════════════════════════════════════════════
   VISTA COMPACTA — cblock
══════════════════════════════════════════════════ */
.cblock {
  border-radius: 5px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  overflow: hidden;
}

/* Cabecera */
.cblock__head {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 14px;
  background: var(--cbalpha);
  border-left: 3px solid var(--cb);
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.06);
}
.cblock__icon {
  display: flex; align-items: center; justify-content: center;
  width: 24px; height: 24px; border-radius: 4px;
  background: var(--cb);
  flex-shrink: 0;
}
.cblock__name   { font-size: 0.7rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; }
.cblock__ticker { font-size: 0.57rem; font-weight: 700; opacity: 0.38; text-transform: uppercase; }

/* Total siempre en una línea, a la derecha con el label encima */
.cblock__total-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  margin-left: auto;
  line-height: 1;
  gap: 2px;
}
.cblock__total-label {
  font-size: 0.52rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  opacity: 0.38;
}
.cblock__total {
  font-size: 1rem;
  font-weight: 900;
  color: var(--cb);
  letter-spacing: -0.3px;
  white-space: nowrap;
}

/* Row de wallets */
.cblock__row {
  display: flex; flex-wrap: wrap;
  background: rgba(var(--v-theme-surface), 0.97);
}

/* Wallet item */
.cwallet {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px;
  border-right: 1px solid rgba(var(--v-theme-on-surface), 0.05);
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.04);
  cursor: pointer;
  flex: 1 1 180px;        /* más ancho base para que el monto no corte */
  min-width: 165px;
  position: relative;
  transition: background 0.18s ease;
}
.cwallet:last-child { border-right: none; }
.cwallet:hover { background: rgba(var(--v-theme-primary), 0.03); }
.cwallet:hover .cwallet__adj { opacity: 0.65 !important; }

.cwallet--active { background: rgba(var(--v-theme-primary), 0.05) !important; }
.cwallet--active::after {
  content: '';
  position: absolute; inset-block-start: 0; inset-inline-start: 0;
  width: 100%; height: 2px;
  background: var(--cb, rgb(var(--v-theme-primary)));
}
.cwallet--neg { background: rgba(var(--v-theme-error), 0.03); }

.cwallet__icon {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border-radius: 5px;
  flex-shrink: 0;
}
.cwallet__body {
  display: flex; flex-direction: column; gap: 2px;
  line-height: 1; min-width: 0; flex: 1;
}
.cwallet__method  {
  font-size: 0.58rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.07em;
  opacity: 0.4; white-space: nowrap;
}
.cwallet__balance {
  font-size: 0.92rem;        /* más grande para legibilidad */
  font-weight: 900;
  letter-spacing: -0.3px;
  white-space: nowrap;       /* nunca cortar el monto a 2 líneas */
  overflow: hidden;
  text-overflow: ellipsis;
}
.cwallet__balance--neg { color: rgb(var(--v-theme-error)); }

.cwallet__dot {
  position: absolute; inset-block-start: 50%; inset-inline-end: 8px;
  transform: translateY(-50%);
  width: 5px; height: 5px; border-radius: 50%;
  opacity: 0.75;
}
.cwallet__adj { opacity: 0; transition: opacity 0.18s; flex-shrink: 0; }
</style>
