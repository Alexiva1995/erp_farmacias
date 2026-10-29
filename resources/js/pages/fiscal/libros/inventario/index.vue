<script setup>
import GenerateInventoryBookPeriodDialog from "@/components/fiscal/GenerateInventoryBookPeriodDialog.vue";
import InventoryBookTable from "@/components/fiscal/InventoryBookTable.vue";
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

const fetchBooks = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/inventory-books", {
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
    console.error("Error al cargar libros de inventario:", error);
    toast.error("Ocurrió un error al cargar los libros de inventario.");
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
  router.push(`/fiscal/libros/inventario/${id}`);
};

const handleExportExcel = (id) => {
  window.open(`/api/inventory-books/${id}/export/excel`, "_blank");
};

const handleExportPdf = (id) => {
  window.open(`/api/inventory-books/${id}/export/pdf`, "_blank");
};

const handleExportActa = (id) => {
  window.open(`/api/inventory-books/${id}/export/acta-desincorporacion`, "_blank");
};

const handleGeneratePeriod = async (formData) => {
  isGenerating.value = true;
  try {
    const res = await axios.post("/inventory-books/generate-period", formData);
    toast.success("Período de inventario generado exitosamente.");
    showGenerateModal.value = false;
    await fetchBooks();
    if (res.data?.data?.id) {
      openBookDetail(res.data.data.id);
    }
  } catch (err) {
    console.error("Error al generar período de inventario:", err);
    toast.error(err.response?.data?.message || "No se pudo generar el período de inventario.");
  } finally {
    isGenerating.value = false;
  }
};

onMounted(() => {
  fetchBooks();
});
</script>

<template>
  <div class="inventory-books-page">
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded size="44">
            <VIcon icon="tabler-clipboard-list" size="26" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-bold mb-0">Libro de Inventario (Art. 177 LISR)</h1>
            <p class="text-caption text-medium-emphasis mb-0">
              Registro detallado de entrada y salida de mercancías, valoración al último costo en Bs del período, y control de desincorporaciones ante el SENIAT.
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

    <PurchaseBookFilters
      v-model:year="filters.year"
      v-model:month="filters.month"
      v-model:status="filters.status"
      :loading="loading"
      @clear="handleClearFilters"
      @create-period="showGenerateModal = true"
      @refresh="fetchBooks"
    />

    <InventoryBookTable
      :books="books"
      :loading="loading"
      :total-items="totalBooks"
      :page="page"
      :items-per-page="itemsPerPage"
      @update:options="handleTableOptions"
      @view-detail="openBookDetail"
      @export-excel="handleExportExcel"
      @export-pdf="handleExportPdf"
      @export-acta="handleExportActa"
    />

    <GenerateInventoryBookPeriodDialog
      v-model="showGenerateModal"
      :is-generating="isGenerating"
      @submit="handleGeneratePeriod"
    />
  </div>
</template>

<style scoped>
.inventory-books-page {
  width: 100%;
}
</style>
