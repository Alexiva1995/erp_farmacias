<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed } from "vue";

const props = defineProps({
  transactions: { type: [Array, Object], default: () => [] },
  loading: { type: Boolean, default: false },
  dataDetailed: { type: Boolean, default: false },
  selectedCurrency: { type: String, default: "" },
  selectedTab: { type: String, default: "" },
  totalTransactions: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "update:selectedTab", "clear"]);

// Hay una caja/moneda seleccionada → el balance corrido tiene sentido
const isFiltered = computed(() => !!props.selectedCurrency);

// Cabeceras activas: ocultar BALANCE CAJA cuando no hay filtro de moneda
const allHeaders = [
  { title: "Día / Mov.", key: "direction",     sortable: false, align: "center" },
  { title: "ID",         key: "id",             sortable: false, align: "start"  },
  { title: "USUARIO",    key: "user_name",      sortable: false                  },
  { title: "DESCRIPCIÓN",key: "description",   sortable: false                  },
  { title: "TIPO",       key: "type",           sortable: false, align: "center" },
  { title: "MONTO",      key: "amount",         sortable: false, align: "end"    },
  { title: "BALANCE CAJA",key: "balance",       sortable: false, align: "end", filteredOnly: true },
  { title: "CATEGORÍA",  key: "category_name", sortable: false                  },
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

    const rate      = parseFloat(t.exchange_rate) || 1;
    const amountUsd = t.currency === "USD" ? t.amount : t.amount / rate;

    if (t.isEntry) {
      map[day].totalInUsd += amountUsd;
    } else {
      map[day].totalOutUsd += amountUsd;
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
      <div v-for="group in groupedByDay" :key="group.date" class="mb-7">
        <!-- Encabezado del día Premium -->
        <VCard class="rounded-lg border shadow-sm mb-4 overflow-hidden">
          <div
            class="d-flex flex-wrap align-center justify-space-between px-5 py-4 bg-white gap-4"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                color="primary"
                variant="elevated"
                size="40"
                class="rounded-lg shadow-sm"
              >
                <VIcon icon="tabler-calendar-event" color="white" size="22" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-h6 font-weight-black leading-none">{{
                  group.date
                }}</span>
                <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1"
                  >{{ group.items.length }} Movimiento{{ group.items.length !== 1 ? 's' : '' }} registrado{{ group.items.length !== 1 ? 's' : '' }}</span
                >
              </div>
            </div>

            <div class="d-flex flex-wrap gap-x-8 gap-y-2">
              <div class="d-flex flex-column align-end">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase"
                  >Entradas (+)</span
                >
                <span class="text-base font-weight-black text-success"
                  >↑ {{ formatCurrency(group.totalInUsd, "USD") }}</span
                >
              </div>
              <div class="d-flex flex-column align-end">
                <span
                  class="text-super-xs font-weight-black text-disabled uppercase"
                  >Salidas (-)</span
                >
                <span class="text-base font-weight-black text-error"
                  >↓ {{ formatCurrency(group.totalOutUsd, "USD") }}</span
                >
              </div>
              <div
                class="d-flex flex-column align-end bg-white px-5 py-2 rounded-lg shadow-inner border border-dashed border-primary"
              >
                <span
                  class="text-super-xs font-weight-black text-primary uppercase"
                  >Neto del Día</span
                >
                <span class="text-h6 font-weight-black text-primary">
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
            class="premium-table text-no-wrap"
          >
            <thead>
              <tr class="bg-surface">
                <th
                  v-for="h in headers"
                  :key="h.key"
                  :class="`text-${h.align || 'start'}`"
                  class="text-uppercase text-super-xs font-weight-black text-disabled border-b px-4 py-3"
                >
                  {{ h.title }}
                  <!-- Ícono candado en BALANCE CAJA para dejar claro que requiere filtro -->
                  <VIcon 
                    v-if="h.key === 'balance' && !isFiltered" 
                    icon="tabler-lock" 
                    size="10" 
                    class="ms-1" 
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in group.items"
                :key="item.id"
                :class="item.isEntry ? 'row-in' : 'row-out'"
              >
                <td class="text-center">
                  <VIcon
                    :icon="
                      item.isEntry
                        ? 'tabler-circle-arrow-up'
                        : 'tabler-circle-arrow-down'
                    "
                    :color="item.isEntry ? 'success' : 'error'"
                    size="20"
                    class="opacity-80"
                  />
                </td>
                <td class="text-caption font-weight-black text-primary px-4">
                  <!-- Mostrar todos los IDs si la fila agrupa varias transacciones -->
                  <span v-if="item._count === 1">#{{ item.id }}</span>
                  <VTooltip v-else location="bottom">
                    <template #activator="{ props: tp }">
                      <span v-bind="tp" class="cursor-help">
                        #{{ item._ids[0] }}
                        <VChip size="x-small" color="primary" variant="tonal" class="ms-1 font-weight-black">
                          +{{ item._count - 1 }}
                        </VChip>
                      </span>
                    </template>
                    <span>IDs agrupados: {{ item._ids.join(', ') }}</span>
                  </VTooltip>
                </td>
                <td>
                   <span class="text-xs font-weight-bold">{{
                      item.user_name
                    }}</span>
                </td>
                <td
                  class="text-truncate text-xs px-4"
                  style="max-inline-size: 250px"
                >
                  {{ item.description }}
                </td>
                <td class="text-center">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    :color="item.isEntry ? 'success' : 'error'"
                    class="font-weight-black rounded-lg"
                  >
                    {{ item.type }}
                  </VChip>
                </td>
                <td class="text-right px-4">
                  <div class="d-flex align-center justify-end gap-2">
                    <!-- Badge cuando agrupa varias transacciones -->
                    <VTooltip v-if="item._count > 1" location="top">
                      <template #activator="{ props: tp }">
                        <VChip v-bind="tp" size="x-small" color="warning" variant="tonal"
                          class="font-weight-black cursor-help">
                          <VIcon icon="tabler-stack" size="11" class="me-1" />
                          {{ item._count }} agrupados
                        </VChip>
                      </template>
                      <span>Total de {{ item._count }} transacciones con la misma descripción, tipo y moneda</span>
                    </VTooltip>
                    <!-- Badge de moneda: solo visible cuando la tabla muestra todas las monedas -->
                    <VChip v-if="!isFiltered" size="x-small"
                      :color="CURRENCY_COLOR[item.currency] ?? 'secondary'"
                      variant="tonal" class="font-weight-black">
                      {{ item.currency }}
                    </VChip>
                    <div
                      :class="[
                        'text-base font-weight-black',
                        item.isEntry ? 'text-success' : 'text-error',
                      ]"
                    >
                      {{ item.isEntry ? "+" : "-" }}
                      {{ formatCurrency(item.amount, item.currency) }}
                    </div>
                  </div>
                </td>
                <!-- Columna BALANCE CAJA: solo cuando hay filtro activo (moneda seleccionada) -->
                <td v-if="isFiltered" class="text-right px-4 bg-surface-variant-light">
                  <div class="d-flex flex-column align-end">
                    <span class="text-base font-weight-black text-high-emphasis">
                      {{ formatCurrency(item.balance, item.currency) }}
                    </span>
                    <span class="text-super-xs text-disabled font-weight-bold uppercase">Balance Final</span>
                  </div>
                </td>
                <td class="text-caption font-weight-bold text-disabled px-4">
                  {{ item.category_name }}
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
                    <!-- ID: si agrupa varios, muestra el primero + badge -->
                    <span class="text-xs font-weight-black text-primary">
                      ID: #{{ item._ids[0] }}
                      <VChip v-if="item._count > 1" size="x-small" color="primary" variant="tonal" class="ms-1 font-weight-black">
                        +{{ item._count - 1 }}
                      </VChip>
                    </span>
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
                      item.isEntry ? 'text-success' : 'text-error',
                    ]"
                  >
                    {{ item.isEntry ? "+" : "-" }}
                    {{ formatCurrency(item.amount, item.currency) }}
                  </div>
                  <VChip size="x-small" variant="tonal" color="secondary" class="font-weight-bold mt-1">
                    {{ item.category_name }}
                  </VChip>
                </div>
              </div>

              <!-- Descripción -->
              <div
                class="text-sm text-medium-emphasis mb-4 bg-surface-variant-light pa-2 rounded-lg italic border"
              >
                "{{ item.description }}"
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
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 4%);
}

.premium-table {
  background: white !important;
}

.premium-table :deep(th) {
  background: white !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-high-emphasis-opacity)
  ) !important;
  block-size: 48px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 2px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.row-in:hover {
  background-color: rgba(var(--v-theme-success), 0.02);
}

.row-out:hover {
  background-color: rgba(var(--v-theme-error), 0.02);
}

.border-dashed {
  border-style: dashed !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.04) !important;
}

.border-success-subtle {
  border-inline-start: 4px solid rgb(var(--v-theme-success)) !important;
}

.border-error-subtle {
  border-inline-start: 4px solid rgb(var(--v-theme-error)) !important;
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
/* ── Banner guía ────────────────────────────────────── */
.guide-banner {
  border: 1px dashed rgba(var(--v-theme-primary), 0.3);
  background: rgba(var(--v-theme-primary), 0.04);
  border-radius: 8px;
}

/* Animación de entrada/salida del banner */
.fade-guide-enter-active,
.fade-guide-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-guide-enter-from,
.fade-guide-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
