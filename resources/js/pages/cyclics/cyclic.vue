<!-- cyclic.vue -->
<script setup>
import InventoryCorrectionModal from "@/components/dialogs/InventoryCorrectionModal.vue";
import InventoryCountModal from "@/components/dialogs/InventoryCountModal.vue";
import InventoryCycleFilters from "@/components/InventoryCycleFilters.vue";
import ProductCyclicTable from "@/components/ProductCyclicTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

// --- Estado para la tabla y paginación ---
const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref(); // Se manejará por el filtro o la tabla
const orderBy = ref();

// --- 2. Estado para los filtros ---
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const laboratories = ref([]);
const isLoadingFilters = ref(false);

// --- Estado para el modal ---
const showCountModal = ref(false);
const selectedProduct = ref(null);

// --- Estado para el modal de corrección ---
const showCorrectionModal = ref(false);
const selectedProductForCorrection = ref(null);

const showLotDistributionModal = ref(false);
const productForLotDistribution = ref(null);
const targetQuantityForDistribution = ref(0);

const pendingAction = ref({ type: null, data: {} });

// 3. Cargar datos para los selects de los filtros
const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const labResponse = await axios.get("/laboratories");

    laboratories.value = labResponse.data.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");

    // **Ajuste 2: Asegurar que laboratories es un array en caso de error**
    laboratories.value = [];
  } finally {
    isLoadingFilters.value = false;
  }
};

// 4. Actualizar la llamada a la API para incluir los filtros
const fetchProducts = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };

  // Limpiar parámetros nulos o vacíos
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    // Asegúrate de que esta es la ruta correcta definida en tu api.php
    const response = await axios.get("/products/count", {
      params,
    });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los conteos:", error);
  } finally {
    loading.value = false;
  }
};

// 5. Observar todos los cambios para recargar los datos
let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    searchQuery,
    selectedLaboratory,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

// Reiniciar a la página 1 si cambia un filtro (mejora la UX)
watch([searchQuery, selectedLaboratory, startDate, endDate], () => {
  page.value = 1;
});

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});

// --- Lógica de la tabla y modales ---

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  // Solo actualiza el orden si el filtro de ordenamiento no está activo
  if (!sortBy.value && options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  }
};

const openCountModal = (product) => {
  selectedProduct.value = product;
  if (selectedProduct.value) {
    showCountModal.value = true;
  }
};

const handleCountProcessed = async () => {
  console.log("Actualizando la tabla después del modal");
  fetchProducts();
};

// --- 6. Manejadores para los eventos del componente de filtros ---
const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  startDate.value = null;
  endDate.value = null;
  // Opcional: reiniciar también el ordenamiento
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleExport = async (format) => {
  // Lógica de exportación similar a la de productos, pero apuntando a una nueva ruta
  toast.info(`Exportando a ${format}... (funcionalidad pendiente en backend)`);
  // Aquí iría el código de llamada a axios a la ruta '/inventory-cycle/export'
};

const callProcessApi = async (productId, payload) => {
  try {
    const response = await axios.post(
      `/products/count/${productId}/process`,
      payload
    );
    if (response.data.success) {
      toast.success(response.data.message);
      await fetchProducts(); // Recargar la tabla
    } else {
      toast.error(response.data.message);
    }
  } catch (error) {
    console.error("Error al procesar la acción:", error);
    const errorMessage =
      error.response?.data?.message || "Hubo un error al procesar la acción.";
    toast.error(errorMessage);
  } finally {
    // Limpiar estados de modales y pendientes
    showCorrectionModal.value = false;
    showLotDistributionModal.value = false;
    selectedProductForCorrection.value = null;
    productForLotDistribution.value = null;
    pendingAction.value = { type: null, data: {} };
  }
};
// --- 7. Nuevos manejadores para aprobar/rechazar productos ---
const handleApproveProduct = async (product) => {
  // La discrepancia se calcula entre lo contado y lo que el sistema esperaba.
  const discrepancy = product.discrepancy;
  // CONDICIÓN CLAVE:
  // Si (discrepancia NO es cero) Y (el producto TIENE lotes) Y (la lista de lotes NO está vacía)
  if (
    discrepancy !== 0 &&
    product.product.lots &&
    product.product.lots.length > 0
  ) {
    // ----> CORRECTO: Abre el modal de distribución
    productForLotDistribution.value = product.product;
    targetQuantityForDistribution.value = product.counted_quantity; // El objetivo es la cantidad contada
    pendingAction.value = { type: "approve" };
    showLotDistributionModal.value = true;
  } else {
    // ----> CORRECTO: Procesa directamente si no hay discrepancia o no hay lotes
    await callProcessApi(product.id, { action: "approve" });
  }
};

const handleRejectProduct = async (product) => {
  // Esto no cambia, sigue abriendo el modal de corrección
  selectedProductForCorrection.value = product;
  showCorrectionModal.value = true;
};

const handleCorrectionProcessed = async (correctionData) => {
  const product = selectedProductForCorrection.value;
  // Se calcula la nueva discrepancia con el valor corregido
  const newDiscrepancy =
    correctionData.correctedQuantity - product.system_quantity;

  const payload = {
    action: "reject",
    corrected_quantity: correctionData.correctedQuantity,
    original_quantity: correctionData.originalQuantity,
  };

  if (
    newDiscrepancy !== 0 &&
    product.product.lots &&
    product.product.lots.length > 0
  ) {
    // ----> CORRECTO: Abre el modal de distribución
    productForLotDistribution.value = product.product;
    targetQuantityForDistribution.value = correctionData.correctedQuantity; // El objetivo es la cantidad CORREGIDA
    pendingAction.value = { type: "reject", data: payload };
    showCorrectionModal.value = false;
    showLotDistributionModal.value = true;
  } else {
    // ----> CORRECTO: Procesa directamente si no hay nueva discrepancia o no hay lotes
    await callProcessApi(product.id, payload);
  }
};

// 4. Nuevo manejador para cuando se guarda la distribución de lotes
const handleLotsDistributed = async (modifiedLot) => {
  // <-- AJUSTE CLAVE: Recibe un solo objeto
  const product = productForLotDistribution.value;
  let finalPayload = {};

  if (pendingAction.value.type === "approve") {
    // El payload ahora contiene una clave 'lot' (singular) con el objeto recibido
    finalPayload = { action: "approve", lot: modifiedLot };
  } else if (pendingAction.value.type === "reject") {
    // Combina los datos de la corrección con los del lote modificado
    finalPayload = { ...pendingAction.value.data, lot: modifiedLot };
  }

  // El resto de la lógica para encontrar el registro de conteo y llamar a la API
  // puede permanecer igual, ya que se basa en `finalPayload`.

  let originalCountRecord = null;
  if (selectedProductForCorrection.value) {
    originalCountRecord = selectedProductForCorrection.value;
  } else {
    originalCountRecord = products.value.find(
      (p) => p.product_id === product.id
    );
  }

  if (originalCountRecord) {
    await callProcessApi(originalCountRecord.id, finalPayload);
  } else {
    toast.error(
      "Error crítico: No se pudo encontrar el registro del conteo original."
    );
  }
};
</script>

<template>
  <div>
    <!-- Filtros y Tabla (sin cambios) -->
    <InventoryCycleFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      :laboratories="laboratories"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @export="handleExport"
      @sort="handleSort"
    />

    <ProductCyclicTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @product-click="openCountModal"
      @approve-product="handleApproveProduct"
      @reject-product="handleRejectProduct"
    />

    <!-- Modales existentes (sin cambios en su declaración) -->
    <InventoryCountModal
      v-if="showCountModal && selectedProduct"
      v-model="showCountModal"
      :product="selectedProduct"
      @count-processed="handleCountProcessed"
    />

    <InventoryCorrectionModal
      v-if="showCorrectionModal && selectedProductForCorrection"
      v-model="showCorrectionModal"
      :product="selectedProductForCorrection"
      @correction-processed="handleCorrectionProcessed"
    />

    <!-- 5. Añadir el nuevo modal al template -->
    <LotDistributionModal
      v-if="showLotDistributionModal && productForLotDistribution"
      v-model="showLotDistributionModal"
      :product-name="productForLotDistribution.name"
      :lots="productForLotDistribution.lots"
      :target-quantity="targetQuantityForDistribution"
      @save="handleLotsDistributed"
    />
  </div>
</template>
