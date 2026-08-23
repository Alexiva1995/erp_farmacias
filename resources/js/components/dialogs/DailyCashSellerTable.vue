<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  filteredCashClosings: {
    type: Array,
    default: () => [],
  },
  tpvPaymentMethods: {
    type: Object,
    default: () => ({ COP: [], USD: [], BS: [] }),
  },
  totalCreditsUsdGlobal: {
    type: Number,
    default: 0,
  },
  totalUsdGlobal: {
    type: Number,
    default: 0,
  },
  totalCopGlobal: {
    type: Number,
    default: 0,
  },
  totalBsGlobal: {
    type: Number,
    default: 0,
  },
  totalSalesGlobal: {
    type: Number,
    default: 0,
  },
});

const fmtUsd = (val) => {
  try {
    const num = parseFloat(val);
    if (isNaN(num)) return "0,00 USD";
    return new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num) + " USD";
  } catch (e) {
    return "0,00 USD";
  }
};

const fmtCop = (val) => {
  try {
    const num = parseFloat(val);
    if (isNaN(num)) return "0 COP";
    return Math.round(num)
      .toString()
      .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
  } catch (e) {
    return "0 COP";
  }
};

const fmtBs = (val) => {
  try {
    const num = parseFloat(val);
    if (isNaN(num)) return "0,00 Bs.";
    return new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num) + " Bs.";
  } catch (e) {
    return "0,00 Bs.";
  }
};

const formatUsername = (username) => {
  if (!username) return "—";
  const parts = username
    .replace(/[._]/g, " ")
    .split(" ")
    .filter((word) => word.length > 0);
  return parts
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(" ");
};

const getPaymentDetail = (item, currency) => {
  if (!item) return [];
  const details = [];

  let rawMethods = props.tpvPaymentMethods;
  if (typeof rawMethods === "string") {
    try {
      rawMethods = JSON.parse(rawMethods);
    } catch (e) {
      rawMethods = {};
    }
  }
  const methods = rawMethods || {};

  if (currency === "USD") {
    const currencyObj = methods.USD || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];

    activeMethods.forEach((m) => {
      let val = 0;
      if (m.value === "cash") val = parseFloat(item.usd_cash || 0);
      else if (m.value === "bank_transfer") val = parseFloat(item.usd_transfer || 0);
      else if (m.value === "paypal") val = parseFloat(item.usd_paypal || 0);
      else if (m.value === "binance") val = parseFloat(item.usd_binance || 0);
      else if (m.value === "credit") val = parseFloat(item.usd_credit || 0);

      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtUsd(val) });
      }
    });
  } else if (currency === "COP") {
    const currencyObj = methods.COP || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];

    activeMethods.forEach((m) => {
      let val = 0;
      if (m.value === "cash_cop") val = parseFloat(item.cop_cash || 0);
      else if (m.value === "bank_transfer_cop") val = parseFloat(item.cop_transfer || 0);

      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtCop(val) });
      }
    });
  } else if (currency === "BS") {
    const currencyObj = methods.BS || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];

    activeMethods.forEach((m) => {
      let val = 0;
      if (m.value === "cash_bs") val = parseFloat(item.bs_cash || 0);
      else if (m.value === "mobile_payment") val = parseFloat(item.bs_mobile || 0);
      else if (m.value === "debit_card") val = parseFloat(item.bs_card_debito || 0);
      else if (m.value === "credit_card") val = parseFloat(item.bs_card_credit || 0);
      else if (m.value === "bank_transfer_bs") val = parseFloat(item.bs_transfer || 0);

      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtBs(val) });
      }
    });
  }
  return details;
};

const hasMismatchForCurrency = (closing, currency) => {
  if (!closing || !closing.blind_mismatches) return false;
  const mismatches = Array.isArray(closing.blind_mismatches)
    ? closing.blind_mismatches
    : typeof closing.blind_mismatches === "string"
    ? JSON.parse(closing.blind_mismatches)
    : [];

  if (currency === "USD") {
    return mismatches.some((m) => m.includes("usd") || m === "declared_usd");
  }
  if (currency === "COP") {
    const sysCop = parseFloat(closing.cop_cash || 0) + parseFloat(closing.cop_cash_payment_credit || 0);
    const decCop = parseFloat(closing.declared_cop || 0);
    return decCop < sysCop && mismatches.some((m) => m.includes("cop") || m === "declared_cop");
  }
  if (currency === "BS") {
    return mismatches.some((m) => m.includes("bs") || m.includes("card") || m.includes("mobile"));
  }
  if (currency === "CREDIT") {
    return mismatches.some((m) => m.includes("credit") || m === "declared_credit");
  }
  return false;
};

const getMismatchText = (closing, currency) => {
  if (!closing || !closing.blind_note) return "Sin detalles del descuadre.";
  const notes = closing.blind_note.split("|");
  const matchedNotes = notes.filter((note) => {
    const noteUpper = note.toUpperCase();
    if (currency === "USD") return noteUpper.includes("USD") || noteUpper.includes("DÓLAR") || noteUpper.includes("DOLAR");
    if (currency === "COP") return noteUpper.includes("COP") || noteUpper.includes("PESO");
    if (currency === "BS")
      return (
        noteUpper.includes("BS") ||
        noteUpper.includes("BOLÍVAR") ||
        noteUpper.includes("BOLIVAR") ||
        noteUpper.includes("TARJETA") ||
        noteUpper.includes("PAGO MÓVIL") ||
        noteUpper.includes("PAGO MOVIL")
      );
    if (currency === "CREDIT") return noteUpper.includes("CRÉDITO") || noteUpper.includes("CREDITO");
    return false;
  });

  return matchedNotes.length > 0 ? matchedNotes.join(" | ").trim() : closing.blind_note;
};
</script>

<template>
  <div>
    <!-- DESKTOP: Tabla Arqueo de Cajas -->
    <VCard variant="flat" class="rounded-xl border shadow-sm mb-5 overflow-hidden d-none d-sm-block">
      <VTable density="compact" class="text-no-wrap premium-table">
        <thead>
          <tr class="bg-grey-lighten-4">
            <th class="text-uppercase text-caption font-weight-black text-disabled">Vendedor</th>
            <th class="text-uppercase text-caption font-weight-black text-disabled text-end">Crédito</th>
            <th class="text-uppercase text-caption font-weight-black text-disabled text-end">USD</th>
            <th class="text-uppercase text-caption font-weight-black text-disabled text-end">COP</th>
            <th class="text-uppercase text-caption font-weight-black text-disabled text-end">Bs.</th>
            <th class="text-uppercase text-caption font-weight-black text-disabled text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="props.filteredCashClosings.length === 0">
            <td colspan="6" class="text-center text-caption text-disabled py-4">No hay cajas con ventas registradas</td>
          </tr>
          <tr v-for="c in props.filteredCashClosings" :key="c.id" class="seller-row">
            <td class="py-2">
              <div class="d-flex align-center gap-3">
                <VAvatar size="32" color="primary" variant="tonal" class="rounded-lg font-weight-black text-xs">
                  {{ (c.seller?.username || '?').charAt(0).toUpperCase() }}
                </VAvatar>
                <div class="d-flex flex-column leading-none">
                  <span class="text-sm font-weight-black text-primary text-capitalize">{{ formatUsername(c.seller?.username) }}</span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase">ID: {{ c.id }}</span>
                </div>
              </div>
            </td>
            <td class="text-end">
              <span class="text-sm font-weight-bold text-error">{{ fmtUsd(c.usd_credit) }}</span>
            </td>
            <td class="text-end">
              <div class="d-flex align-center justify-end gap-1">
                <span class="text-sm font-weight-bold">{{ fmtUsd(c.usd_delivered) }}</span>
                <!-- Descuadre USD -->
                <VMenu v-if="hasMismatchForCurrency(c, 'USD')" close-on-content-click location="top">
                  <template #activator="{ props: menuProps }">
                    <VIcon v-bind="menuProps" icon="tabler-alert-triangle" size="15" color="error" class="cursor-pointer me-1 animate-pulse" />
                  </template>
                  <VCard class="pa-3 text-xs bg-error-lighten-5 border-error" min-width="220">
                    <div class="font-weight-black text-error mb-1 d-flex align-center gap-1">
                      <VIcon icon="tabler-alert-triangle" size="14" />
                      <span>DESCUADRE USD DETECTADO:</span>
                    </div>
                    <div class="text-disabled font-weight-medium" style="line-height: 1.3;">{{ getMismatchText(c, 'USD') }}</div>
                  </VCard>
                </VMenu>

                <!-- Detalle Métodos USD -->
                <VMenu v-if="getPaymentDetail(c, 'USD').length > 1" open-on-hover close-on-content-click location="top">
                  <template #activator="{ props: menuProps }">
                    <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
                  </template>
                  <VCard class="pa-2 text-xs" min-width="160">
                    <div v-for="det in getPaymentDetail(c, 'USD')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                      <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                      <span class="font-weight-black">{{ det.value }}</span>
                    </div>
                  </VCard>
                </VMenu>
              </div>
            </td>
            <td class="text-end">
              <div class="d-flex align-center justify-end gap-1">
                <span class="text-sm font-weight-bold text-success">{{ fmtCop(parseFloat(c.cop_delivered || 0) + parseFloat(c.cop_transfer || 0)) }}</span>
                <!-- Descuadre COP -->
                <VMenu v-if="hasMismatchForCurrency(c, 'COP')" close-on-content-click location="top">
                  <template #activator="{ props: menuProps }">
                    <VIcon v-bind="menuProps" icon="tabler-alert-triangle" size="15" color="error" class="cursor-pointer me-1 animate-pulse" />
                  </template>
                  <VCard class="pa-3 text-xs bg-error-lighten-5 border-error" min-width="220">
                    <div class="font-weight-black text-error mb-1 d-flex align-center gap-1">
                      <VIcon icon="tabler-alert-triangle" size="14" />
                      <span>DESCUADRE COP DETECTADO:</span>
                    </div>
                    <div class="text-disabled font-weight-medium" style="line-height: 1.3;">{{ getMismatchText(c, 'COP') }}</div>
                  </VCard>
                </VMenu>

                <!-- Detalle Métodos COP -->
                <VMenu v-if="getPaymentDetail(c, 'COP').length > 1" open-on-hover close-on-content-click location="top">
                  <template #activator="{ props: menuProps }">
                    <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
                  </template>
                  <VCard class="pa-2 text-xs" min-width="160">
                    <div v-for="det in getPaymentDetail(c, 'COP')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                      <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                      <span class="font-weight-black">{{ det.value }}</span>
                    </div>
                  </VCard>
                </VMenu>
              </div>
            </td>
            <td class="text-end">
              <div class="d-flex align-center justify-end gap-1">
                <span class="text-sm font-weight-bold text-warning">{{ fmtBs(c.total_bs) }}</span>
                <!-- Descuadre BS -->
                <VMenu v-if="hasMismatchForCurrency(c, 'BS')" close-on-content-click location="top">
                  <template #activator="{ props: menuProps }">
                    <VIcon v-bind="menuProps" icon="tabler-alert-triangle" size="15" color="error" class="cursor-pointer me-1 animate-pulse" />
                  </template>
                  <VCard class="pa-3 text-xs bg-error-lighten-5 border-error" min-width="220">
                    <div class="font-weight-black text-error mb-1 d-flex align-center gap-1">
                      <VIcon icon="tabler-alert-triangle" size="14" />
                      <span>DESCUADRE BS DETECTADO:</span>
                    </div>
                    <div class="text-disabled font-weight-medium" style="line-height: 1.3;">{{ getMismatchText(c, 'BS') }}</div>
                  </VCard>
                </VMenu>

                <!-- Detalle Métodos BS -->
                <VMenu v-if="getPaymentDetail(c, 'BS').length > 1" open-on-hover close-on-content-click location="top">
                  <template #activator="{ props: menuProps }">
                    <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
                  </template>
                  <VCard class="pa-2 text-xs" min-width="160">
                    <div v-for="det in getPaymentDetail(c, 'BS')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                      <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                      <span class="font-weight-black">{{ det.value }}</span>
                    </div>
                  </VCard>
                </VMenu>
              </div>
            </td>
            <td class="text-end">
              <VChip size="small" variant="flat" color="primary" class="font-weight-black rounded px-2">
                {{ fmtUsd(c.total_sales) }}
              </VChip>
            </td>
          </tr>
          <!-- Fila de totales -->
          <tr v-if="props.filteredCashClosings.length > 0" class="bg-grey-lighten-3 font-weight-black" style="font-size: 0.95rem;">
            <td class="uppercase py-3 pl-3 text-primary text-subtitle-2 font-weight-black">TOTAL CONSOLIDADO</td>
            <td class="text-end text-error">{{ fmtUsd(props.totalCreditsUsdGlobal) }}</td>
            <td class="text-end">{{ fmtUsd(props.totalUsdGlobal) }}</td>
            <td class="text-end text-success">{{ fmtCop(props.totalCopGlobal) }}</td>
            <td class="text-end text-warning">{{ fmtBs(props.totalBsGlobal) }}</td>
            <td class="text-end">
              <VChip size="medium" variant="flat" color="primary" class="font-weight-black rounded px-3 text-subtitle-2 py-1">
                {{ fmtUsd(props.totalSalesGlobal) }}
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- MOBILE: Tarjetas Arqueo por Cajas -->
    <VRow class="d-flex d-sm-none mb-4 row-gap-3">
      <VCol v-if="props.filteredCashClosings.length === 0" cols="12">
        <VAlert type="info" variant="tonal" class="rounded-xl text-button font-weight-black" icon="tabler-info-circle">
          NO SE ENCONTRARON CAJAS CON VENTAS REGISTRADAS
        </VAlert>
      </VCol>
      <VCol v-for="c in props.filteredCashClosings" :key="'m-' + c.id" cols="12">
        <VCard variant="flat" class="rounded-xl border shadow-md overflow-hidden">
          <div class="pa-3 border-b d-flex justify-space-between align-center">
            <div class="d-flex gap-2 align-center">
              <VAvatar color="primary" size="36" variant="tonal" class="font-weight-black rounded-lg text-sm">
                {{ (c.seller?.username || '?').charAt(0).toUpperCase() }}
              </VAvatar>
              <div class="leading-none">
                <div class="text-subtitle-2 font-weight-black text-capitalize">{{ formatUsername(c.seller?.username) }}</div>
                <span class="text-super-xs text-disabled font-weight-bold uppercase">Caja #{{ c.id }}</span>
              </div>
            </div>
            <VChip color="primary" size="small" variant="flat" class="font-weight-black">
              {{ fmtUsd(c.real_total_usd) }}
            </VChip>
          </div>
          <VTable density="compact" class="text-caption bg-transparent">
            <tbody>
              <tr>
                <td class="font-weight-black text-disabled uppercase pl-4 py-1">Créditos:</td>
                <td class="text-right font-weight-black pr-4 py-1 text-error">{{ fmtUsd(c.usd_credit) }}</td>
              </tr>
              <tr>
                <td class="font-weight-black text-disabled uppercase pl-4 py-1">USD:</td>
                <td class="text-right font-weight-black pr-4 py-1 text-primary">{{ fmtUsd(c.total_usd) }}</td>
              </tr>
              <tr>
                <td class="font-weight-black text-disabled uppercase pl-4 py-1">COP:</td>
                <td class="text-right font-weight-black pr-4 py-1 text-success">{{ fmtCop(c.total_cop) }}</td>
              </tr>
              <tr>
                <td class="font-weight-black text-disabled uppercase pl-4 py-1">Bolívares:</td>
                <td class="text-right font-weight-bold pr-4 py-1 text-warning">{{ fmtBs(c.total_bs) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
.leading-none {
  line-height: 1 !important;
}
</style>
