<script setup>
import PaymentDetailModal from "@/components/PaymentDetailModal.vue";
import axios from "@/plugins/axios";
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed, ref } from "vue";

const props = defineProps({
  transactions: { type: [Array, Object], default: () => [] },
  loading: { type: Boolean, default: false },
  dataDetailed: { type: Boolean, default: false },
  selectedCurrency: { type: String, default: "" },
  selectedTab: { type: String, default: "" },
  totalTransactions: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  rates: { type: Object, default: () => ({ bcv: { rate: 0 }, cop: { rate: 0 } }) },
});

const emit = defineEmits(["update:options", "update:selectedTab", "clear"]);

// Detección de transferencia entre cuentas
const isTransferTransaction = (desc) => {
  if (!desc) return false;
  return /transferencia\s+(enviada|recibida)/i.test(desc);
};

// Modal de Detalle de Pago
const showPaymentModal = ref(false);
const selectedPayment = ref(null);
const loadingPaymentDetail = ref(false);

// Funciones de formateo y parsing de descripción
const isInvoicePayment = (desc) => {
  if (!desc) return false;
  return /pago\s+factura/i.test(desc);
};

const extractInvoiceNumbers = (desc) => {
  if (!desc) return [];
  const match = desc.match(/pago\s+factura\(?s?\)?\s*#?\s*([^\(\n]+)/i);
  if (!match) return [];
  
  // Extraer tokens que parezcan números de factura
  const cleaned = match[1].trim();
  const tokens = cleaned.split(/[\s,]+/);
  return tokens.filter((t) => /^[A-Za-z0-9\-_]+$/.test(t) && t.length >= 2);
};

const cleanDescription = (desc) => {
  if (!desc) return "";
  
  // 1. Remover paréntesis y su contenido (ej: Origen: ... COP @ Tasa ...)
  let text = desc.replace(/\s*\([^)]*\)/g, "").trim();

  // 2. Si es pago de facturas, simplificar mostrando "Pago a PROVEEDOR" o "Pago factura(s) - PROVEEDOR"
  const match = text.match(/pago\s+factura\(?s?\)?\s*#?\s*(.+)$/i);
  if (match) {
    let rest = match[1].trim();
    // Remover números o listas de facturas al inicio (ej: "709484, 709486 DROMEGA" -> "DROMEGA")
    const cleanedSupplier = rest.replace(/^[0-9A-Za-z\-_,\s]+?\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-z0-9ÁÉÍÓÚáéíóúÑñ\s\.\-_&]+)$/, "$1");
    if (cleanedSupplier && cleanedSupplier !== rest) {
      return `Pago a ${cleanedSupplier.trim()}`;
    }
    // Si no hubo reemplazo por regex estricto, buscar última palabra o palabras en mayúsculas
    return `Pago a ${rest.trim()}`;
  }

  return text;
};

// Abrir detalle del pago
const openPaymentDetail = async (item) => {
  loadingPaymentDetail.value = true;
  try {
    const numbers = extractInvoiceNumbers(item.description);
    let paymentFound = null;

    if (numbers.length > 0) {
      for (const num of numbers) {
        const res = await axios.get("/finances/payment-history", {
          params: { search: num, itemsPerPage: 5 },
        });
        const list = res.data?.data?.data || [];
        if (list.length > 0) {
          paymentFound = list[0];
          break;
        }
      }
    }

    if (!paymentFound) {
      // Búsqueda alternativa por texto limpio o descripción
      const cleaned = cleanDescription(item.description).replace(/^Pago a\s+/i, "");
      const res = await axios.get("/finances/payment-history", {
        params: { search: cleaned, itemsPerPage: 5 },
      });
      const list = res.data?.data?.data || [];
      if (list.length > 0) {
        paymentFound = list[0];
      }
    }

    if (paymentFound) {
      selectedPayment.value = paymentFound;
      showPaymentModal.value = true;
    } else {
      // Fallback: construir objeto básico con la información de la transacción
      selectedPayment.value = {
        id: item.id,
        payment_date: item.transaction_date,
        currency: item.currency,
        amount: item.amount,
        amount_usd: item.currency === "USD" ? item.amount : (item.amount / (item.exchange_rate || 1)),
        source_amount: item.amount,
        source_currency: item.currency,
        reference: item.description,
        notes: item.description,
        user: { name: item.user_name || "Sistema" },
        invoices: extractInvoiceNumbers(item.description).map((num, i) => ({
          id: i + 1,
          invoice_number: num,
          total_amount: item.amount,
          total_usd: item.currency === "USD" ? item.amount : (item.amount / (item.exchange_rate || 1)),
          currency: item.currency,
          supplier: { name: cleanDescription(item.description).replace(/^Pago a\s+/i, "") },
        })),
      };
      showPaymentModal.value = true;
    }
  } catch (err) {
    console.error("Error cargando detalle del pago:", err);
  } finally {
    loadingPaymentDetail.value = false;
  }
};

// Hay una caja/moneda seleccionada → el balance corrido tiene sentido
const isFiltered = computed(() => !!props.selectedCurrency);

// Cabeceras activas: ocultar Balance Caja cuando no hay filtro de moneda
const allHeaders = [
  { title: "Día / Mov.", key: "direction",     sortable: false, align: "center", width: "90px" },
  { title: "Usuario",    key: "user_name",      sortable: false,                  width: "140px" },
  { title: "Descripción",key: "description",   sortable: false                                  },
  { title: "Tipo",       key: "type",           sortable: false, align: "center", width: "110px" },
  { title: "Monto",      key: "amount",         sortable: false, align: "end",    width: "160px" },
  { title: "Balance Caja",key: "balance",       sortable: false, align: "end",    width: "150px", filteredOnly: true },
];
const headers = computed(() =>
  allHeaders.filter((h) => !h.filteredOnly || isFiltered.value)
);

// Config visual por moneda
const CURRENCY_COLOR = { USD: 'warning', BS: 'error', COP: 'primary' };

const processedTransactions = computed(() => {
  const raw = Array.isArray(props.transactions)
    ? props.transactions
    : props.transactions?.data || [];
  return raw.map((t) => {
    // Detección robusta de entrada vs salida (insensible a mayúsculas y variaciones latinas)
    const mType = String(t.movement_type || "")
      .trim()
      .toUpperCase();
    const isEntry =
      ["IN", "ENTRADA", "INGRESO"].includes(mType) ||
      (mType === "" && parseFloat(t.amount) > 0);

    // Rescatar balance con fallback exhaustivo por si el backend lo envía con nombre distinto
    const rawBalance =
      t.balance ?? t.running_balance ?? t.current_balance ?? t.saldo ?? 0;

    return {
      ...t,
      isEntry,
      amount: Math.abs(parseFloat(t.amount) || 0),
      balance: parseFloat(rawBalance) || 0,
    };
  });
});

const groupedByDay = computed(() => {
  const map = {};
  for (const t of processedTransactions.value) {
    const day = t.transaction_date?.slice(0, 10) ?? "Sin fecha";
    if (!map[day])
      map[day] = { date: day, items: [], totalInUsd: 0, totalOutUsd: 0 };

    // ─── Clave de agrupación: misma descripción + tipo + moneda + dirección
    // Colapsa p.ej. múltiples entradas de "Cierre de caja #7861 / CASH / COP / IN"
    const groupKey = [
      t.description?.trim() ?? "",
      t.type ?? "",
      t.currency ?? "",
      t.isEntry ? "IN" : "OUT",
    ].join("|");

    const existing = map[day].items.find((i) => i._groupKey === groupKey);
    if (existing) {
      // Acumular en la fila existente
      existing.amount       += t.amount;
      existing.balance       = t.balance; // balance final del último registro
      existing._ids.push(t.id);
      existing._count++;
    } else {
      map[day].items.push({
        ...t,
        _groupKey: groupKey,
        _ids:      [t.id],
        _count:    1,
      });
    }

    // ─── Conversión precisa a USD para los totales del encabezado diario
    let rate = 1.0;
    if (t.currency === "USD") {
      rate = 1.0;
    } else if (t.currency === "COP") {
      // Priorizar tasa guardada en la transacción si es razonable para COP (> 500)
      const tRate = parseFloat(t.exchange_rate) || 0;
      if (tRate > 500) {
        rate = tRate;
      } else {
        const propRate = parseFloat(props.rates?.cop?.rate) || 0;
        rate = propRate > 0 ? propRate : 3170;
      }
    } else if (t.currency === "BS") {
      // Priorizar tasa guardada en la transacción si es razonable para BS (> 5)
      const tRate = parseFloat(t.exchange_rate) || 0;
      if (tRate > 5) {
        rate = tRate;
      } else {
        const propRate = parseFloat(props.rates?.bcv?.rate) || 0;
        rate = propRate > 0 ? propRate : 60;
      }
    }

    if (rate <= 0) rate = 1.0;

    const amountUsd = t.currency === "USD" ? t.amount : t.amount / rate;

    // Las transferencias entre cajas, cambistas y créditos no suman a las entradas/salidas netas directas de flujo en USD
    const isTransfer = isTransferTransaction(t.description);
    const isCambista = String(t.type || "").toUpperCase() === "CAMBISTA";
    const isCredit   = String(t.type || "").toUpperCase() === "CREDIT";

    if (!isTransfer && !isCambista && !isCredit) {
      if (t.isEntry) {
        map[day].totalInUsd += amountUsd;
      } else {
        map[day].totalOutUsd += amountUsd;
      }
    }
  }

  return Object.values(map).sort((a, b) => b.date.localeCompare(a.date));
});
</script>

<template>
  <div class="transactions-table-container">

    <div v-if="props.loading" class="pa-8 text-center rounded-lg border shadow-sm bg-white my-4">
      <VProgressCircular indeterminate color="primary" size="38" class="mb-3" />
      <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando movimientos...</div>
    </div>

    <div v-else-if="groupedByDay.length > 0">
      <div v-for="group in groupedByDay" :key="group.date" class="mb-6">
        <!-- Separador de Fecha con Identidad de Marca sutil -->
        <VCard class="rounded-lg border mb-3 overflow-hidden bg-surface date-group-card">
          <div
            class="d-flex flex-wrap align-center justify-space-between px-4 py-3 bg-surface-variant-subtle gap-3 border-b"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar color="primary" variant="tonal" size="28" class="rounded">
                <VIcon icon="tabler-calendar" size="16" />
              </VAvatar>
              <div class="d-flex align-baseline gap-2">
                <span class="text-subtitle-1 font-weight-bold text-high-emphasis leading-none">{{
                  group.date
                }}</span>
                <span class="text-caption text-medium-emphasis"
                  >({{ group.items.length }} mov.)</span
                >
              </div>
            </div>

            <div class="d-flex flex-wrap align-center gap-x-6 gap-y-1 text-caption">
              <div class="d-flex align-center gap-1">
                <span class="text-medium-emphasis">Entradas:</span>
                <span class="font-weight-bold text-success"
                  >+ {{ formatCurrency(group.totalInUsd, "USD") }}</span
                >
              </div>
              <div class="d-flex align-center gap-1">
                <span class="text-medium-emphasis">Salidas:</span>
                <span class="font-weight-bold text-error"
                  >- {{ formatCurrency(group.totalOutUsd, "USD") }}</span
                >
              </div>
              <div class="d-flex align-center gap-1 px-2 py-1 rounded bg-surface border">
                <span class="text-medium-emphasis font-weight-medium">Neto:</span>
                <span class="font-weight-bold" :class="group.totalInUsd - group.totalOutUsd < 0 ? 'text-error' : 'text-high-emphasis'">
                  {{
                    formatCurrency(group.totalInUsd - group.totalOutUsd, "USD")
                  }}
                </span>
              </div>
            </div>
          </div>

          <!-- Vista Escritorio: Tabla -->
          <VTable
            v-if="!$vuetify.display.smAndDown"
            density="comfortable"
            class="financial-table text-no-wrap"
          >
            <thead>
              <tr class="table-header-row">
                <th
                  v-for="h in headers"
                  :key="h.key"
                  :class="`text-${h.align || 'start'}`"
                  :style="h.width ? { width: h.width, maxWidth: h.width } : {}"
                  class="table-header-cell px-4 py-3"
                >
                  {{ h.title }}
                  <!-- Ícono candado en Balance Caja para indicar contexto de moneda -->
                  <VIcon 
                    v-if="h.key === 'balance' && !isFiltered" 
                    icon="tabler-lock" 
                    size="12" 
                    class="ms-1 text-disabled" 
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in group.items"
                :key="item.id"
                class="financial-row"
              >
                <td class="text-center" style="width: 90px;">
                  <VIcon
                    :icon="
                      item.isEntry
                        ? 'tabler-arrow-up'
                        : 'tabler-arrow-down'
                    "
                    :color="item.isEntry ? 'success' : 'error'"
                    size="18"
                  />
                </td>
                <td class="px-4 text-truncate" style="width: 140px; max-width: 140px;">
                   <span class="text-body-2 text-high-emphasis">{{
                      item.user_name
                    }}</span>
                </td>
                <td
                  class="text-body-2 text-high-emphasis px-4 text-wrap"
                >
                  <div class="d-flex align-center gap-2">
                    <span>{{ cleanDescription(item.description) }}</span>
                    <!-- Botón Ojito para ver el detalle de las facturas si es un pago a proveedor -->
                    <VBtn
                      v-if="isInvoicePayment(item.description)"
                      icon="tabler-eye"
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      class="rounded-lg ms-1 flex-shrink-0"
                      :loading="loadingPaymentDetail"
                      @click.stop="openPaymentDetail(item)"
                    >
                      <VIcon icon="tabler-eye" size="14" />
                      <VTooltip activator="parent" location="top">Ver detalle de facturas</VTooltip>
                    </VBtn>
                  </div>
                </td>
                <td class="text-center px-2" style="width: 110px;">
                  <VChip
                    size="small"
                    variant="tonal"
                    color="primary"
                    label
                    class="font-weight-medium text-caption"
                  >
                    {{ item.type }}
                  </VChip>
                </td>
                <td class="text-right px-4" style="width: 160px;">
                  <div class="d-flex align-center justify-end gap-2">
                    <!-- Badge cuando agrupa varias transacciones -->
                    <VTooltip v-if="item._count > 1" location="top">
                      <template #activator="{ props: tp }">
                        <VChip v-bind="tp" size="x-small" color="secondary" variant="tonal"
                          class="font-weight-medium cursor-help">
                          <VIcon icon="tabler-stack" size="11" class="me-1" />
                          {{ item._count }}
                        </VChip>
                      </template>
                      <span>Total de {{ item._count }} transacciones agrupadas</span>
                    </VTooltip>
                    <!-- Monto formateado con color explícito: Verde dinero (entrada) / Rojo (salida) -->
                    <div
                      :class="[
                        'text-body-2 font-weight-bold',
                        item.isEntry ? 'text-money-in' : 'text-money-out',
                      ]"
                    >
                      {{ item.isEntry ? "+ " : "- " }}{{ formatCurrency(item.amount, item.currency) }}
                    </div>
                  </div>
                </td>
                <!-- Columna BALANCE CAJA: solo cuando hay filtro activo (moneda seleccionada) -->
                <td v-if="isFiltered" class="text-right px-4" style="width: 150px;">
                  <span class="text-body-2 font-weight-bold text-high-emphasis">
                    {{ formatCurrency(item.balance, item.currency) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Vista Móvil: Cards dentro del grupo -->
          <div v-else class="pa-3 d-flex flex-column gap-3">
            <VCard
              v-for="item in group.items"
              :key="item._groupKey ?? item.id"
              variant="flat"
              class="border rounded-lg px-4 py-3 bg-white shadow-xs"
              :class="item.isEntry ? 'border-success-subtle' : 'border-error-subtle'"
            >
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    :color="item.isEntry ? 'success' : 'error'"
                    variant="tonal"
                    size="40"
                    class="rounded-lg"
                  >
                    <VIcon
                      :icon="
                        item.isEntry
                          ? 'tabler-arrow-up-right'
                          : 'tabler-arrow-down-left'
                      "
                      size="22"
                    />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-sm font-weight-black text-high-emphasis">{{ item.user_name }}</span>
                    <span class="text-super-xs text-disabled font-weight-black uppercase">{{ item.type }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <!-- Badge de agrupación en móvil -->
                  <VChip v-if="item._count > 1" size="x-small" color="warning" variant="tonal"
                    class="font-weight-black mb-1">
                    <VIcon icon="tabler-stack" size="10" class="me-1" />
                    {{ item._count }} agrupados
                  </VChip>
                  <div
                    :class="[
                      'text-lg font-weight-black',
                      item.isEntry ? 'text-money-in' : 'text-money-out',
                    ]"
                  >
                    {{ item.isEntry ? "+" : "-" }}
                    {{ formatCurrency(item.amount, item.currency) }}
                  </div>
                </div>
              </div>

              <!-- Descripción -->
              <div
                class="text-sm text-medium-emphasis mb-4 bg-surface-variant-light pa-2 rounded-lg italic border d-flex align-center justify-space-between gap-2"
              >
                <span>"{{ cleanDescription(item.description) }}"</span>
                <VBtn
                  v-if="isInvoicePayment(item.description)"
                  icon="tabler-eye"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="rounded-lg flex-shrink-0"
                  :loading="loadingPaymentDetail"
                  @click.stop="openPaymentDetail(item)"
                >
                  <VIcon icon="tabler-eye" size="14" />
                </VBtn>
              </div>

              <!-- Footer de la Card con Balance Destacado -->
              <div
                class="d-flex justify-space-between align-center pt-3 border-t"
              >
                <div class="d-flex align-center gap-2">
                  <span
                    class="text-super-xs font-weight-black text-disabled uppercase"
                    >{{ item.user_name }}</span
                  >
                </div>
                <div class="d-flex flex-column align-end">
                  <span
                    class="text-super-xs font-weight-black text-primary uppercase"
                    >Balance tras mov.</span
                  >
                  <span class="text-sm font-weight-black text-high-emphasis">
                    {{ formatCurrency(item.balance, item.currency) }}
                  </span>
                </div>
              </div>
            </VCard>
          </div>
        </VCard>
      </div>

      <!-- Paginación Premium -->
      <VCard
        class="rounded-lg border shadow-sm pa-3 d-flex justify-center mt-6"
      >
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalTransactions / props.itemsPerPage)"
          density="comfortable"
          total-visible="5"
          active-color="primary"
          @update:model-value="
            (p) =>
              emit('update:options', {
                page: p,
                itemsPerPage: props.itemsPerPage,
              })
          "
        />
      </VCard>
    </div>

    <!-- Empty state -->
    <div v-else class="pa-12 text-center rounded-xl border-2 border-dashed bg-surface">
      <VAvatar size="80" color="primary" variant="tonal" class="mb-4">
        <VIcon icon="tabler-database-x" size="40" color="primary" />
      </VAvatar>
      <h3 class="text-h6 font-weight-black text-high-emphasis mb-1">
        Sin movimientos registrados
      </h3>
      <p class="text-body-2 text-medium-emphasis mb-6 max-w-md mx-auto">
        No se encontraron transacciones para los filtros o rango de fechas seleccionado.
      </p>
      <VBtn color="primary" variant="tonal" class="font-weight-black px-6" @click="emit('clear')">
        <VIcon icon="tabler-filter-off" class="me-2" size="18" />
        REINICIAR FILTROS
      </VBtn>
    </div>

    <!-- Modal Detalle de Pago -->
    <PaymentDetailModal
      v-model="showPaymentModal"
      :payment="selectedPayment"
    />
  </div>
</template>

<style scoped>
.bg-surface-variant-subtle {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.financial-table {
  background: rgb(var(--v-theme-surface)) !important;
}

.financial-table :deep(th) {
  background: rgb(var(--v-theme-surface)) !important;
  color: #6B7280 !important;
  block-size: 44px !important;
  font-size: 0.75rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.02rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08) !important;
}

.financial-table :deep(td) {
  padding-block: 14px !important;
  padding-inline: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.financial-row:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.date-group-card {
  border-inline-start: 4px solid rgb(var(--v-theme-primary)) !important;
}

.border-success-subtle {
  border-inline-start: 4px solid rgb(var(--v-theme-success)) !important;
}

.border-error-subtle {
  border-inline-start: 4px solid rgb(var(--v-theme-error)) !important;
}

/* Colores explícitos de dinero para transacciones */
.text-money-in {
  color: #10B981 !important; /* Verde dinero vivo */
}

.text-money-out {
  color: #EF4444 !important; /* Rojo salida vivo */
}
</style>
