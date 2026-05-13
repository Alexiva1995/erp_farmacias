<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  invoices: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  currentTab: { type: String, required: true },
  selected: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:options", "update:selected", "download-pdf", "delete-retention", "edit-retention"]);

const { mobile } = useDisplay();
const authStore = useAuthStore();

const selectedModel = computed({
  get: () => props.selected,
  set: (val) => emit("update:selected", val),
});

const pendingHeaders = [
  { title: "S", key: "select", sortable: false, width: "40px" },
  { title: "Fecha Factura", key: "created_invoice_date", sortable: true },
  { title: "Proveedor / Razón Social", key: "supplier.name", sortable: true, width: "30%" },
  { title: "Nº Factura", key: "invoice_number", sortable: true },
  { title: "Base Imponible", key: "taxable_base", align: "end", sortable: true },
  { title: "IVA", key: "tax_amount", align: "end", sortable: true },
  { title: "Total", key: "total_amount", align: "end", sortable: true },
];

const generatedHeaders = [
  { title: "Comprobante #", key: "number", sortable: true },
  { title: "Fecha Emisión", key: "date", sortable: true },
  { title: "Proveedor", key: "supplier.name", sortable: true, width: "30%" },
  { title: "Base Total", key: "total_taxable_base", align: "end", sortable: true },
  { title: "IVA Total", key: "total_tax_amount", align: "end", sortable: true },
  { title: "Monto Retenido", key: "total_withheld_amount", align: "end", sortable: true },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const formatDate = (date) => {
  if (!date) return "-";
  return new Intl.DateTimeFormat("es-VE").format(new Date(date));
};

const getAvatarColor = (id) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[id % colors.length];
};

const getInitials = (name) => {
  if (!name) return "P";
  return name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
};

const isItemSelected = (id) => props.selected.includes(id);

const toggleSelection = (id) => {
  const index = props.selected.indexOf(id);
  const newSelected = [...props.selected];
  if (index === -1) {
    newSelected.push(id);
  } else {
    newSelected.splice(index, 1);
  }
  emit("update:selected", newSelected);
};
</script>

<template>
  <div class="mt-4">
    <!-- Vista de Escritorio -->
    <VCard v-if="!mobile" class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VDataTableServer
        v-model="selectedModel"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="props.currentTab === 'pending' ? pendingHeaders : generatedHeaders"
        :items="props.invoices"
        :items-length="props.totalRecords"
        :loading="props.loading"
        :show-select="props.currentTab === 'pending'"
        class="text-no-wrap premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <!-- Custom Items Desktop -->
        <template #item.created_invoice_date="{ item }">
          <span class="text-xs font-weight-medium text-disabled uppercase">{{ formatDate(item.created_invoice_date) }}</span>
        </template>

        <template #item.date="{ item }">
          <span class="text-xs font-weight-medium text-disabled uppercase">{{ formatDate(item.date) }}</span>
        </template>

        <template #item.number="{ item }">
          <div class="d-flex align-center gap-2 py-2">
            <VIcon icon="tabler-hash" size="14" color="disabled" />
            <span class="font-weight-black text-primary">{{ item.number }}</span>
          </div>
        </template>

        <template #item.supplier.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              :color="getAvatarColor(item.supplier?.id || 0)"
              size="32"
              variant="tonal"
              class="rounded-lg"
            >
              <span class="text-xs font-weight-black">{{ getInitials(item.supplier?.name || item.supplier?.social_reason || 'N/A') }}</span>
            </VAvatar>
            <div class="d-flex flex-column truncate" style="max-width: 250px;">
              <span class="text-xs font-weight-bold text-high-emphasis text-capitalize truncate">{{ item.supplier?.name || item.supplier?.social_reason || 'N/A' }}</span>
              <span class="text-super-xs text-disabled truncate">{{ item.supplier?.rif || item.identification || 'Sin RIF' }}</span>
            </div>
          </div>
        </template>

        <template #item.invoice_number="{ item }">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-receipt" size="16" color="disabled" />
            <span class="font-weight-black text-primary">{{ item.invoice_number }}</span>
          </div>
        </template>

        <template #item.taxable_base="{ item }">
          <span class="text-xs font-weight-medium">{{ formatCurrency(item.taxable_base) }}</span>
        </template>

        <template #item.tax_amount="{ item }">
          <span class="text-xs font-weight-medium text-info">{{ formatCurrency(item.tax_amount) }}</span>
        </template>

        <template #item.total_amount="{ item }">
          <span class="text-sm font-weight-black text-high-emphasis">{{ formatCurrency(item.total_amount) }}</span>
        </template>

        <template #item.total_taxable_base="{ item }">
          <span class="text-xs font-weight-medium">{{ formatCurrency(item.total_taxable_base) }}</span>
        </template>

        <template #item.total_tax_amount="{ item }">
          <span class="text-xs font-weight-medium text-info">{{ formatCurrency(item.total_tax_amount) }}</span>
        </template>

        <template #item.total_withheld_amount="{ item }">
          <span class="text-sm font-weight-black text-success">{{ formatCurrency(item.total_withheld_amount) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VBtn
              icon
              variant="text"
              size="32"
              color="primary"
              class="rounded-lg shadow-sm"
              @click="emit('download-pdf', item.id)"
            >
              <VIcon icon="tabler-file-download" size="20" />
              <VTooltip activator="parent" location="top">Descargar Comprobante</VTooltip>
            </VBtn>
            <template v-if="authStore.isAdmin">
              <VBtn
                icon
                variant="text"
                size="32"
                color="warning"
                class="rounded-lg shadow-sm"
                @click="emit('edit-retention', item)"
              >
                <VIcon icon="tabler-edit" size="20" />
                <VTooltip activator="parent" location="top">Editar Número</VTooltip>
              </VBtn>
              <VBtn
                icon
                variant="text"
                size="32"
                color="error"
                class="rounded-lg shadow-sm"
                @click="emit('delete-retention', item.id)"
              >
                <VIcon icon="tabler-trash" size="20" />
                <VTooltip activator="parent" location="top">Eliminar Retención</VTooltip>
              </VBtn>
            </template>
          </div>
        </template>

        <template #bottom>
          <VDivider class="opacity-10" />
          <div class="d-flex align-center justify-space-between pa-4">
            <div class="d-flex align-center gap-4">
              <span class="text-super-xs text-disabled font-weight-bold uppercase">Mostrar</span>
              <VSelect
                :model-value="props.itemsPerPage"
                :items="[10, 25, 50, 100]"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 80px;"
                class="text-xs font-weight-black"
                @update:model-value="(val) => emit('update:options', { ...props, itemsPerPage: val, page: 1 })"
              />
              <span class="text-super-xs text-disabled font-weight-bold uppercase">de {{ props.totalRecords }} registros</span>
            </div>
            <VPagination
               :model-value="props.page"
               :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
               size="small"
               class="premium-pagination"
               @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
            />
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil Premium Cards -->
    <div v-else class="d-flex flex-column gap-4">
      <div v-if="props.loading" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else-if="props.invoices.length > 0">
        <VCard
          v-for="item in props.invoices"
          :key="item.id || item.order_id"
          class="rounded-lg border shadow-sm premium-card overflow-hidden"
          :class="{ 'card-selected': isItemSelected(item.id) && props.currentTab === 'pending' }"
          @click="props.currentTab === 'pending' ? toggleSelection(item.id) : null"
        >
          <div class="premium-card-decoration" :class="props.currentTab === 'pending' ? 'bg-primary-opacity' : 'bg-success-opacity'"></div>
          
          <VCardText class="pa-5">
            <!-- Cabecera Móvil -->
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <div v-if="props.currentTab === 'pending'" class="premium-checkbox-wrapper">
                  <VCheckboxBtn
                    :model-value="isItemSelected(item.id)"
                    density="compact"
                    color="primary"
                    class="ms-n2"
                  />
                </div>
                <VAvatar
                  :color="getAvatarColor(item.supplier?.id || 0)"
                  size="38"
                  variant="tonal"
                  class="rounded-lg shadow-sm"
                >
                  <VIcon :icon="props.currentTab === 'pending' ? 'tabler-receipt' : 'tabler-file-percent'" size="18" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-xs font-weight-black text-disabled uppercase leading-tight">
                    {{ props.currentTab === 'pending' ? 'Factura #' : 'Comprobante #' }}
                  </span>
                  <span class="text-sm font-weight-black text-primary leading-tight">
                    {{ props.currentTab === 'pending' ? item.invoice_number : item.number }}
                  </span>
                </div>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-xs font-weight-black text-disabled uppercase leading-tight">Fecha</span>
                <span class="text-xs font-weight-bold leading-tight uppercase">
                  {{ props.currentTab === 'pending' ? formatDate(item.created_invoice_date) : formatDate(item.date) }}
                </span>
              </div>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <!-- Info Proveedor -->
            <div class="mb-4">
              <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Proveedor / RIF</span>
              <span class="text-sm font-weight-bold text-high-emphasis d-block leading-tight text-capitalize mb-1">
                {{ item.supplier?.name || item.supplier?.social_reason || 'N/A' }}
              </span>
              <span class="text-xs text-disabled leading-tight">{{ item.supplier?.rif || item.identification || 'Sin RIF' }}</span>
            </div>

            <!-- Dashboard de Montos Móvil -->
            <div class="d-flex gap-3 mb-4">
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block mb-1">Base Imponible</span>
                <span class="text-sm font-weight-black">
                  {{ props.currentTab === 'pending' ? formatCurrency(item.taxable_base) : formatCurrency(item.total_taxable_base) }}
                </span>
              </div>
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-info-opacity">
                <span class="text-super-xs text-info font-weight-bold uppercase d-block mb-1">Monto IVA</span>
                <span class="text-sm font-weight-black text-info">
                  {{ props.currentTab === 'pending' ? formatCurrency(item.tax_amount) : formatCurrency(item.total_tax_amount) }}
                </span>
              </div>
            </div>

            <!-- Footer Card Móvil -->
            <div 
              class="d-flex align-center justify-space-between pa-3 rounded-lg"
              :class="props.currentTab === 'pending' ? 'bg-surface-variant-opacity-2' : 'bg-success-opacity-2'"
            >
              <span class="text-xs font-weight-black uppercase">
                {{ props.currentTab === 'pending' ? 'Monto Total' : 'Total Retenido' }}
              </span>
              <span 
                class="text-h6 font-weight-black"
                :class="props.currentTab === 'pending' ? 'text-high-emphasis' : 'text-success'"
              >
                Bs. {{ props.currentTab === 'pending' ? formatCurrency(item.total_amount) : formatCurrency(item.total_withheld_amount) }}
              </span>
            </div>

            <!-- Botones de Acción (Solo Generados) -->
            <div v-if="props.currentTab === 'generated'" class="d-flex flex-column gap-2 mt-4">
              <VBtn
                color="primary"
                variant="flat"
                block
                class="rounded-lg text-xs font-weight-black shadow-sm"
                @click.stop="emit('download-pdf', item.id)"
              >
                <VIcon start icon="tabler-file-download" size="18" />
                DESCARGAR COMPROBANTE
              </VBtn>
              <div v-if="authStore.isAdmin" class="d-flex gap-2">
                <VBtn
                  color="warning"
                  variant="tonal"
                  class="flex-grow-1 rounded-lg text-xs font-weight-black shadow-sm"
                  @click.stop="emit('edit-retention', item)"
                >
                  <VIcon start icon="tabler-edit" size="18" />
                  EDITAR
                </VBtn>
                <VBtn
                  color="error"
                  variant="tonal"
                  class="flex-grow-1 rounded-lg text-xs font-weight-black shadow-sm"
                  @click.stop="emit('delete-retention', item.id)"
                >
                  <VIcon start icon="tabler-trash" size="18" />
                  ELIMINAR
                </VBtn>
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-2 pb-4">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            rounded="circle"
            class="premium-pagination"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>

      <VAlert v-else type="info" variant="tonal" class="rounded-lg">
        {{ props.currentTab === 'pending' ? 'No hay facturas pendientes de retención.' : 'No se han generado comprobantes para este período.' }}
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
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
  opacity: 0.5;
}

.bg-primary-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.1) 0%, transparent 100%);
}

.bg-success-opacity {
  background: linear-gradient(135deg, rgba(var(--v-theme-success), 0.1) 0%, transparent 100%);
}

.bg-success-opacity-2 {
  background-color: rgba(var(--v-theme-success), 0.08) !important;
}

.bg-info-opacity {
  background-color: rgba(var(--v-theme-info), 0.05) !important;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.premium-pagination :deep(.v-btn) {
  background-color: white !important;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-pagination :deep(.v-pagination__item--active .v-btn) {
  background: rgb(var(--v-theme-primary)) !important;
  color: white !important;
  border: 0 !important;
}

.premium-checkbox-wrapper :deep(.v-checkbox-btn .v-selection-control) {
  min-height: auto;
}
</style>
