<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  monthlyCash:       { type: Array,  required: true },
  loading:           { type: Boolean, default: false },
  totalMonthlyCash:  { type: Number, required: true },
  itemsPerPage:      { type: Number, required: true },
  page:              { type: Number, required: true },
  tpvPaymentMethods: { type: Object, default: () => ({ COP: [], USD: [], BS: [] }) }
});

const emit = defineEmits(['update:options', 'view-cash']);

const { mobile } = useDisplay();

const fmtUsd = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return "0,00 USD";
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num) + " USD";
};

const fmtCop = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return "0 COP";
  return Math.round(num)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
};

const fmtBs = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return "0,00 Bs.";
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num) + " Bs.";
};

const getPaymentDetail = (item, currency) => {
  if (!item) return [];
  const details = [];
  
  let rawMethods = props.tpvPaymentMethods;
  if (typeof rawMethods === 'string') {
    try {
      rawMethods = JSON.parse(rawMethods);
    } catch (e) {
      rawMethods = {};
    }
  }
  
  const defaultMethods = {
    USD: [
      { value: 'cash', label: 'Efectivo' },
      { value: 'bank_transfer', label: 'Transferencia' },
      { value: 'paypal', label: 'PayPal' },
      { value: 'binance', label: 'Binance' },
      { value: 'credit', label: 'Crédito' }
    ],
    COP: [
      { value: 'cash', label: 'Efectivo' },
      { value: 'bank_transfer', label: 'Transferencia' }
    ],
    BS: [
      { value: 'cash_bs', label: 'Efectivo' },
      { value: 'mobile_payment', label: 'Pago Móvil' },
      { value: 'debit_card', label: 'Tarjeta Débito' },
      { value: 'credit_card', label: 'Tarjeta Crédito' },
      { value: 'bank_transfer_bs', label: 'Transferencia' }
    ]
  };

  const methods = (rawMethods && Object.keys(rawMethods).length > 0) ? rawMethods : defaultMethods;
  
  if (currency === 'USD') {
    const currencyObj = methods.USD || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : (Array.isArray(currencyObj) ? currencyObj : []);
    
    activeMethods.forEach(m => {
      let val = 0;
      if (m.value === 'cash') val = parseFloat(item.usd_cash || 0);
      else if (m.value === 'bank_transfer') val = parseFloat(item.usd_transfer || 0);
      else if (m.value === 'paypal') val = parseFloat(item.usd_paypal || 0);
      else if (m.value === 'binance') val = parseFloat(item.usd_binance || 0);
      else if (m.value === 'credit') val = parseFloat(item.usd_credit || 0);
      
      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtUsd(val) });
      }
    });
  } else if (currency === 'COP') {
    const currencyObj = methods.COP || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : (Array.isArray(currencyObj) ? currencyObj : []);
    
    activeMethods.forEach(m => {
      let val = 0;
      if (m.value === 'cash') val = parseFloat(item.cop_cash || 0);
      else if (m.value === 'bank_transfer') val = parseFloat(item.cop_transfer || 0);
      
      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtCop(val) });
      }
    });
    
    if (parseFloat(item.cop_spare || 0) > 0) {
      details.push({ label: "Sobrante", value: fmtCop(item.cop_spare) });
    }
  } else if (currency === 'BS') {
    const currencyObj = methods.BS || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : (Array.isArray(currencyObj) ? currencyObj : []);
    
    activeMethods.forEach(m => {
      let val = 0;
      if (m.value === 'cash_bs') val = parseFloat(item.bs_cash || 0);
      else if (m.value === 'mobile_payment') val = parseFloat(item.bs_mobile || 0);
      else if (m.value === 'debit_card') val = parseFloat(item.bs_card_debito || 0);
      else if (m.value === 'credit_card') val = parseFloat(item.bs_card_credit || 0);
      else if (m.value === 'bank_transfer_bs') val = parseFloat(item.bs_transfer || 0);
      
      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtBs(val) });
      }
    });
  }
  return details;
};

const headers = [
  { title: "Período",         key: "period",              sortable: true },
  { title: "Crédito",         key: "amount_credits",       sortable: false, align: "end" },
  { title: "USD",             key: "amount_usd",           sortable: true, align: "end" },
  { title: "COP",             key: "amount_cop",           sortable: true, align: "end" },
  { title: "Bs.",             key: "amount_bs",            sortable: true, align: "end" },
  { title: "Total USD",       key: "total_usd_equivalent", sortable: false, align: "end" },
  { title: "Días",            key: "days_closed",          sortable: false, align: "center" },
  { title: "Promedio/Día",    key: "daily_average",        sortable: true, align: "end" },
  { title: "Acciones",        key: "actions",              sortable: false, align: "center", width: "100px" },
];

const getAvatarColor = (id) => {
  const colors = ["primary", "success", "info", "warning", "error", "purple"];
  return colors[id % colors.length];
};
</script>

<template>
  <div class="mt-4">
    <!-- Vista Escritorio -->
    <VCard v-if="!mobile" class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardItem class="pa-4 pb-0">
        <template #prepend>
          <VAvatar color="success" variant="tonal" size="38" class="rounded-lg">
            <VIcon icon="tabler-chart-area" size="20" />
          </VAvatar>
        </template>
        <VCardTitle class="text-subtitle-1 font-weight-black uppercase">Cierres Mensuales</VCardTitle>
        <VCardSubtitle class="text-xs font-weight-medium text-disabled">Totales convertidos a USD equivalente para comparación correcta</VCardSubtitle>
      </VCardItem>

      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.monthlyCash"
        :items-length="props.totalMonthlyCash"
        :loading="props.loading"
        no-data-text="No hay cierres mensuales registrados"
        class="text-no-wrap premium-table"
        @update:options="(opt) => emit('update:options', opt)"
      >
        <template #item.period="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar size="32" :color="getAvatarColor(item.id)" variant="tonal" class="rounded-lg font-weight-black text-xs">
              <VIcon icon="tabler-calendar-month" size="16" />
            </VAvatar>
            <span class="text-sm font-weight-black uppercase">{{ item.period }}</span>
          </div>
        </template>

        <template #item.amount_credits="{ item }">
          <span class="text-sm font-weight-bold text-disabled">{{ item.amount_credits ?? '0,00' }} USD</span>
        </template>

        <template #item.amount_usd="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <span class="text-sm font-weight-bold text-primary">{{ item.amount_usd }} USD</span>
            <VMenu v-if="getPaymentDetail(item, 'USD').length > 1" open-on-hover close-on-content-click location="top">
              <template #activator="{ props: menuProps }">
                <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer animate-pulse" />
              </template>
              <VCard class="pa-2 text-xs" min-width="160">
                <div v-for="det in getPaymentDetail(item, 'USD')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                  <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                  <span class="font-weight-black">{{ det.value }}</span>
                </div>
              </VCard>
            </VMenu>
          </div>
        </template>

        <template #item.amount_cop="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <span class="text-sm font-weight-bold text-success">{{ item.amount_cop }} COP</span>
            <VMenu v-if="getPaymentDetail(item, 'COP').length > 0" open-on-hover close-on-content-click location="top">
              <template #activator="{ props: menuProps }">
                <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer animate-pulse" />
              </template>
              <VCard class="pa-2 text-xs" min-width="160">
                <div v-for="det in getPaymentDetail(item, 'COP')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                  <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                  <span class="font-weight-black">{{ det.value }}</span>
                </div>
              </VCard>
            </VMenu>
          </div>
        </template>

        <template #item.amount_bs="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <span class="text-sm font-weight-bold text-warning">{{ item.amount_bs }} Bs.</span>
            <VMenu v-if="getPaymentDetail(item, 'BS').length > 1" open-on-hover close-on-content-click location="top">
              <template #activator="{ props: menuProps }">
                <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer animate-pulse" />
              </template>
              <VCard class="pa-2 text-xs" min-width="160">
                <div v-for="det in getPaymentDetail(item, 'BS')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                  <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                  <span class="font-weight-black">{{ det.value }}</span>
                </div>
              </VCard>
            </VMenu>
          </div>
        </template>

        <template #item.total_usd_equivalent="{ item }">
          <VChip color="primary" size="x-small" variant="flat" class="font-weight-black rounded px-2">
            {{ item.total_usd_equivalent ?? '—' }} USD
          </VChip>
        </template>

        <template #item.days_closed="{ item }">
          <VChip size="x-small" variant="tonal" class="font-weight-black rounded px-2">{{ item.days_closed }} DÍAS</VChip>
        </template>

        <template #item.daily_average="{ item }">
          <span class="text-sm font-weight-black">{{ item.daily_average }} USD</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VTooltip text="Ver Detalle Mensual" location="top">
              <template #activator="{ props: tip }">
                <VBtn v-bind="tip" icon="tabler-eye" size="32" variant="text" color="info" class="rounded-lg" @click="emit('view-cash', item)" />
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil Cards -->
    <div v-else class="d-flex flex-column gap-4">
      <VCard
        v-for="item in props.monthlyCash"
        :key="item.id"
        class="rounded-lg border shadow-sm premium-card overflow-hidden"
      >
        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center gap-3">
              <VAvatar size="42" :color="getAvatarColor(item.id)" variant="tonal" class="rounded-lg">
                <VIcon icon="tabler-calendar-stats" size="20" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-black leading-tight uppercase">{{ item.period }}</span>
                <span class="text-xs text-disabled font-weight-bold uppercase">Consolidado Mensual {{ item.id }}</span>
              </div>
            </div>
            <VChip color="success" variant="flat" size="x-small" class="font-weight-black rounded px-2">
              MENSUAL
            </VChip>
          </div>

          <VDivider class="mb-4 opacity-10" />

          <!-- Resumen de Totales Físicos -->
          <div class="d-flex flex-column gap-2 mb-4">
            <div class="d-flex justify-space-between align-center px-1">
              <span class="text-xs text-disabled font-weight-bold uppercase">Crédito</span>
              <span class="text-xs font-weight-black text-disabled">{{ item.amount_credits ?? '0,00' }} USD</span>
            </div>
            <div class="d-flex justify-space-between align-center px-1">
              <span class="text-xs text-disabled font-weight-bold uppercase">USD Físico</span>
              <span class="text-xs font-weight-black text-primary">{{ item.amount_usd }} USD</span>
            </div>
            <div class="d-flex justify-space-between align-center px-1">
              <span class="text-xs text-disabled font-weight-bold uppercase">COP Físico</span>
              <span class="text-xs font-weight-black text-success">{{ item.amount_cop }} COP</span>
            </div>
            <div class="d-flex justify-space-between align-center px-1">
              <span class="text-xs text-disabled font-weight-bold uppercase">BS Físico</span>
              <span class="text-xs font-weight-black text-warning">{{ item.amount_bs }} Bs.</span>
            </div>
          </div>

          <!-- Total Unificado y Promedios -->
          <div class="bg-primary-gradient pa-4 rounded-lg shadow-sm mb-4">
             <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-xs font-weight-black text-white uppercase opacity-80">Total Equivalente</span>
                <span class="text-lg font-weight-black text-white leading-none">{{ item.total_usd_equivalent }} USD</span>
             </div>
             <VDivider class="mb-2 opacity-20 border-white" />
             <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                   <span class="text-super-xs text-white opacity-60 font-weight-bold uppercase">Promedio Diario</span>
                   <span class="text-xs font-weight-black text-white">{{ item.daily_average }} USD/día</span>
                </div>
                <div class="d-flex flex-column align-end">
                   <span class="text-super-xs text-white opacity-60 font-weight-bold uppercase">Días Cerrados</span>
                   <span class="text-xs font-weight-black text-white">{{ item.days_closed }} DÍAS</span>
                </div>
             </div>
          </div>

          <!-- Acciones Móvil -->
          <div class="d-flex align-center">
            <VBtn
              block
              color="primary"
              variant="tonal"
              class="rounded-lg font-weight-black text-xs h-10"
              prepend-icon="tabler-eye"
              @click="emit('view-cash', item)"
            >
              VER DETALLE MENSUAL
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <VAlert v-if="props.monthlyCash.length === 0" type="info" variant="tonal" class="rounded-lg">
        No hay registros mensuales para mostrar.
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  block-size: 44px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-tight {
  line-height: 1.25;
}

.bg-primary-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #9575cd 100%);
}

.h-10 {
  block-size: 40px !important;
}

.text-white-opacity-60 {
  color: rgba(255, 255, 255, 60%);
}
</style>
