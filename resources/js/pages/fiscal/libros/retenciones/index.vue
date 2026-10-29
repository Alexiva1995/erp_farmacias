<script setup>
import PurchaseBookFilters from "@/components/fiscal/PurchaseBookFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const loading = ref(false);
const books = ref([]);
const totalBooks = ref(0);
const page = ref(1);
const itemsPerPage = ref(15);

const filters = reactive({
  year: new Date().getFullYear(),
  month: null,
  status: null,
});

const showGenerateModal = ref(false);
const isGenerating = ref(false);
const generateForm = reactive({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
  fortnight: new Date().getDate() <= 15 ? 1 : 2,
});

const months = [
  { title: "Enero", value: 1 },
  { title: "Febrero", value: 2 },
  { title: "Marzo", value: 3 },
  { title: "Abril", value: 4 },
  { title: "Mayo", value: 5 },
  { title: "Junio", value: 6 },
  { title: "Julio", value: 7 },
  { title: "Agosto", value: 8 },
  { title: "Septiembre", value: 9 },
  { title: "Octubre", value: 10 },
  { title: "Noviembre", value: 11 },
  { title: "Diciembre", value: 12 },
];

const years = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i);

const formatCurrency = (val) => {
  return Number(val || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const fetchBooks = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/vat-retention-books", {
      params: {
        page: page.value,
        per_page: itemsPerPage.value,
        year: filters.year || undefined,
        month: filters.month || undefined,
        status: filters.status || undefined,
      },
    });

    books.value = response.data?.data || [];
    totalBooks.value = response.data?.pagination?.total || 0;
  } catch (error) {
    console.error("Error al cargar reportes de retenciones:", error);
    toast.error("Ocurrió un error al cargar los reportes de retenciones.");
  } finally {
    loading.value = false;
  }
};

const handleTableOptions = (options) => {
  if (options.page !== undefined) page.value = options.page;
  if (options.itemsPerPage !== undefined) itemsPerPage.value = options.itemsPerPage;
  fetchBooks();
};

const handleClearFilters = () => {
  filters.year = new Date().getFullYear();
  filters.month = null;
  filters.status = null;
  page.value = 1;
  fetchBooks();
};

const openBookDetail = (id) => {
  router.push(`/fiscal/libros/retenciones/${id}`);
};

const downloadBlob = async (endpoint, defaultFilename) => {
  try {
    const res = await axios.get(endpoint, { responseType: "blob" });
    const blob = new Blob([res.data]);
    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = blobUrl;
    link.setAttribute("download", defaultFilename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(blobUrl);
    toast.success("Archivo descargado exitosamente.");
  } catch (err) {
    console.error("Error al descargar archivo:", err);
    toast.error("Ocurrió un error al descargar el archivo.");
  }
};

const handleExportTxt = (item) => {
  const suffix = item.fortnight ? `_Q${item.fortnight}` : "";
  downloadBlob(`/vat-retention-books/${item.id}/export/txt`, `TxtImpuesto${item.year}${String(item.month).padStart(2, '0')}${suffix}.txt`);
};

const handleExportExcel = (item) => {
  const suffix = item.fortnight ? `_Q${item.fortnight}` : "";
  downloadBlob(`/vat-retention-books/${item.id}/export/excel`, `reporte_retenciones_iva_${item.year}_${item.month}${suffix}.xlsx`);
};

const handleExportPdf = (item) => {
  const suffix = item.fortnight ? `_Q${item.fortnight}` : "";
  downloadBlob(`/vat-retention-books/${item.id}/export/pdf`, `reporte_retenciones_iva_${item.year}_${item.month}${suffix}.pdf`);
};

const handleGeneratePeriod = async () => {
  isGenerating.value = true;
  try {
    const res = await axios.post("/vat-retention-books/generate-period", generateForm);
    toast.success("Período generado exitosamente.");
    showGenerateModal.value = false;
    await fetchBooks();
    if (res.data?.data?.id) {
      openBookDetail(res.data.data.id);
    }
  } catch (err) {
    console.error("Error al generar período:", err);
    toast.error(err.response?.data?.message || "No se pudo generar el período fiscal.");
  } finally {
    isGenerating.value = false;
  }
};

onMounted(() => {
  fetchBooks();
});
</script>

<template>
  <div class="vat-retention-books-page">
    <!-- Encabezado con Estilo Homologado del ERP -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded size="44">
            <VIcon icon="tabler-receipt-tax" size="26" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-bold mb-0">Reporte de Retenciones de IVA</h1>
            <p class="text-caption text-medium-emphasis mb-0">
              Control fiscal de retenciones de IVA a proveedores, declaración informativa SENIAT y exportación en formato TXT, Excel y PDF.
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            color="primary"
            prepend-icon="tabler-calendar-plus"
            @click="showGenerateModal = true"
          >
            Nuevo Período
          </VBtn>
          <VBtn
            icon
            variant="outlined"
            color="secondary"
            :loading="loading"
            @click="fetchBooks"
          >
            <VIcon icon="tabler-refresh" />
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Filtros de Búsqueda Homologados con AppFilterBase -->
    <PurchaseBookFilters
      v-model:year="filters.year"
      v-model:month="filters.month"
      v-model:status="filters.status"
      :loading="loading"
      @clear="handleClearFilters"
      @create-period="showGenerateModal = true"
      @refresh="fetchBooks"
    />

    <!-- Tabla Principal de Períodos -->
    <VCard class="rounded-lg">
      <VDataTableServer
        :items="books"
        :items-length="totalBooks"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        hover
        @update:options="handleTableOptions"
      >
        <template #headers>
          <tr>
            <th class="text-left font-weight-bold">PERÍODO FISCAL</th>
            <th class="text-left font-weight-bold">RANGO DE FECHAS</th>
            <th class="text-center font-weight-bold">FACTURAS</th>
            <th class="text-right font-weight-bold">TOTAL DOCS (BS)</th>
            <th class="text-right font-weight-bold">BASE IMPONIBLE (BS)</th>
            <th class="text-right font-weight-bold text-primary">IVA RETENIDO (BS)</th>
            <th class="text-center font-weight-bold">ESTADO</th>
            <th class="text-center font-weight-bold" style="width: 220px;">ACCIONES</th>
          </tr>
        </template>

        <template #item="{ item }">
          <tr>
            <td>
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-calendar" size="18" class="text-medium-emphasis" />
                <span class="font-weight-medium">{{ item.period_label }}</span>
              </div>
            </td>
            <td>
              <span class="text-caption text-medium-emphasis">
                {{ item.start_date }} al {{ item.end_date }}
              </span>
            </td>
            <td class="text-center">
              <VChip size="small" variant="tonal" color="info">
                {{ item.total_documents }} docs
              </VChip>
            </td>
            <td class="text-right">
              Bs {{ formatCurrency(item.total_document_amount) }}
            </td>
            <td class="text-right">
              Bs {{ formatCurrency(item.total_taxable_base) }}
            </td>
            <td class="text-right font-weight-bold text-primary">
              Bs {{ formatCurrency(item.total_withheld_amount) }}
            </td>
            <td class="text-center">
              <VChip
                size="small"
                :color="item.is_open ? 'success' : 'secondary'"
                variant="flat"
              >
                {{ item.is_open ? 'Abierto' : 'Cerrado' }}
              </VChip>
            </td>
            <td class="text-center">
              <div class="d-flex align-center justify-center gap-1">
                <VBtn
                  icon="tabler-eye"
                  variant="text"
                  color="primary"
                  size="small"
                  title="Ver detalle del período"
                  @click="openBookDetail(item.id)"
                />
                <VBtn
                  icon="tabler-file-code"
                  variant="text"
                  color="warning"
                  size="small"
                  title="Exportar TXT SENIAT"
                  @click="handleExportTxt(item)"
                />
                <VBtn
                  icon="tabler-file-spreadsheet"
                  variant="text"
                  color="success"
                  size="small"
                  title="Exportar Excel"
                  @click="handleExportExcel(item)"
                />
                <VBtn
                  icon="tabler-file-type-pdf"
                  variant="text"
                  color="error"
                  size="small"
                  title="Exportar PDF"
                  @click="handleExportPdf(item)"
                />
              </div>
            </td>
          </tr>
        </template>

        <template #no-data>
          <div class="pa-6 text-center text-medium-emphasis">
            <VIcon icon="tabler-receipt-off" size="36" class="mb-2" />
            <p class="mb-0">No se encontraron períodos de retención registrados.</p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Modal para Nuevo Período Manual -->
    <VDialog v-model="showGenerateModal" max-width="480">
      <VCard class="pa-4 rounded-lg">
        <VCardTitle class="px-0 pt-0 d-flex align-center justify-space-between">
          <span class="text-h6 font-weight-bold">Nuevo Período Fiscal</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="showGenerateModal = false" />
        </VCardTitle>
        <VCardText class="px-0 py-3">
          <VRow dense>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="generateForm.year"
                :items="years"
                label="Año Fiscal"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="generateForm.month"
                :items="months"
                item-title="title"
                item-value="value"
                label="Mes"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12">
              <VRadioGroup v-model="generateForm.fortnight" inline label="Quincena">
                <VRadio label="1ra Quincena (01 al 15)" :value="1" />
                <VRadio label="2da Quincena (16 al fin de mes)" :value="2" />
              </VRadioGroup>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-0 pb-0 justify-end gap-2">
          <VBtn variant="outlined" color="secondary" @click="showGenerateModal = false">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="isGenerating"
            :disabled="isGenerating"
            @click="handleGeneratePeriod"
          >
            Crear Período
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
