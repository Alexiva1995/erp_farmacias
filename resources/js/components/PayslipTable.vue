<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "finalize-payslip",
  "download-excel",
  "download-pdf",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: false, width: "80px" },
  { title: "PERIODO", key: "payslip_date", sortable: false },
  { title: "TOTAL BRUTO", key: "total", sortable: false, align: "end" },
  { title: "NETO PAGADO", key: "payed", sortable: false, align: "end" },
  { title: "DIVISA", key: "currency", sortable: false, align: "center", width: "90px" },
  { title: "ESTADO", key: "status", sortable: false, align: "center" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center", width: "160px" },
];

const formatCurrency = (amount, currencyCode) => {
  if (!amount && amount !== 0) return "-";
  const isCop = currencyCode === 'COP';
  const symbol = currencyCode || 'USD';

  if (isCop) {
    return Math.round(amount)
      .toString()
      .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
  }

  return new Intl.NumberFormat("es-ES", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount) + " " + symbol;
};

const formatDate = (date) => {
  if (!date) return "N/A";
  // Asumiendo formato YYYY-MM-DD
  const [year, month] = date.split('-');
  const months = ["ENE", "FEB", "MAR", "ABR", "MAY", "JUN", "JUL", "AGO", "SEP", "OCT", "NOV", "DIC"];
  return `${months[parseInt(month) - 1]} ${year}`;
};

const getAvatarColor = (id) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[id % colors.length];
};
</script>

<template>
  <div class="mt-4">
    <!-- Vista Escritorio -->
    <VCard v-if="!mobile" class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VDataTableServer
        :headers="headers"
        :items-per-page="props.itemsPerPage"
        :items="props.items"
        :items-length="props.total"
        :loading="loading"
        :page="props.page"
        class="text-no-wrap premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.payslip_date="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar :color="getAvatarColor(item.id)" variant="tonal" size="30" class="rounded-lg">
              <VIcon icon="tabler-calendar" size="16" />
            </VAvatar>
            <span class="text-sm font-weight-bold text-high-emphasis uppercase">
              {{ formatDate(item.payslip_date) }}
            </span>
          </div>
        </template>

        <template #item.total="{ item }">
          <span class="text-sm font-weight-bold">{{ formatCurrency(item.total, item.currency) }}</span>
        </template>

        <template #item.payed="{ item }">
          <span v-if="item.status === 1" class="text-sm font-weight-black text-success">
            {{ formatCurrency(item.payed, item.currency) }}
          </span>
          <span v-else class="text-xs text-disabled font-weight-medium">PENDIENTE</span>
        </template>

        <template #item.currency="{ item }">
          <VChip size="x-small" variant="tonal" class="rounded font-weight-black uppercase px-2">
            {{ item.currency }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip 
            :color="item.status === 1 ? 'success' : 'warning'" 
            variant="flat" 
            size="x-small" 
            class="font-weight-black rounded px-2"
          >
            {{ item.status === 1 ? 'FINALIZADA' : 'PENDIENTE' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <!-- Ver Nómina Legal -->
            <VTooltip text="Nómina Legal (Bs)" location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  :href="'/finances/payslips/' + item.id + '?tab=legal'"
                  icon="tabler-eyeglass"
                  variant="text"
                  color="info"
                  size="32"
                  class="rounded-lg"
                />
              </template>
            </VTooltip>

            <!-- Ver Nómina Completa -->
            <VTooltip text="Nómina Completa (Interna)" location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  :href="'/finances/payslips/' + item.id + '?tab=eye'"
                  icon="tabler-eye"
                  variant="text"
                  color="warning"
                  size="32"
                  class="rounded-lg"
                />
              </template>
            </VTooltip>

            <VDivider vertical class="mx-1 my-2" />

            <!-- Descargas -->
            <VTooltip v-if="item.status === 1" text="Descargar PDF Legal" location="top">
              <template #activator="{ props }">
                <IconBtn v-bind="props" color="primary" size="32" @click="emit('download-pdf', item.id, 'legal')">
                  <VIcon icon="tabler-file-type-pdf" size="18" />
                </IconBtn>
              </template>
            </VTooltip>

            <VTooltip v-if="item.status === 0" text="Finalizar Nómina" location="top">
              <template #activator="{ props }">
                <IconBtn v-bind="props" color="success" size="32" @click="emit('finalize-payslip', item)">
                  <VIcon icon="tabler-file-check" size="18" />
                </IconBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <template #no-data>
           <div class="text-center py-10 opacity-50">
             <VIcon icon="tabler-file-off" size="48" class="mb-2" />
             <div class="text-xs font-weight-black uppercase">No hay registros de nómina</div>
           </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil Cards -->
    <div v-else class="d-flex flex-column gap-4 pb-16">
      <template v-if="props.items.length > 0">
        <VCard
          v-for="item in props.items"
          :key="item.id"
          class="rounded-lg border shadow-sm premium-card overflow-hidden"
        >
          <div class="premium-card-decoration" :class="item.status === 1 ? 'bg-success-opacity' : 'bg-warning-opacity'"></div>
          
          <VCardText class="pa-5">
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <VAvatar :color="getAvatarColor(item.id)" variant="tonal" size="38" class="rounded-lg">
                  <VIcon icon="tabler-file-spreadsheet" size="18" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-sm font-weight-black text-disabled uppercase leading-tight">Nómina {{ item.id }}</span>
                  <span class="text-sm font-weight-black text-primary leading-tight uppercase">{{ formatDate(item.payslip_date) }}</span>
                </div>
              </div>
              <VChip 
                :color="item.status === 1 ? 'success' : 'warning'" 
                variant="flat" 
                size="x-small" 
                class="font-weight-black rounded px-2"
              >
                {{ item.status === 1 ? 'FINALIZADA' : 'PENDIENTE' }}
              </VChip>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <div class="d-flex gap-3 mb-4">
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block mb-1">Total Bruto</span>
                <span class="text-sm font-weight-black">{{ formatCurrency(item.total, item.currency) }}</span>
              </div>
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block mb-1">Neto Pagado</span>
                <span class="text-sm font-weight-black text-success">{{ formatCurrency(item.payed, item.currency) }}</span>
              </div>
            </div>

            <!-- Botones de Acción Móvil -->
            <div class="d-flex align-center gap-2 w-100">
              <VBtn
                color="primary"
                variant="tonal"
                class="rounded-lg font-weight-black text-xs h-10 flex-grow-1"
                :href="'/finances/payslips/' + item.id + '?tab=legal'"
              >
                VER DETALLES
              </VBtn>
              <VBtn
                v-if="item.status === 1"
                icon="tabler-file-type-pdf"
                color="error"
                variant="tonal"
                class="rounded-lg"
                size="40"
                min-width="40"
                @click="emit('download-pdf', item.id, 'legal')"
              />
              <VBtn
                v-if="item.status === 0"
                icon="tabler-file-check"
                color="success"
                variant="flat"
                class="rounded-lg shadow-sm"
                size="40"
                min-width="40"
                @click="emit('finalize-payslip', item)"
              />
            </div>
          </VCardText>
        </VCard>
      </template>

      <VAlert v-else type="info" variant="tonal" class="rounded-lg">
        No hay registros de nómina encontrados.
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

.premium-card {
  position: relative;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.premium-card-decoration {
  position: absolute;
  block-size: 70px;
  inline-size: 70px;
  border-radius: 0 0 0 100%;
  inset-block-start: 0;
  inset-inline-end: 0;
}

.bg-success-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-success), 0.1) 0%, transparent 100%);
}

.bg-warning-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-warning), 0.1) 0%, transparent 100%);
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.h-10 {
  block-size: 40px !important;
}
</style>
