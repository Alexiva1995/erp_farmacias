<script setup>
import { computed, ref, reactive } from "vue";
import { useDisplay } from "vuetify";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  pendingPayments: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  selectedTableInvoices: { type: Array, default: () => [] },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  updatingIndexed: { type: Object, default: () => ({}) },
  updatingDates: { type: Object, default: () => ({}) },
  exchangeRate: { type: Number, default: 1 },
});

const getDisplayAmount = (item) => {
  if (item.is_indexed && item.indexed_data?.is_indexed) {
    return item.indexed_data.indexed_amount;
  }
  return item.total_amount;
};

const selectedTotals = computed(() => {
  const usd = props.selectedTableInvoices.reduce((acc, item) => acc + (parseFloat(item.original_amount_usd) || 0), 0);
  const bs = props.selectedTableInvoices.reduce((acc, item) => {
    let amount = parseFloat(getDisplayAmount(item)) || 0;
    // Si tiene Nota de Débito referencial aprobada (descuento), se resta al total a pagar
    if (item.nd_referential_amount && parseFloat(item.nd_referential_amount) > 0) {
      amount = Math.max(0, amount - parseFloat(item.nd_referential_amount));
    }
    return acc + amount;
  }, 0);

  return {
    count: props.selectedTableInvoices.length,
    usd: usd.toFixed(2),
    bs: bs.toFixed(2),
  };
});

const emit = defineEmits([
  "update:options",
  "update:selected",
  "toggle-indexed",
  "process-payment",
  "toggle-selection",
  "select-all",
  "deselect-all",
  "update-date",
  "mark-as-paid",
]);

const { mobile } = useDisplay();
const authStore = useAuthStore();

const tempDates = reactive({});
const menuStates = reactive({});

const openMenu = (item) => {
  tempDates[item.id] = item.payment_date;
  menuStates[item.id] = true;
};

const saveDate = (item) => {
  const selectedVal = tempDates[item.id];
  if (selectedVal) {
    emit('update-date', item, selectedVal);
  }
  menuStates[item.id] = false;
};

const headers = [
  { title: "", key: "select", sortable: false, width: "40px" },
  { title: "FAC", key: "invoice_number", sortable: false },
  { title: "Proveedor", key: "supplier_name", sortable: false, width: "25%" },
  { title: "Vencimiento", key: "payment_date", sortable: false },
  { title: "Monto USD", key: "original_amount", sortable: false, align: "end" },
  { title: "Monto BS", key: "remaining_amount", sortable: false, align: "end" },
  { title: "Indexada", key: "is_indexed", sortable: false, width: "80px", align: "center" },
  { title: "Estado", key: "status", sortable: false, align: "center" },
  { title: "", key: "actions", sortable: false, align: "center" },
];

const selectedAll = computed(() => {
  if (props.pendingPayments.length === 0) return false;
  return props.selectedTableInvoices.length === props.pendingPayments.length;
});

const indeterminate = computed(() => {
  return (
    props.selectedTableInvoices.length > 0 &&
    props.selectedTableInvoices.length < props.pendingPayments.length
  );
});

const formatCurrency = (amount, currency, omitCurrency = false) => {
  if (!amount && amount !== 0) return "0.00";
  const formatted = new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
  
  if (omitCurrency) return formatted;
  return `${currency} ${formatted}`;
};

const formatDate = (date) => {
  if (!date) return "N/A";
  const dateStr = typeof date === 'string' ? date.substring(0, 10).replace(/-/g, '/') : date;
  return new Date(dateStr).toLocaleDateString("es-VE");
};

const formatDueDate = (paymentDate) => {
  if (!paymentDate) return "N/A";
  const dateStr = typeof paymentDate === 'string' ? paymentDate.substring(0, 10).replace(/-/g, '/') : paymentDate;
  const dueDate = new Date(dateStr);
  return dueDate.toLocaleDateString("es-VE");
};

const isOverdue = (paymentDate) => {
  if (!paymentDate) return false;
  const dateStr = typeof paymentDate === 'string' ? paymentDate.substring(0, 10).replace(/-/g, '/') : paymentDate;
  const dueDate = new Date(dateStr);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  dueDate.setHours(0, 0, 0, 0);
  return dueDate < today;
};

const getStatusColor = (status) => {
  switch (status) {
    case "loaded": return "info";
    case "to_order": return "warning";
    default: return "success";
  }
};

const getStatusText = (status) => {
  switch (status) {
    case "loaded": return "Cargada";
    case "to_order": return "Por Ordenar";
    default: return "Pendiente";
  }
};

const getRemainingAmountClass = (item) => {
  const remaining = item.remaining_amount || item.total_amount;
  const original = item.original_amount || item.total_amount;
  return remaining < original ? "text-warning" : "text-success";
};

const isTableInvoiceSelected = (invoice) => {
  return props.selectedTableInvoices.some((inv) => inv.id === invoice.id);
};

const handleHeaderCheckboxChange = (value) => {
  if (value) emit("select-all");
  else emit("deselect-all");
};

const getAvatarColor = (name) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  const hash = name.split("").reduce((a, b) => a + b.charCodeAt(0), 0);
  return colors[hash % colors.length];
};

const getInitials = (name) => {
  if (!name) return "P";
  return name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
};

const openInvoiceTab = (item) => {
  const invoiceId = item.id || item.invoice_id;
  if (invoiceId) {
    window.open(`/invoice/invoices?id=${invoiceId}`, "_blank");
  } else {
    window.open(`/invoice/invoices?search=${encodeURIComponent(item.invoice_number)}`, "_blank");
  }
};
</script>

<template>
  <div class="mt-4">
    <!-- Vista Escritorio -->
    <VCard v-if="!mobile" class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VDataTable
        :headers="headers"
        :items="props.pendingPayments"
        :loading="props.loading"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        class="text-no-wrap premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #header.select>
          <VCheckbox
            :model-value="selectedAll"
            :indeterminate="indeterminate"
            density="compact"
            color="primary"
            class="ms-n2"
            @update:model-value="handleHeaderCheckboxChange"
          />
        </template>

        <template #item.select="{ item }">
          <VCheckbox
            :model-value="isTableInvoiceSelected(item)"
            density="compact"
            color="primary"
            class="ms-n2"
            @change="emit('toggle-selection', item)"
          />
        </template>

        <template #item.invoice_number="{ item }">
          <div class="d-flex flex-column">
            <a
              href="javascript:void(0)"
              class="text-xs font-weight-black text-primary text-decoration-none d-inline-flex align-center gap-1 cursor-pointer"
              @click.stop="openInvoiceTab(item)"
            >
              <span>{{ item.invoice_number }}</span>
              <VIcon icon="tabler-external-link" size="13" class="opacity-75" />
              <VTooltip activator="parent" location="top">Abrir factura en nueva pestaña</VTooltip>
            </a>
            <span class="text-super-xs text-medium-emphasis">Ctrl: {{ (item.control_number && item.control_number !== 'N/A') ? item.control_number : 'N/A' }}</span>
          </div>
        </template>

        <template #item.supplier_name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar :color="getAvatarColor(item.supplier_name)" variant="tonal" size="30" class="rounded-lg">
              <span class="text-super-xs font-weight-black">{{ getInitials(item.supplier_name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-bold text-high-emphasis truncate text-capitalize max-w-180">
                {{ item.supplier_name }}
              </span>
              <span class="text-super-xs text-medium-emphasis">RIF: {{ (item.supplier_rif && item.supplier_rif !== 'N/A') ? item.supplier_rif : 'N/A' }}</span>
            </div>
          </div>
        </template>

        <template #item.payment_date="{ item }">
          <div class="d-flex align-center gap-2">
            <VIcon 
              :icon="isOverdue(item.payment_date) ? 'tabler-calendar-cancel' : 'tabler-calendar-time'" 
              size="18" 
              :color="isOverdue(item.payment_date) ? 'error' : 'disabled'" 
            />
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-black" :class="isOverdue(item.payment_date) ? 'text-error' : 'text-high-emphasis'">
                {{ formatDueDate(item.payment_date) }}
              </span>
              <span class="text-super-xs text-disabled">Pago: {{ formatDate(item.payment_date) }}</span>
            </div>
          </div>
        </template>

        <template #item.original_amount="{ item }">
          <span class="text-xs font-weight-bold">{{ formatCurrency(item.original_amount_usd, "USD", true) }}</span>
        </template>

        <template #item.remaining_amount="{ item }">
          <div class="d-flex flex-column align-end">
            <span class="text-xs font-weight-black" :class="getRemainingAmountClass(item)">
              {{ formatCurrency(getDisplayAmount(item), item.currency, true) }}
            </span>
            <div v-if="item.nd_referential_amount > 0 || item.claim_amount > 0" class="d-flex align-center gap-1 mt-0">
              <span
                v-if="item.nd_referential_amount > 0"
                class="text-super-xs font-weight-bold text-error d-inline-flex align-center cursor-pointer"
              >
                ND Ref: -{{ formatCurrency(item.nd_referential_amount, item.currency, true) }}
                <VTooltip activator="parent" location="top">Nota de Débito Referencial Aprobada (-{{ formatCurrency(item.nd_referential_amount, item.currency) }})</VTooltip>
              </span>
              <span
                v-if="item.claim_amount > 0"
                class="text-super-xs font-weight-bold text-warning d-inline-flex align-center cursor-pointer"
              >
                Reclamo: {{ formatCurrency(item.claim_amount, item.currency, true) }}
                <VTooltip activator="parent" location="top">Reclamo en Proceso ({{ formatCurrency(item.claim_amount, item.currency) }})</VTooltip>
              </span>
            </div>
          </div>
        </template>

        <template #item.is_indexed="{ item }">
          <VSwitch
            v-model="item.is_indexed"
            color="primary"
            density="compact"
            hide-details
            :disabled="!!props.updatingIndexed[item.id]"
            @change="emit('toggle-indexed', item)"
          />
        </template>

        <!-- Columna Total Prov Eliminada por solicitud de usuario -->

        <template #item.status="{ item }">
          <VChip :color="getStatusColor(item.status)" variant="tonal" size="x-small" class="font-weight-black rounded">
            {{ getStatusText(item.status) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <!-- Editar Fecha -->
            <VMenu v-if="authStore.isAdmin" v-model="menuStates[item.id]" :close-on-content-click="false" location="start">
              <template #activator="{ props: menuProps }">
                <VBtn
                  v-bind="menuProps"
                  icon
                  variant="tonal"
                  size="32"
                  color="info"
                  class="rounded-circle shadow-sm"
                  :loading="!!props.updatingDates[item.id]"
                  :disabled="!!props.updatingDates[item.id]"
                  @click="openMenu(item)"
                >
                  <VIcon icon="tabler-calendar" size="18" />
                  <VTooltip activator="parent" location="top">Cambiar Fecha Vencimiento</VTooltip>
                </VBtn>
              </template>
              <VCard min-width="280" class="pa-4 rounded-lg shadow-lg">
                <div class="text-xs font-weight-black uppercase mb-2 text-disabled">Nueva Fecha Pago</div>
                <div class="d-flex align-center gap-2">
                  <VTextField
                    type="date"
                    v-model="tempDates[item.id]"
                    placeholder="Seleccionar Fecha"
                    variant="outlined"
                    density="compact"
                    prepend-inner-icon="tabler-calendar"
                    hide-details
                  />
                  <VBtn
                    icon
                    size="36"
                    color="primary"
                    class="rounded-lg"
                    @click="saveDate(item)"
                  >
                    <VIcon icon="tabler-check" size="18" />
                  </VBtn>
                </div>
              </VCard>
            </VMenu>

            <!-- Ver / Descargar PDF de la Factura -->
            <VBtn
              v-if="item.pdf_url || item.invoice_photo"
              icon
              variant="tonal"
              size="32"
              color="error"
              class="rounded-circle shadow-sm"
              :href="item.pdf_url || `/storage/${item.invoice_photo}`"
              target="_blank"
            >
              <VIcon icon="tabler-file-type-pdf" size="18" />
              <VTooltip activator="parent" location="top">Ver / Descargar PDF Digital</VTooltip>
            </VBtn>

            <!-- Marcar como Pagado Directamente -->
            <VBtn
              v-if="authStore.isAdmin"
              icon
              variant="tonal"
              size="32"
              color="primary"
              class="rounded-circle shadow-sm"
              @click="emit('mark-as-paid', item)"
            >
              <VIcon icon="tabler-square-check" size="18" />
              <VTooltip activator="parent" location="top">Marcar Pagada (Sin Gasto)</VTooltip>
            </VBtn>

            <!-- Procesar Pago (Original) -->
            <VBtn
              icon
              variant="tonal"
              size="32"
              color="success"
              class="rounded-circle shadow-sm"
              @click="emit('process-payment', item)"
            >
              <VIcon icon="tabler-credit-card" size="18" />
              <VTooltip activator="parent" location="top">Procesar Pago</VTooltip>
            </VBtn>
          </div>
        </template>

        <template #loading>
          <div class="pa-8 text-center bg-white">
            <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
            <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando cuentas por pagar...</div>
          </div>
        </template>

        <template #no-data>
           <div class="text-center py-10 opacity-50">
             <VIcon icon="tabler-receipt-off" size="48" class="mb-2" />
             <div class="text-xs font-weight-black uppercase">No hay pagos pendientes</div>
           </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Vista Móvil Cards -->
    <div v-else class="d-flex flex-column gap-4 pb-16">
      <template v-if="props.loading">
        <VCard class="rounded-lg border shadow-sm pa-8 text-center bg-white">
          <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
          <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Cargando cuentas por pagar...</div>
        </VCard>
      </template>
      <template v-else-if="props.pendingPayments.length > 0">
        <VCard
          v-for="item in props.pendingPayments"
          :key="item.id"
          class="rounded-lg border shadow-sm premium-card overflow-hidden"
          :class="{ 'card-selected': isTableInvoiceSelected(item) }"
          @click="emit('toggle-selection', item)"
        >
          <div class="premium-card-decoration" :class="isOverdue(item.payment_date) ? 'bg-error-opacity' : 'bg-primary-opacity'"></div>
          
          <VCardText class="pa-5">
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <VCheckboxBtn
                  :model-value="isTableInvoiceSelected(item)"
                  density="compact"
                  color="primary"
                  class="ms-n2"
                />
                <VAvatar :color="getAvatarColor(item.supplier_name)" variant="tonal" size="38" class="rounded-lg">
                  <VIcon icon="tabler-receipt" size="18" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-xs font-weight-black text-disabled uppercase leading-tight">Factura #</span>
                  <a
                    href="javascript:void(0)"
                    class="text-sm font-weight-black text-primary leading-tight text-decoration-none d-inline-flex align-center gap-1 cursor-pointer"
                    @click.stop="openInvoiceTab(item)"
                  >
                    <span>{{ item.invoice_number }}</span>
                    <VIcon icon="tabler-external-link" size="14" class="opacity-75" />
                  </a>
                  <span class="text-super-xs text-disabled">Ctrl: {{ item.control_number || 'N/A' }}</span>
                </div>
              </div>
              <div class="d-flex flex-column align-end">
                <div class="d-flex align-center gap-1">
                   <VIcon 
                    :icon="isOverdue(item.payment_date) ? 'tabler-calendar-cancel' : 'tabler-calendar-time'" 
                    size="14" 
                    :color="isOverdue(item.payment_date) ? 'error' : 'disabled'" 
                  />
                  <span class="text-xs font-weight-black text-disabled uppercase leading-tight">Vence</span>
                </div>
                <span class="text-xs font-weight-black leading-tight" :class="isOverdue(item.payment_date) ? 'text-error' : 'text-high-emphasis'">
                  {{ formatDueDate(item.payment_date) }}
                </span>
              </div>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <!-- Info Proveedor -->
            <div class="mb-4 d-flex justify-space-between align-center">
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Proveedor</span>
                <span class="text-sm font-weight-bold text-high-emphasis d-block leading-tight text-capitalize">{{ item.supplier_name }}</span>
              </div>
              <VChip size="x-small" variant="tonal" color="secondary" class="font-weight-black">
                RIF: {{ item.supplier_rif || 'N/A' }}
              </VChip>
            </div>

            <!-- Stats Pago -->
            <div class="d-flex gap-3 mb-4">
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block mb-1">Original (USD)</span>
                <span class="text-sm font-weight-black">{{ formatCurrency(item.original_amount_usd, "USD", true) }}</span>
              </div>
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg" :class="getRemainingAmountClass(item) === 'text-success' ? 'bg-success-opacity-2' : 'bg-warning-opacity'">
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-super-xs font-weight-bold uppercase" :class="getRemainingAmountClass(item)">Pendiente ({{ item.currency }})</span>
                  <VIcon v-if="item.is_indexed" icon="tabler-link" size="14" color="primary" />
                </div>
                <span class="text-sm font-weight-black" :class="getRemainingAmountClass(item)">
                  {{ formatCurrency(getDisplayAmount(item), "", true) }}
                </span>
              </div>
            </div>

            <!-- Botones de Acción Móvil -->
            <div class="d-flex align-center gap-2 mt-2">
              <div class="d-flex align-center gap-2 flex-grow-1">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Indexada</span>
                <VSwitch
                  v-model="item.is_indexed"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="!!props.updatingIndexed[item.id]"
                  @change="emit('toggle-indexed', item)"
                />
              </div>
              <div class="d-flex gap-1">
                <!-- Editar Fecha (Móvil) -->
                <VMenu v-if="authStore.isAdmin" v-model="menuStates[item.id]" :close-on-content-click="false" location="top">
                  <template #activator="{ props: menuProps }">
                    <VBtn
                      v-bind="menuProps"
                      icon
                      variant="tonal"
                      size="32"
                      color="info"
                      class="rounded-lg shadow-sm"
                      @click.stop="openMenu(item)"
                    >
                      <VIcon icon="tabler-calendar-edit" size="18" />
                    </VBtn>
                  </template>
                  <VCard min-width="280" class="pa-4 rounded-lg shadow-lg" @click.stop>
                    <div class="text-xs font-weight-black uppercase mb-2 text-disabled">Nueva Fecha Pago</div>
                    <div class="d-flex align-center gap-2">
                      <VTextField
                        type="date"
                        v-model="tempDates[item.id]"
                        placeholder="Seleccionar Fecha"
                        variant="outlined"
                        density="compact"
                        prepend-inner-icon="tabler-calendar"
                        hide-details
                      />
                      <VBtn
                        icon
                        size="36"
                        color="primary"
                        class="rounded-lg"
                        @click="saveDate(item)"
                      >
                        <VIcon icon="tabler-check" size="18" />
                      </VBtn>
                    </div>
                  </VCard>
                </VMenu>

                <!-- Marcar Pagada (Móvil) -->
                <VBtn
                  v-if="authStore.isAdmin"
                  icon
                  variant="tonal"
                  size="32"
                  color="primary"
                  class="rounded-lg shadow-sm"
                  @click.stop="emit('mark-as-paid', item)"
                >
                  <VIcon icon="tabler-square-check" size="18" />
                </VBtn>

                <!-- Pagar (Original) -->
                <VBtn
                  color="success"
                  variant="flat"
                  size="small"
                  class="rounded-lg text-super-xs font-weight-black shadow-sm px-4"
                  @click.stop="emit('process-payment', item)"
                >
                  PAGAR
                </VBtn>
              </div>
            </div>
          </VCardText>
        </VCard>
      </template>

      <VAlert v-else type="info" variant="tonal" class="rounded-lg">
        No hay pagos pendientes registrados.
      </VAlert>
    </div>

    <!-- Barra de Selección Flotante (Desktop y Móvil) -->
    <VSlideYReverseTransition>
      <div v-if="props.selectedTableInvoices.length > 0" class="floating-selection-bar pa-4">
        <VCard color="primary" class="rounded-xl shadow-lg pa-3 d-flex align-center justify-space-between border-0">
          <div class="d-flex align-center gap-4 ms-2">
            <VAvatar color="white" variant="tonal" size="36" class="rounded-lg">
              <span class="text-xs font-weight-black text-white">{{ selectedTotals.count }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-white-50 uppercase letter-spacing-1">Resumen Lote Seleccionado</span>
              <div class="d-flex align-center gap-3">
                <span class="text-sm font-weight-black text-white">${{ selectedTotals.usd }} USD</span>
                <span class="text-xs font-weight-bold text-white-50">({{ selectedTotals.bs }} Bs)</span>
              </div>
            </div>
          </div>
          <div class="d-flex align-center gap-2">
            <VBtn
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg font-weight-black"
              @click="emit('deselect-all')"
            >
              <VIcon icon="tabler-x" size="18" class="me-1" />
              Limpiar
            </VBtn>
            <VBtn
              variant="flat"
              color="success"
              size="small"
              class="rounded-lg font-weight-black shadow-sm px-4"
              @click="emit('process-multiple')"
            >
              <VIcon icon="tabler-credit-card" size="18" class="me-1" />
              PROCESAR PAGO ({{ selectedTotals.count }})
            </VBtn>
          </div>
        </VCard>
      </div>
    </VSlideYReverseTransition>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  height: 44px !important;
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

.max-w-180 {
  max-width: 180px;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.premium-card {
  position: relative;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  border: 2px solid transparent !important;
}

.card-selected {
  border-color: rgb(var(--v-theme-primary)) !important;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.15) !important;
}

.premium-card-decoration {
  position: absolute;
  top: 0;
  right: 0;
  width: 70px;
  height: 70px;
  border-radius: 0 0 0 100%;
}

.bg-primary-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.1) 0%, transparent 100%);
}

.bg-error-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-error), 0.1) 0%, transparent 100%);
}

.bg-success-opacity-2 {
  background-color: rgba(var(--v-theme-success), 0.05) !important;
}

.bg-warning-opacity {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.floating-selection-bar {
  position: fixed;
  bottom: 10px;
  left: 50%;
  transform: translateX(-50%);
  width: 90%;
  max-width: 800px;
  z-index: 100;
}
</style>
