<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  pendingPayments: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  selectedTableInvoices: { type: Array, default: () => [] },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
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
  return new Date(date).toLocaleDateString("es-VE");
};

const formatDueDate = (paymentDate) => {
  if (!paymentDate) return "N/A";
  const dueDate = new Date(paymentDate);
  dueDate.setDate(dueDate.getDate() - 1);
  return dueDate.toLocaleDateString("es-VE");
};

const isOverdue = (paymentDate) => {
  if (!paymentDate) return false;
  const dueDate = new Date(paymentDate);
  dueDate.setDate(dueDate.getDate() - 1);
  return dueDate < new Date();
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

const getDisplayAmount = (item) => {
  if (item.is_indexed && item.indexed_data?.is_indexed) {
    return item.indexed_data.indexed_amount;
  }
  return item.total_amount;
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
          <span class="text-xs font-weight-black text-primary">{{ item.invoice_number }}</span>
        </template>

        <template #item.supplier_name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar :color="getAvatarColor(item.supplier_name)" variant="tonal" size="30" class="rounded-lg">
              <span class="text-super-xs font-weight-black">{{ getInitials(item.supplier_name) }}</span>
            </VAvatar>
            <span class="text-xs font-weight-bold text-high-emphasis truncate text-capitalize" style="max-width: 180px;">
              {{ item.supplier_name }}
            </span>
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
          <span class="text-xs font-weight-black" :class="getRemainingAmountClass(item)">
            {{ formatCurrency(getDisplayAmount(item), item.currency, true) }}
          </span>
        </template>

        <template #item.is_indexed="{ item }">
          <VSwitch
            v-model="item.is_indexed"
            color="primary"
            density="compact"
            hide-details
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
            <VMenu :close-on-content-click="false" location="start">
              <template #activator="{ props: menuProps }">
                <VBtn
                  v-bind="menuProps"
                  icon
                  variant="tonal"
                  size="32"
                  color="info"
                  class="rounded-circle shadow-sm"
                >
                  <VIcon icon="tabler-calendar" size="18" />
                  <VTooltip activator="parent" location="top">Cambiar Fecha Vencimiento</VTooltip>
                </VBtn>
              </template>
              <VCard min-width="250" class="pa-4 rounded-lg shadow-lg">
                <div class="text-xs font-weight-black uppercase mb-2 text-disabled">Nueva Fecha Pago</div>
                <AppDateTimePicker
                  :model-value="item.payment_date"
                  placeholder="Seleccionar Fecha"
                  variant="outlined"
                  density="compact"
                  :config="{ altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                  @update:model-value="(val) => emit('update-date', item, val)"
                />
              </VCard>
            </VMenu>

            <!-- Marcar como Pagado Directamente -->
            <VBtn
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
      <template v-if="props.pendingPayments.length > 0">
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
                  <span class="text-sm font-weight-black text-primary leading-tight">{{ item.invoice_number }}</span>
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
            <div class="mb-4">
              <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Proveedor</span>
              <span class="text-sm font-weight-bold text-high-emphasis d-block leading-tight text-capitalize">{{ item.supplier_name }}</span>
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
                  @click.stop="emit('toggle-indexed', item)"
                />
              </div>
              <div class="d-flex gap-1">
                <!-- Editar Fecha (Móvil) -->
                <VMenu :close-on-content-click="false" location="top">
                  <template #activator="{ props: menuProps }">
                    <VBtn
                      v-bind="menuProps"
                      icon
                      variant="tonal"
                      size="32"
                      color="info"
                      class="rounded-lg shadow-sm"
                      @click.stop
                    >
                      <VIcon icon="tabler-calendar-edit" size="18" />
                    </VBtn>
                  </template>
                  <VCard min-width="250" class="pa-4 rounded-lg shadow-lg">
                    <div class="text-xs font-weight-black uppercase mb-2 text-disabled">Nueva Fecha Pago</div>
                    <AppDateTimePicker
                      :model-value="item.payment_date"
                      placeholder="Seleccionar Fecha"
                      variant="outlined"
                      density="compact"
                      :config="{ altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                      @update:model-value="(val) => emit('update-date', item, val)"
                    />
                  </VCard>
                </VMenu>

                <!-- Marcar Pagada (Móvil) -->
                <VBtn
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

    <!-- Barra de Selección Móvil Flotante -->
    <VSlideYReverseTransition>
      <div v-if="mobile && props.selectedTableInvoices.length > 0" class="mobile-action-bar pa-4">
        <VCard color="primary" class="rounded-xl shadow-lg pa-3 d-flex align-center justify-space-between border-0">
          <div class="d-flex align-center gap-3 ms-2">
            <VAvatar color="white" variant="tonal" size="32" class="rounded-lg">
              <span class="text-xs font-weight-black text-white">{{ props.selectedTableInvoices.length }}</span>
            </VAvatar>
            <span class="text-xs font-weight-black text-white uppercase letter-spacing-1">Seleccionados</span>
          </div>
          <div class="d-flex gap-2">
            <VBtn
              icon
              variant="tonal"
              color="white"
              size="38"
              class="rounded-lg"
              @click="emit('deselect-all')"
            >
              <VIcon icon="tabler-x" size="20" />
            </VBtn>
            <VBtn
              icon
              variant="flat"
              color="white"
              size="38"
              class="rounded-lg shadow-sm"
              @click="emit('process-multiple')"
            >
              <VIcon icon="tabler-credit-card" size="20" color="primary" />
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

.mobile-action-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: linear-gradient(to top, rgba(var(--v-theme-surface), 0.9), transparent);
  backdrop-filter: blur(4px);
}
</style>
