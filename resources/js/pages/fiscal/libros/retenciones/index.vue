<script setup>
import PurchaseBookFilters from "@/components/fiscal/PurchaseBookFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref, watch } from "vue";
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

watch(
  filters,
  () => {
    page.value = 1;
    fetchBooks();
  },
  { deep: true }
);

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
      </div>
    </VCard>

    <!-- Filtros de Búsqueda Homologados con AppFilterBase -->
    <PurchaseBookFilters
      v-model:year="filters.year"
      v-model:month="filters.month"
      v-model:status="filters.status"
      :loading="loading"
      :show-add="false"
      @clear="handleClearFilters"
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
  </div>
</template>
