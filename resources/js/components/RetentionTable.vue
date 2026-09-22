<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  invoices: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  downloadingPdf: { type: Object, default: () => ({}) },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  currentTab: { type: String, required: true },
  selected: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:options", "update:selected", "download-pdf", "delete-retention", "edit-retention", "generate-supplier"]);

const { mobile } = useDisplay();
const authStore = useAuthStore();

const selectedModel = computed({
  get: () => props.selected,
  set: (val) => emit("update:selected", val),
});

const pendingHeaders = [
  { title: "Fecha", key: "created_invoice_date", sortable: true },
  { title: "Razón Social", key: "supplier.name", sortable: true, width: "28%" },
  { title: "Nº Factura", key: "invoice_number", sortable: true },
  { title: "Exento", key: "exempt_amount", align: "end", sortable: true },
  { title: "BIG", key: "taxable_base", align: "end", sortable: true },
  { title: "IVA", key: "tax_amount", align: "end", sortable: true },
  { title: "Total", key: "total_amount", align: "end", sortable: true },
  { title: "RET.", key: "estimated_retention", align: "end", sortable: false },
];

const supplierHeaders = [
  { title: "Razón Social", key: "supplier.name", sortable: false, width: "26%" },
  { title: "Nº Facturas", key: "invoice_numbers", sortable: false, width: "22%" },
  { title: "Exento", key: "exempt_amount", align: "end", sortable: false },
  { title: "BIG", key: "taxable_base", align: "end", sortable: false },
  { title: "IVA", key: "tax_amount", align: "end", sortable: false },
  { title: "Total", key: "total_amount", align: "end", sortable: false },
  { title: "RET.", key: "withheld_amount", align: "end", sortable: false },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const generatedHeaders = [
  { title: "Fecha", key: "date", sortable: true },
  { title: "Razón Social", key: "supplier.name", sortable: true, width: "28%" },
  { title: "Comprobante", key: "number", sortable: true },
  { title: "BIG", key: "total_taxable_base", align: "end", sortable: true },
  { title: "IVA", key: "total_tax_amount", align: "end", sortable: true },
  { title: "RET.", key: "total_withheld_amount", align: "end", sortable: true },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const groupedBySupplier = computed(() => {
  if (!props.invoices || props.invoices.length === 0) return [];

  const map = new Map();
  props.invoices.forEach((inv) => {
    const sId = inv.supplier?.id || inv.supplier_id || `temp-${inv.id}`;
    if (!map.has(sId)) {
      map.set(sId, {
        id: sId,
        supplier: inv.supplier || { id: sId, name: "Sin Proveedor", social_reason: "Sin Proveedor", rif: "Sin RIF" },
        invoices: [],
        invoice_numbers: [],
        exempt_amount: 0,
        taxable_base: 0,
        tax_amount: 0,
        total_amount: 0,
        withheld_amount: 0,
      });
    }
    const item = map.get(sId);
    item.invoices.push(inv);
    if (inv.invoice_number && !item.invoice_numbers.includes(inv.invoice_number)) {
      item.invoice_numbers.push(inv.invoice_number);
    }
    item.exempt_amount += Number(inv.exempt_amount || 0);
    item.taxable_base += Number(inv.taxable_base || 0);
    item.tax_amount += Number(inv.tax_amount || 0);
    item.total_amount += Number(inv.total_amount || 0);
    item.withheld_amount += Number(inv.tax_amount || 0) * 0.75;
  });

  return Array.from(map.values());
});

const currentHeaders = computed(() => {
  if (props.currentTab === "by_supplier") return supplierHeaders;
  if (props.currentTab === "pending") return pendingHeaders;
  return generatedHeaders;
});

const currentTableItems = computed(() => {
  if (props.currentTab === "by_supplier") return groupedBySupplier.value;
  return props.invoices;
});

const currentItemsLength = computed(() => {
  if (props.currentTab === "by_supplier") return groupedBySupplier.value.length;
  return props.totalRecords;
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const formatDate = (date) => {
  if (!date) return "-";
  const str = String(date).trim();
  const dateOnly = str.split(" ")[0].split("T")[0];
  const parts = dateOnly.split("-");
  if (parts.length === 3) {
    const [year, month, day] = parts;
    return `${parseInt(day, 10)}/${parseInt(month, 10)}/${year}`;
  }
  return new Intl.DateTimeFormat("es-VE").format(new Date(date));
};

const getAvatarColor = (id) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[(id || 0) % colors.length];
};

const getInitials = (name) => {
  if (!name) return "P";
  return name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
};

const isItemSelected = (id) => props.selected.includes(id);

const isSupplierFiscalValid = (supplier) => {
  if (!supplier) return false;
  const hasRif = Boolean(supplier.rif && String(supplier.rif).trim() !== "");
  const hasAddress = Boolean(supplier.address && String(supplier.address).trim() !== "");
  return hasRif && hasAddress;
};

const getSupplierMissingFiscalData = (supplier) => {
  if (!supplier) return "Sin datos fiscales";
  const missing = [];
  if (!supplier.rif || String(supplier.rif).trim() === "") missing.push("RIF");
  if (!supplier.address || String(supplier.address).trim() === "") missing.push("Dirección Fiscal");
  return `Falta ${missing.join(" y ")}`;
};

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
  <div class="mt-0">
    <!-- Vista de Escritorio -->
    <VCard v-if="!mobile" class="rounded-lg border-0 shadow-none overflow-hidden bg-surface">
      <VDataTableServer
        v-model="selectedModel"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="currentHeaders"
        :items="currentTableItems"
        :items-length="currentItemsLength"
        :loading="props.loading"
        :show-select="props.currentTab === 'pending'"
        class="text-no-wrap premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            :title="props.currentTab === 'generated' ? 'No hay retenciones generadas' : 'No hay retenciones pendientes'"
            :message="props.currentTab === 'generated' ? 'No se han emitido comprobantes de retención aún.' : 'No se encontraron facturas sujetas a retención en este periodo.'"
            icon="tabler-receipt-off"
          />
        </template>

        <template #item.created_invoice_date="{ item }">
          <span class="text-sm font-weight-medium text-medium-emphasis uppercase">{{ formatDate(item.created_invoice_date) }}</span>
        </template>

        <template #item.date="{ item }">
          <span class="text-sm font-weight-medium text-medium-emphasis uppercase">{{ formatDate(item.date) }}</span>
        </template>

        <template #item.number="{ item }">
          <div class="d-flex align-center gap-1 py-2">
            <span class="text-sm font-weight-bold text-primary">{{ item.number }}</span>
          </div>
        </template>

        <template #item.supplier.name="{ item }">
          <div class="d-flex flex-column truncate py-2" style="max-width: 250px;">
            <span class="text-sm font-weight-bold text-high-emphasis text-capitalize truncate">{{ item.supplier?.name || item.supplier?.social_reason || 'N/A' }}</span>
            <span class="text-xs text-disabled truncate font-weight-medium">{{ item.supplier?.rif || item.identification || 'Sin RIF' }}</span>
          </div>
        </template>

        <template #item.invoice_number="{ item }">
          <div class="d-flex align-center gap-2">
            <span class="text-sm font-weight-bold text-primary">{{ item.invoice_number }}</span>
          </div>
        </template>

        <template #item.invoice_numbers="{ item }">
          <div class="d-flex align-center flex-wrap gap-1 py-1" style="max-width: 260px;">
            <VChip
              v-for="num in item.invoice_numbers"
              :key="num"
              size="small"
              variant="tonal"
              color="primary"
              class="font-weight-bold text-xs"
            >
              {{ num }}
            </VChip>
          </div>
        </template>

        <template #item.exempt_amount="{ item }">
          <span class="text-sm font-weight-medium">{{ formatCurrency(item.exempt_amount) }}</span>
        </template>

        <template #item.taxable_base="{ item }">
          <span class="text-sm font-weight-medium">{{ formatCurrency(item.taxable_base) }}</span>
        </template>

        <template #item.tax_amount="{ item }">
          <span class="text-sm font-weight-bold text-success">{{ formatCurrency(item.tax_amount) }}</span>
        </template>

        <template #item.total_amount="{ item }">
          <span class="text-sm font-weight-bold text-high-emphasis">{{ formatCurrency(item.total_amount) }}</span>
        </template>

        <template #item.estimated_retention="{ item }">
          <span class="text-sm font-weight-bold text-success">{{ formatCurrency(Number(item.tax_amount || 0) * 0.75) }}</span>
        </template>

        <template #item.withheld_amount="{ item }">
          <span class="text-sm font-weight-bold text-success">{{ formatCurrency(item.withheld_amount) }}</span>
        </template>

        <template #item.total_taxable_base="{ item }">
          <span class="text-sm font-weight-medium">{{ formatCurrency(item.total_taxable_base) }}</span>
        </template>

        <template #item.total_tax_amount="{ item }">
          <span class="text-sm font-weight-bold text-success">{{ formatCurrency(item.total_tax_amount) }}</span>
        </template>

        <template #item.total_withheld_amount="{ item }">
          <span class="text-sm font-weight-bold text-success">{{ formatCurrency(item.total_withheld_amount) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <template v-if="props.currentTab === 'by_supplier'">
              <VBtn
                icon
                variant="text"
                size="32"
                color="primary"
                class="rounded-lg shadow-sm"
                @click="emit('generate-supplier', item.invoices.map(i => i.id))"
              >
                <VIcon icon="tabler-file-percent" size="20" />
                <VTooltip activator="parent" location="top">Generar Retención</VTooltip>
              </VBtn>
            </template>
            <template v-else-if="props.currentTab === 'generated'">
              <VBtn
                icon
                variant="text"
                size="32"
                color="primary"
                class="rounded-lg shadow-sm"
                :loading="props.downloadingPdf[item.id]"
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
              <span class="text-super-xs text-disabled font-weight-bold uppercase">de {{ currentItemsLength }} registros</span>
            </div>
            <VPagination
               :model-value="props.page"
               :length="Math.ceil(currentItemsLength / props.itemsPerPage) || 1"
               size="small"
               class="premium-pagination"
               @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
            />
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil Premium Cards -->
    <div v-else class="d-flex flex-column gap-4 pa-2">
      <div v-if="props.loading" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else-if="currentTableItems.length > 0">
        <VCard
          v-for="item in currentTableItems"
          :key="item.id || item.order_id"
          class="rounded-lg border shadow-sm premium-card overflow-hidden"
          :class="{ 'card-selected': isItemSelected(item.id) && props.currentTab === 'pending' }"
          @click="props.currentTab === 'pending' ? toggleSelection(item.id) : null"
        >
          <div class="premium-card-decoration" :class="props.currentTab === 'generated' ? 'bg-success-opacity' : 'bg-primary-opacity'"></div>
          
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
                <div class="d-flex flex-column">
                  <span class="text-xs font-weight-black text-disabled uppercase leading-tight">
                    {{ props.currentTab === 'pending' ? 'Factura' : (props.currentTab === 'by_supplier' ? 'Proveedor' : 'Comprobante') }}
                  </span>
                  <span class="text-sm font-weight-bold text-primary leading-tight">
                    {{ props.currentTab === 'pending' ? item.invoice_number : (props.currentTab === 'by_supplier' ? `${item.invoices?.length || 0} Facturas` : item.number) }}
                  </span>
                </div>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-xs font-weight-black text-disabled uppercase leading-tight">Fecha</span>
                <span class="text-xs font-weight-bold leading-tight uppercase">
                  {{ props.currentTab === 'pending' ? formatDate(item.created_invoice_date) : (props.currentTab === 'by_supplier' ? '-' : formatDate(item.date)) }}
                </span>
              </div>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <!-- Info Proveedor -->
            <div class="mb-4">
              <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Razón Social</span>
              <span class="text-sm font-weight-bold text-high-emphasis d-block leading-tight text-capitalize mb-1">
                {{ item.supplier?.name || item.supplier?.social_reason || 'N/A' }}
              </span>
              <div class="d-flex align-center gap-1 flex-wrap">
                <span class="text-xs text-disabled leading-tight">{{ item.supplier?.rif || item.identification || 'Sin RIF' }}</span>
              </div>
            </div>

            <!-- Chips de facturas si es por proveedor -->
            <div v-if="props.currentTab === 'by_supplier' && item.invoice_numbers?.length" class="mb-4">
              <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Facturas Incluidas</span>
              <div class="d-flex align-center flex-wrap gap-1">
                <VChip
                  v-for="num in item.invoice_numbers"
                  :key="num"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="font-weight-bold text-super-xs"
                >
                  {{ num }}
                </VChip>
              </div>
            </div>

            <!-- Dashboard de Montos Móvil -->
            <div class="d-flex gap-3 mb-4">
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-surface-variant-opacity-2">
                <span class="text-super-xs text-disabled font-weight-bold uppercase d-block mb-1">Base Imponible</span>
                <span class="text-sm">
                  {{ props.currentTab === 'generated' ? formatCurrency(item.total_taxable_base) : formatCurrency(item.taxable_base) }}
                </span>
              </div>
              <div class="premium-stat-box flex-grow-1 pa-3 rounded-lg bg-info-opacity">
                <span class="text-super-xs text-info font-weight-bold uppercase d-block mb-1">Monto IVA</span>
                <span class="text-sm font-weight-bold text-success">
                  {{ props.currentTab === 'generated' ? formatCurrency(item.total_tax_amount) : formatCurrency(item.tax_amount) }}
                </span>
              </div>
            </div>

            <!-- Footer Card Móvil -->
            <div 
              class="d-flex align-center justify-space-between pa-3 rounded-lg"
              :class="props.currentTab === 'generated' ? 'bg-success-opacity-2' : 'bg-surface-variant-opacity-2'"
            >
              <span class="text-xs font-weight-bold uppercase">
                {{ props.currentTab === 'generated' ? 'Total Retenido' : (props.currentTab === 'by_supplier' ? 'Retención (75%)' : 'Monto Total') }}
              </span>
              <span 
                class="text-h6 font-weight-bold text-success"
              >
                {{ props.currentTab === 'generated' ? formatCurrency(item.total_withheld_amount) : (props.currentTab === 'by_supplier' ? formatCurrency(item.withheld_amount) : formatCurrency(item.total_amount)) }}
              </span>
            </div>

            <!-- Botones de Acción (Por Proveedor o Generados) -->
            <div v-if="props.currentTab === 'by_supplier'" class="mt-4">
              <VBtn
                color="primary"
                variant="flat"
                block
                class="rounded-lg text-xs font-weight-bold shadow-sm"
                @click.stop="emit('generate-supplier', item.invoices.map(i => i.id))"
              >
                <VIcon start icon="tabler-file-percent" size="18" />
                GENERAR RETENCIÓN ({{ item.invoices?.length || 0 }} FACTURAS)
              </VBtn>
            </div>
            <div v-else-if="props.currentTab === 'generated'" class="d-flex flex-column gap-2 mt-4">
              <VBtn
                color="primary"
                variant="flat"
                block
                class="rounded-lg text-xs font-weight-bold shadow-sm"
                :loading="props.downloadingPdf[item.id]"
                @click.stop="emit('download-pdf', item.id)"
              >
                <VIcon start icon="tabler-file-download" size="18" />
                DESCARGAR COMPROBANTE
              </VBtn>
              <div v-if="authStore.isAdmin" class="d-flex gap-2">
                <VBtn
                  color="warning"
                  variant="tonal"
                  class="flex-grow-1 rounded-lg text-xs font-weight-bold shadow-sm"
                  @click.stop="emit('edit-retention', item)"
                >
                  <VIcon start icon="tabler-edit" size="18" />
                  EDITAR
                </VBtn>
                <VBtn
                  color="error"
                  variant="tonal"
                  class="flex-grow-1 rounded-lg text-xs font-weight-bold shadow-sm"
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
        <div v-if="currentTableItems.length > 0" class="d-flex justify-center mt-2 pb-4">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(currentItemsLength / props.itemsPerPage) || 1"
            size="small"
            rounded="circle"
            class="premium-pagination"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>

      <VAlert v-else type="info" variant="tonal" class="rounded-lg">
        {{ props.currentTab === 'generated' ? 'No se han generado comprobantes para este período.' : 'No hay facturas pendientes de retención.' }}
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header th) {
  background: rgb(var(--v-theme-surface)) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.8125rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.04rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 14px !important;
  font-size: 0.875rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.7rem !important;
  letter-spacing: 0.05em !important;
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
  background-color: rgb(var(--v-theme-surface)) !important;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-pagination :deep(.v-pagination__item--active .v-btn) {
  background: rgb(var(--v-theme-primary)) !important;
  color: white !important;
  border: 0 !important;
}
</style>
