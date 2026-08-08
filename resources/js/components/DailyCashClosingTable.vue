<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  dailyCash: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalDailyCash: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  tpvPaymentMethods: { type: Object, default: () => ({ COP: [], USD: [], BS: [] }) },
});

const emit = defineEmits([
  "update:options",
  "view-cash",
  "reference",
  "closing-daily",
  "mismatch",
]);

const { mobile } = useDisplay();

const headers = computed(() => [
  { title: "Fecha", key: "date", sortable: true },
  { title: "Crédito", key: "total_credits", sortable: true, align: "end" },
  { title: "USD", key: "usd_delivered", sortable: true, align: "end" },
  { title: "COP", key: "cop_delivered", sortable: true, align: "end" },
  { title: "Bs.", key: "total_bs", sortable: true, align: "end" },
  { title: "Total USD", key: "total_sales", sortable: true, align: "end" },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "140px",
  },
]);

const fmtDate = (v) => {
  if (!v) return "—";
  const date = new Date(v);
  const days = ["DOM", "LUN", "MAR", "MIÉ", "JUE", "VIE", "SÁB"];
  const months = [
    "ENE", "FEB", "MAR", "ABR", "MAY", "JUN",
    "JUL", "AGO", "SEP", "OCT", "NOV", "DIC",
  ];
  return `${days[date.getDay()]} ${date.getDate()} ${months[date.getMonth()]}`;
};

const fmtUsd = (v) =>
  new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(v ?? 0) + " USD";

const fmtCop = (v) =>
  Math.round(v ?? 0)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";

const fmtBs = (v) =>
  new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(v ?? 0) + " Bs.";

const getAvatarColor = (id) => {
  const colors = ["primary", "success", "info", "warning", "purple", "cyan"];
  return colors[id % colors.length];
};

/* 
  Construye el desglose por método de pago para el consolidado diario.
  Ahora daily_closures tiene columnas individuales por cada método,
  igual que cash_closing. Se cruzan con la config del TPV para mostrar
  solo los que tienen saldo > 0, con el nombre configurado.
*/
const getPaymentDetail = (item, currency) => {
  if (!item) return [];
  const details = [];

  let rawMethods = props.tpvPaymentMethods;
  if (typeof rawMethods === "string") {
    try { rawMethods = JSON.parse(rawMethods); } catch (e) { rawMethods = {}; }
  }
  const methods = rawMethods || {};

  if (currency === "USD") {
    const currencyObj = methods.USD || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];
    activeMethods.forEach((m) => {
      let val = 0;
      if (m.value === "cash")          val = parseFloat(item.usd_cash || 0);
      else if (m.value === "bank_transfer") val = parseFloat(item.usd_transfer || 0);
      else if (m.value === "paypal")   val = parseFloat(item.usd_paypal || 0);
      else if (m.value === "binance")  val = parseFloat(item.usd_binance || 0);
      else if (m.value === "credit")   val = parseFloat(item.total_credits || 0);
      if (val > 0) details.push({ label: m.label || m.value, value: fmtUsd(val) });
    });
  } else if (currency === "COP") {
    const currencyObj = methods.COP || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];
    activeMethods.forEach((m) => {
      let val = 0;
      if (m.value === "cash")          val = parseFloat(item.cop_cash || 0);
      else if (m.value === "bank_transfer") val = parseFloat(item.cop_transfer || 0);
      if (val > 0) details.push({ label: m.label || m.value, value: fmtCop(val) });
    });
  } else if (currency === "BS") {
    const currencyObj = methods.BS || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];
    activeMethods.forEach((m) => {
      let val = 0;
      if (m.value === "cash_bs")           val = parseFloat(item.bs_cash || 0);
      else if (m.value === "mobile_payment")  val = parseFloat(item.bs_mobile || 0);
      else if (m.value === "debit_card")   val = parseFloat(item.bs_card_debito || 0);
      else if (m.value === "credit_card")  val = parseFloat(item.bs_card_credit || 0);
      else if (m.value === "bank_transfer_bs") val = parseFloat(item.bs_transfer || 0);
      if (val > 0) details.push({ label: m.label || m.value, value: fmtBs(val) });
    });
  }
  return details;
};

const hasMismatch = (item) => {
  if (!item.cash_closings || !Array.isArray(item.cash_closings)) return false;
  return item.cash_closings.some((closing) => {
    let mismatches = closing.blind_mismatches;
    if (typeof mismatches === "string") {
      try { mismatches = JSON.parse(mismatches); } catch (e) { mismatches = []; }
    }
    if (!Array.isArray(mismatches) || mismatches.length === 0) return false;

    // Si tiene descuadres, verificamos si todos los de COP son sobrantes positivos
    return mismatches.some(m => {
      if (m.includes('cop') || m === 'declared_cop') {
        const sysCop = parseFloat(closing.cop_cash || 0) + parseFloat(closing.cop_cash_payment_credit || 0);
        const decCop = parseFloat(closing.declared_cop || 0);
        return decCop < sysCop; // Solo es descuadre si es faltante
      }
      return true; // Cualquier otra discrepancia en otra moneda sí cuenta como descuadre
    });
  });
};
</script>

<template>
  <div>
    <!-- Vista Escritorio -->
    <VCard
      v-if="!mobile"
      class="rounded-lg border shadow-sm overflow-hidden bg-surface"
    >
      <VCardItem class="pa-4 pb-0">
        <template #prepend>
          <VAvatar color="info" variant="tonal" size="38" class="rounded-lg">
            <VIcon icon="tabler-calendar-check" size="20" />
          </VAvatar>
        </template>
        <VCardTitle class="text-subtitle-1 font-weight-black uppercase"
          >Cierres Diarios</VCardTitle
        >
        <VCardSubtitle class="text-xs font-weight-medium text-disabled"
          >Consolidado de ventas por día con detalle de entregas</VCardSubtitle
        >
      </VCardItem>

      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.dailyCash"
        :items-length="props.totalDailyCash"
        :loading="props.loading"
        no-data-text="No hay cierres diarios registrados"
        class="text-no-wrap premium-table"
        @update:options="(opt) => emit('update:options', opt)"
      >
        <!-- Fecha -->
        <template #item.date="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              size="32"
              :color="getAvatarColor(item.id)"
              variant="tonal"
              class="rounded-lg font-weight-black text-xs"
            >
              <VIcon icon="tabler-calendar" size="16" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-black uppercase">{{
                fmtDate(item.created_at)
              }}</span>
              <VChip
                v-if="hasMismatch(item)"
                size="x-small"
                color="error"
                variant="flat"
                class="mt-1 font-weight-bold rounded px-1 text-uppercase align-self-start"
                style="font-size: 9px; height: 16px;"
              >
                Descuadre
              </VChip>
              <VChip
                v-else
                size="x-small"
                color="success"
                variant="tonal"
                class="mt-1 font-weight-bold rounded px-1 text-uppercase align-self-start"
                style="font-size: 9px; height: 16px;"
              >
                Cuadrado
              </VChip>
            </div>
          </div>
        </template>

        <!-- Crédito -->
        <template #item.total_credits="{ item }">
          <span class="text-sm font-weight-bold text-error">{{
            fmtUsd(item.total_credits)
          }}</span>
        </template>

        <!-- USD (entrega + popover) -->
        <template #item.usd_delivered="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <span class="text-sm font-weight-bold">{{ fmtUsd(item.usd_delivered) }}</span>
            <VMenu v-if="getPaymentDetail(item, 'USD').length > 0" open-on-hover close-on-content-click location="top">
              <template #activator="{ props: menuProps }">
                <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
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

        <!-- COP (entrega + popover) -->
        <template #item.cop_delivered="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <span class="text-sm font-weight-bold text-success">{{ fmtCop(parseFloat(item.cop_delivered || 0) + parseFloat(item.cop_transfer || 0)) }}</span>
            <VMenu v-if="getPaymentDetail(item, 'COP').length > 0" open-on-hover close-on-content-click location="top">
              <template #activator="{ props: menuProps }">
                <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
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

        <!-- Bs. (total + popover) -->
        <template #item.total_bs="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <span class="text-sm font-weight-bold text-warning">{{ fmtBs(item.total_bs) }}</span>
            <VMenu v-if="getPaymentDetail(item, 'BS').length > 0" open-on-hover close-on-content-click location="top">
              <template #activator="{ props: menuProps }">
                <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
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

        <!-- Total USD -->
        <template #item.total_sales="{ item }">
          <VChip
            size="x-small"
            variant="flat"
            color="primary"
            class="font-weight-black rounded px-2"
          >
            {{ fmtUsd(item.total_sales) }}
          </VChip>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VTooltip text="Ver Detalle" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-eye"
                  size="32"
                  variant="text"
                  color="info"
                  class="rounded-lg"
                  @click="emit('view-cash', item)"
                />
              </template>
            </VTooltip>
            <VTooltip text="Referencias" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-clipboard-list"
                  size="32"
                  variant="text"
                  color="secondary"
                  class="rounded-lg"
                  @click="emit('reference', item)"
                />
              </template>
            </VTooltip>
            <VTooltip text="Descuadres Contables" location="top">
              <template #activator="{ props: tip }">
                <VBtn
                  v-bind="tip"
                  icon="tabler-alert-triangle"
                  size="32"
                  variant="text"
                  :color="hasMismatch(item) ? 'error' : 'grey-lighten-1'"
                  :disabled="!hasMismatch(item)"
                  class="rounded-lg"
                  :class="{ 'animate-pulse': hasMismatch(item) }"
                  @click="emit('mismatch', item)"
                />
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil Cards -->
    <div v-else class="d-flex flex-column gap-4">
      <VCard
        v-for="item in props.dailyCash"
        :key="item.id"
        class="rounded-lg border shadow-sm premium-card overflow-hidden"
      >
        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="42"
                :color="getAvatarColor(item.id)"
                variant="tonal"
                class="rounded-lg"
              >
                <VIcon icon="tabler-calendar-event" size="20" />
              </VAvatar>
              <div class="d-flex flex-column">
                <div class="d-flex align-center gap-2">
                  <span
                    class="text-sm font-weight-black leading-tight uppercase"
                    >{{ fmtDate(item.created_at) }}</span
                  >
                  <VChip
                    v-if="hasMismatch(item)"
                    size="x-small"
                    color="error"
                    variant="flat"
                    class="font-weight-bold rounded px-1 text-uppercase"
                    style="font-size: 8px; height: 14px;"
                  >
                    Descuadre
                  </VChip>
                  <VChip
                    v-else
                    size="x-small"
                    color="success"
                    variant="tonal"
                    class="font-weight-bold rounded px-1 text-uppercase"
                    style="font-size: 8px; height: 14px;"
                  >
                    Cuadrado
                  </VChip>
                </div>
                <span class="text-xs text-disabled font-weight-bold uppercase"
                  >Consolidado Diario {{ item.id }}</span
                >
              </div>
            </div>
            <VChip
              color="info"
              variant="tonal"
              size="x-small"
              class="font-weight-black rounded px-2"
            >
              DIARIO
            </VChip>
          </div>

          <VDivider class="mb-4 opacity-10" />

          <!-- Grid de Totales -->
          <div class="d-flex flex-column gap-2 mb-4">
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Créditos (CUSD)</span
              >
              <span class="text-xs font-weight-black text-error">{{
                fmtUsd(item.total_credits)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >E. USD (Físico)</span
              >
              <span class="text-xs font-weight-black">{{
                fmtUsd(item.usd_delivered)
              }}</span>
            </div>
            <VDivider class="my-1 opacity-5" />
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >E. COP (Físico)</span
              >
              <span class="text-xs font-weight-black text-success">{{
                fmtCop(item.cop_delivered)
              }}</span>
            </div>
            <VDivider class="my-1 opacity-5" />
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Pago Móvil Bs.</span
              >
              <span class="text-xs font-weight-black text-info">{{
                fmtBs(item.bs_mobile)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Banco Bs.</span
              >
              <span class="text-xs font-weight-black text-info">{{
                fmtBs(item.bs_card)
              }}</span>
            </div>
            <div class="d-flex justify-space-between align-center px-2">
              <span class="text-xs text-disabled font-weight-bold uppercase"
                >Total Bs.</span
              >
              <span class="text-xs font-weight-black text-warning">{{
                fmtBs(item.total_bs)
              }}</span>
            </div>
          </div>

          <div
            class="bg-primary-gradient pa-3 rounded-lg d-flex justify-space-between align-center shadow-sm mb-4"
          >
            <span class="text-xs font-weight-black text-white uppercase"
              >Venta Total (USD)</span
            >
            <span class="text-sm font-weight-black text-white">{{
              fmtUsd(item.total_sales)
            }}</span>
          </div>

          <!-- Acciones Móvil -->
          <div class="d-flex align-center gap-2">
            <VBtn
              color="primary"
              variant="tonal"
              class="rounded-lg font-weight-black text-xs h-10 flex-grow-1"
              prepend-icon="tabler-eye"
              @click="emit('view-cash', item)"
            >
              DETALLES
            </VBtn>
            <VBtn
              icon="tabler-clipboard-list"
              color="secondary"
              variant="tonal"
              class="rounded-lg"
              size="40"
              min-width="40"
              @click="emit('reference', item)"
            />
            <VBtn
              v-if="hasMismatch(item)"
              icon="tabler-alert-triangle"
              color="error"
              variant="flat"
              class="rounded-lg animate-pulse"
              size="40"
              min-width="40"
              @click="emit('mismatch', item)"
            />
          </div>
        </VCardText>
      </VCard>

      <VAlert
        v-if="props.dailyCash.length === 0"
        type="info"
        variant="tonal"
        class="rounded-lg"
      >
        No hay registros diarios encontrados.
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.bg-primary-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #9575cd 100%
  );
}

.premium-card {
  position: relative;
}
</style>
