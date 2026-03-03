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

const setYearPreset = () => {
  const now = new Date();
  startDate.value = `${now.getFullYear()}-01-01`;
  endDate.value = `${now.getFullYear()}-12-31`;
};

const pendingHeaders = [
  { title: "Seleccionar", key: "select", sortable: false, width: "50px" },
  { title: "Fecha Factura", key: "created_invoice_date" },
  { title: "Proveedor", key: "supplier.social_reason" },
  { title: "Nº Factura", key: "invoice_number" },
  { title: "Base Imponible", key: "taxable_base", align: "end" },
  { title: "IVA", key: "tax_amount", align: "end" },
  { title: "Total", key: "total_amount", align: "end" },
];

const generatedHeaders = [
  { title: "Nº Comprobante", key: "number" },
  { title: "Fecha Emisión", key: "date" },
  { title: "Proveedor", key: "supplier.social_reason" },
  { title: "Base Imponible", key: "total_taxable_base", align: "end" },
  { title: "IVA Total", key: "total_tax_amount", align: "end" },
  { title: "Total Retenido", key: "total_withheld_amount", align: "end" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/suppliers");
    suppliers.value = response.data;
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

onMounted(() => {
  setYearPreset();
  fetchSuppliers();
  fetchRetentions();
});
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center py-4">
      <VIcon icon="tabler-file-percent" class="me-2" />
      Gestión de Retenciones de IVA
      <VSpacer />
      <VBtn
        v-if="selected.length > 0 && currentTab === 'pending'"
        color="success"
        prepend-icon="tabler-check"
        @click="handleBulkGenerate"
      >
        Generar {{ selected.length }} Retenciones
      </VBtn>
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

    <VCardText class="pa-6">
      <VRow>
        <VCol cols="12" md="4">
          <AppTextField
            v-model="search"
            placeholder="Buscar por Nº o Proveedor"
            prepend-inner-icon="tabler-search"
            clearable
            @update:model-value="fetchRetentions"
          />
        </VCol>

        <VCol cols="12" md="2">
          <AppDateTimePicker
            v-model="startDate"
            placeholder="Desde"
            :config="{ dateFormat: 'Y-m-d' }"
            clearable
            @update:model-value="fetchRetentions"
          />
        </VCol>

        <VCol cols="12" md="2">
          <AppDateTimePicker
            v-model="endDate"
            placeholder="Hasta"
            :config="{ dateFormat: 'Y-m-d' }"
            clearable
            @update:model-value="fetchRetentions"
          />
        </VCol>

        <VCol cols="12" md="3">
          <AppSelect
            v-model="supplierId"
            :items="suppliers"
            item-title="social_reason"
            item-value="id"
            placeholder="Filtrar por Proveedor"
            clearable
            @update:model-value="fetchRetentions"
          />
        </VCol>

        <VCol cols="12" md="1" class="d-flex align-center">
          <VBtn
            icon="tabler-filter-off"
            variant="text"
            color="secondary"
            @click="clearFilters"
          >
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            <VIcon icon="tabler-filter-off" />
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model="selected"
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :headers="currentTab === 'pending' ? pendingHeaders : generatedHeaders"
      :items="invoices"
      :items-length="totalRecords"
      :loading="loading"
      :show-select="currentTab === 'pending'"
      select-strategy="multiple"
      @update:options="fetchRetentions"
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
</template>
