<script setup>
import ExpirationsFilters from "@/components/ExpirationsFilters.vue";
import ExpirationsTable from "@/components/ExpirationsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const lots = ref([]);
const totalLots = ref(0);
const loading = ref(false);
const selectedLots = ref([]);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("expiration_date");
const orderBy = ref("asc");

const searchQuery = ref("");

const fetchLots = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/products/expirations", { params });
    lots.value = response.data.data;
    totalLots.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los lotes por vencer:", error);
    toast.error("Error al obtener la lista de lotes.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchLots(), 300);
  },
  { deep: true }
);

watch([searchQuery], () => {
  page.value = 1;
});

onMounted(() => {
  fetchLots();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleExpireLot = async (lotToExpire) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Vas a marcar como caducado el lote Nº ${lotToExpire.lot_number} del producto "${lotToExpire.product.name}".`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Sí, marcar caducado",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.put(`/lots/${lotToExpire.id}/expire`);
      toast.success("Lote marcado como caducado con éxito.");
      await fetchLots();
    } catch (error) {
      console.error("Error al caducar el lote:", error);
      toast.error("No se pudo caducar el lote. Inténtalo de nuevo.");
    }
  }
};

const handleApplyDiscount = async (lot) => {
  const { value: discount } = await Swal.fire({
    title: "Aplicar Descuento",
    input: "number",
    inputLabel: `Ingrese el porcentaje de descuento para el lote #${lot.lot_number}`,
    inputPlaceholder: "Ej: 15",
    inputAttributes: { min: 1, max: 100 },
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Aplicar",
    reverseButtons: true,
    inputValidator: (value) => {
      if (!value || value < 1 || value > 100)
        return "Debes ingresar un porcentaje válido (1-100)";
    },
  });

  if (discount) {
    try {
      await axios.post(`/lots/${lot.id}/apply-discount`, { discount });
      toast.success(`Descuento del ${discount}% aplicado.`);
    } catch (error) {
      console.error("Error al aplicar el descuento:", error);
      toast.error("No se pudo aplicar el descuento.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
};

const handleExpireSelected = async () => {
  const selectedCount = selectedLots.value.length;
  if (selectedCount === 0) {
    toast.fire({
      icon: "info",
      title: "Por favor, selecciona al menos un lote.",
    });
    return;
  }

  const result = await Swal.fire({
    title: `¿Estás seguro de caducar ${selectedCount} lotes?`,
    text: "Esta acción marcará todos los lotes seleccionados como caducados. No podrás revertirlo.",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Sí, caducar todos",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post("/lots/expire-multiple", {
        lot_ids: selectedLots.value,
      });

      toast.success(response.data.message || "Lotes caducados con éxito.");
      selectedLots.value = [];
      await fetchLots();
    } catch (error) {
      console.error("Error al caducar lotes seleccionados:", error);
      const errorMessage =
        error.response?.data?.message ||
        "Ocurrió un error al procesar la solicitud.";
      toast.error(errorMessage);
    }
  }
};
</script>

<template>
  <div>
    <ExpirationsFilters
      v-model:searchQuery="searchQuery"
      @clear="handleClearFilters"
    />
    <VCard class="mb-6" v-if="selectedLots.length > 0">
      <VCardText class="d-flex align-center justify-space-between">
        <div>
          <span class="font-weight-medium">{{ selectedLots.length }}</span>
          lote(s) seleccionado(s)
        </div>

        <VBtn
          color="warning"
          prepend-icon="tabler-calendar-off"
          @click="handleExpireSelected"
        >
          Caducar Seleccionados
        </VBtn>
      </VCardText>
    </VCard>
    <ExpirationsTable
      v-model="selectedLots"
      :lots="lots"
      :loading="loading"
      :total-lots="totalLots"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @apply-discount="handleApplyDiscount"
      @expire-lot="handleExpireLot"
    />
  </div>
</template>
