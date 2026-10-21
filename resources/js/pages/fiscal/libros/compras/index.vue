<script setup>
import GeneratePurchaseBookPeriodDialog from "@/components/fiscal/GeneratePurchaseBookPeriodDialog.vue";
import PurchaseBookFilters from "@/components/fiscal/PurchaseBookFilters.vue";
import PurchaseBookTable from "@/components/fiscal/PurchaseBookTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

// --- Estados Reactivos Principales ---
const loading = ref(false);
const books = ref([]);
const totalBooks = ref(0);
const page = ref(1);
const itemsPerPage = ref(15);

// Filtros Reactivos
const filters = reactive({
  year: new Date().getFullYear(),
  month: null,
  status: null,
});

// Modales
const showGenerateModal = ref(false);
const isGenerating = ref(false);

// Cargar Libros de Compras desde la API
const fetchBooks = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/purchase-books", {
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
    console.error("Error al cargar libros de compras:", error);
    toast.error("Ocurrió un error al cargar los libros de compras.");
  } finally {
    loading.value = false;
  }
};

// Manejo de Paginación desde VDataTableServer
const handleTableOptions = (options) => {
  if (options.page !== undefined) page.value = options.page;
  if (options.itemsPerPage !== undefined) itemsPerPage.value = options.itemsPerPage;
  fetchBooks();
};

// Limpieza de Filtros
const handleClearFilters = () => {
  filters.year = new Date().getFullYear();
  filters.month = null;
  filters.status = null;
  page.value = 1;
  fetchBooks();
};

// Navegar a la Vista Dedicada del Detalle del Libro
const openBookDetail = (id) => {
  router.push(`/fiscal/libros/compras/${id}`);
};

const handleExportExcel = (id) => {
  window.open(`/api/purchase-books/${id}/export/excel`, "_blank");
};

const handleExportPdf = (id) => {
  window.open(`/api/purchase-books/${id}/export/pdf`, "_blank");
};

// Generación Manual de Período
const handleGeneratePeriod = async (formData) => {
  isGenerating.value = true;
  try {
    const res = await axios.post("/purchase-books/generate-period", formData);
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
  <div class="purchase-books-page">
    <!-- Encabezado con Iconografía y Tipografía Oficial del ERP -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded size="44">
            <VIcon icon="tabler-book" size="26" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-bold mb-0">Libro de Compras</h1>
            <p class="text-caption text-medium-emphasis mb-0">
              Control tributario de facturas, retenciones de IVA (05-RET), compras exentas y alícuotas según SENIAT.
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

    <!-- Tabla Maestro Homologada con VDataTableServer -->
    <PurchaseBookTable
      :books="books"
      :loading="loading"
      :total-books="totalBooks"
      :page="page"
      :items-per-page="itemsPerPage"
      @update:options="handleTableOptions"
      @view-detail="openBookDetail"
      @export-excel="handleExportExcel"
      @export-pdf="handleExportPdf"
      @create-period="showGenerateModal = true"
    />

    <!-- Modal Generar Nuevo Período Fiscal -->
    <GeneratePurchaseBookPeriodDialog
      v-model="showGenerateModal"
      :is-generating="isGenerating"
      @submit="handleGeneratePeriod"
    />
  </div>
</template>

<style scoped>
.purchase-books-page {
  width: 100%;
}
</style>
