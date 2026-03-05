<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const loading = ref(false);
const invoices = ref([]);
const suppliers = ref([]);
const totalRecords = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const selected = ref([]);
const currentTab = ref("pending");

// Filtros
const search = ref("");
const supplierId = ref(null);
const startDate = ref("");
const endDate = ref("");
const sortBy = ref("created_invoice_date");
const orderBy = ref("desc");
const vSortBy = ref([{ key: "created_invoice_date", order: "desc" }]);

const setYearPreset = () => {
  const now = new Date();
  startDate.value = `${now.getFullYear()}-01-01`;
  endDate.value = `${now.getFullYear()}-12-31`;
};

const pendingHeaders = [
  { title: "", key: "select", sortable: false, width: "50px" },
  { title: "Fecha Factura", key: "created_invoice_date", sortable: true },
  { title: "Proveedor", key: "supplier.name", sortable: true },
  { title: "Nº Factura", key: "invoice_number", sortable: true },
  { title: "Base Imponible", key: "taxable_base", align: "end", sortable: true },
  { title: "IVA", key: "tax_amount", align: "end", sortable: true },
  { title: "Total", key: "total_amount", align: "end", sortable: true },
];

const generatedHeaders = [
  { title: "Nº Comprobante", key: "number", sortable: true },
  { title: "Fecha Emisión", key: "date", sortable: true },
  { title: "Proveedor", key: "supplier.name", sortable: true },
  { title: "Base Imponible", key: "total_taxable_base", align: "end", sortable: true },
  { title: "IVA Total", key: "total_tax_amount", align: "end", sortable: true },
  { title: "Total Retenido", key: "total_withheld_amount", align: "end", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/suppliers", { params: { itemsPerPage: -1 } });
    suppliers.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error al cargar proveedores", error);
  }
};

const fetchRetentions = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/retentions", {
      params: {
        page: page.value,
        itemsPerPage: itemsPerPage.value,
        search: search.value,
        start_date: startDate.value,
        end_date: endDate.value,
        supplier_id: supplierId.value,
        is_generated: currentTab.value === "generated",
        sortBy: sortBy.value,
        orderBy: orderBy.value,
      },
    });
    invoices.value = response.data.data;
    totalRecords.value = response.data.pagination.total;
  } catch (error) {
    toast.error("Error al cargar datos");
  } finally {
    loading.value = false;
  }
};

const handleSortChange = (newSort) => {
  if (newSort && newSort.length > 0) {
    sortBy.value = newSort[0].key;
    orderBy.value = newSort[0].order;
  } else {
    sortBy.value = "created_invoice_date";
    orderBy.value = "desc";
  }
  page.value = 1;
  fetchRetentions();
};

const handleBulkGenerate = async () => {
  if (selected.value.length === 0) return;

  try {
    const idsToProcess = [...selected.value];
    const response = await axios.post("/retentions/bulk-generate", {
      ids: idsToProcess,
    });
    
    toast.success(response.data.message);
    
    // Descargar el PDF usando el ID de la retención creada
    if (response.data.retention_id) {
        await downloadPdf(response.data.retention_id, true);
    }
    
    selected.value = [];
    fetchRetentions();
  } catch (error) {
    toast.error("Error al generar retenciones");
  }
};

const downloadPdf = async (id, isRetention = true) => {
  try {
    const params = isRetention ? { retention_id: id } : { ids: id };
    const response = await axios.get("/retentions/download", {
      params,
      responseType: "blob",
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `retencion_${id}.pdf`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  } catch (error) {
    toast.error("Error al descargar el PDF");
  }
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "VES",
  }).format(value);
};

const formatDate = (date) => {
  if (!date) return "-";
  return new Intl.DateTimeFormat("es-VE").format(new Date(date));
};

watch(currentTab, () => {
  page.value = 1;
  selected.value = [];
  fetchRetentions();
});

const clearFilters = () => {
  search.value = "";
  supplierId.value = null;
  setYearPreset();
  fetchRetentions();
};

onMounted(() => {
  setYearPreset();
  fetchSuppliers();
  fetchRetentions();
});
</script>

<template>
  <div class="retention-management">
    <!-- Filtros -->
    <VCard class="mb-6">
      <VCardText class="pa-6">
        <VRow>
          <VCol cols="12" sm="4" md="3">
            <AppTextField
              v-model="search"
              placeholder="Nº Factura o Proveedor"
              prepend-inner-icon="tabler-search"
              clearable
              @update:model-value="fetchRetentions"
            />
          </VCol>

          <VCol cols="12" sm="4" md="3">
            <AppSelect
              v-model="supplierId"
              :items="suppliers"
              item-title="name"
              item-value="id"
              placeholder="Proveedor"
              clearable
              @update:model-value="fetchRetentions"
            />
          </VCol>

          <VCol cols="12" sm="2" md="3">
            <AppDateTimePicker
              v-model="startDate"
              placeholder="Desde"
              :config="{ dateFormat: 'Y-m-d' }"
              clearable
              @update:model-value="fetchRetentions"
            />
          </VCol>

          <VCol cols="12" sm="2" md="3">
            <AppDateTimePicker
              v-model="endDate"
              placeholder="Hasta"
              :config="{ dateFormat: 'Y-m-d' }"
              clearable
              @update:model-value="fetchRetentions"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6 d-flex align-center">
        <VBtn
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-filter-off"
          @click="clearFilters"
        >
          Limpiar Filtros
        </VBtn>

        <VSpacer />

        <div class="d-flex gap-4">
          <VBtn
            v-if="selected.length > 0 && currentTab === 'pending'"
            color="success"
            variant="flat"
            prepend-icon="tabler-check"
            @click="handleBulkGenerate"
          >
            Generar {{ selected.length }} Retenciones
          </VBtn>
        </div>
      </VCardActions>
    </VCard>

    <!-- Tabla de Resultados -->
    <VCard>
      <VCardTitle class="d-flex align-center py-4">
        <VIcon icon="tabler-file-percent" class="me-2" />
        Gestión de Retenciones de IVA
      </VCardTitle>

      <VTabs v-model="currentTab" color="primary" grow>
        <VTab value="pending">
          <VIcon icon="tabler-clock-pause" class="me-2" />
          Facturas Pendientes
        </VTab>
        <VTab value="generated">
          <VIcon icon="tabler-checkbox" class="me-2" />
          Comprobantes Generados
        </VTab>
      </VTabs>

      <VDivider />

    <VDataTableServer
      v-model="selected"
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      v-model:sort-by="vSortBy"
      :headers="currentTab === 'pending' ? pendingHeaders : generatedHeaders"
      :items="invoices"
      :items-length="totalRecords"
      :loading="loading"
      :show-select="currentTab === 'pending'"
      select-strategy="multiple"
      @update:page="fetchRetentions"
      @update:items-per-page="fetchRetentions"
      @update:sort-by="handleSortChange"
    >
      <template #[`item.created_invoice_date`]="{ item }">
        {{ formatDate(item.created_invoice_date) }}
      </template>

      <template #[`item.date`]="{ item }">
        {{ formatDate(item.date) }}
      </template>

      <template #[`item.supplier.social_reason`]="{ item }">
        {{ item.supplier?.social_reason || item.supplier?.name || 'N/A' }}
      </template>

      <template #[`item.taxable_base`]="{ item }">
        {{ formatCurrency(item.taxable_base) }}
      </template>

      <template #[`item.total_taxable_base`]="{ item }">
        {{ formatCurrency(item.total_taxable_base) }}
      </template>

      <template #[`item.tax_amount`]="{ item }">
        {{ formatCurrency(item.tax_amount) }}
      </template>

      <template #[`item.total_tax_amount`]="{ item }">
        {{ formatCurrency(item.total_tax_amount) }}
      </template>

      <template #[`item.total_amount`]="{ item }">
        {{ formatCurrency(item.total_amount) }}
      </template>

      <template #[`item.total_withheld_amount`]="{ item }">
        {{ formatCurrency(item.total_withheld_amount) }}
      </template>

      <template #[`item.actions`]="{ item }">
        <VBtn
          icon="tabler-file-download"
          variant="text"
          color="primary"
          @click="downloadPdf(item.id, true)"
        >
          <VTooltip activator="parent" location="top">Descargar Comprobante</VTooltip>
          <VIcon icon="tabler-file-download" />
        </VBtn>
      </template>
    </VDataTableServer>
    </VCard>
  </div>
</template>
