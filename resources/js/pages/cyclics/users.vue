<script setup>
import InventoryCountDialog from "@/components/dialogs/InventoryCountDialog.vue";
import LotDistributionModal from "@/components/dialogs/LotDistributionModal.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import InvoiceToCountTable from "@/components/InvoiceToCountTable.vue";
import SalesToCountTable from "@/components/SalesToCountTable.vue";
import ProductTable from "@/components/ProductTable.vue";
import { useDataTable } from "@/composables/useDataTable";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref, computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

// ── Pestaña activa ──────────────────────────────────────────────────────────
const activeTab = ref("products");

// ── Filtros compartidos entre las 3 tablas ──────────────────────────────────
const filters = reactive({
  q: "",
  laboratoryId: null,
  originId: null,
  hasStock: null,
  startDate: null,
  endDate: null,
  isStrictSearch: false,
});

// ── Fuentes de datos ────────────────────────────────────────────────────────
const {
  items: products,
  totalItems: totalProduct,
  loading: productLoading,
  options: productOptions,
  fetchData: fetchProducts,
  updateTableOptions: updateProductTableOptions,
} = useDataTable("/inventory/products", filters);

const {
  items: invoiceProducts,
  totalItems: totalInvoiceProducts,
  loading: invoiceLoading,
  options: invoiceOptions,
  fetchData: fetchInvoiceProducts,
  updateTableOptions: updateInvoiceTableOptions,
} = useDataTable("/inventory/count/invoice-details-to-count", filters);

const {
  items: salesProducts,
  totalItems: totalSalesProducts,
  loading: salesLoading,
  options: salesOptions,
  fetchData: fetchSalesProducts,
  updateTableOptions: updateSalesTableOptions,
} = useDataTable("/inventory/count/sales-details-to-count", filters);

// ── Datos auxiliares ────────────────────────────────────────────────────────
const laboratories  = ref([]);
const origins       = ref([]);
const locations     = ref([]);
const isLoadingFilters = ref(false);
const hasActiveCycle   = ref(false);

// ── Estado de cuota diaria por operador ────────────────────────────────────
const quotaStatus = ref({
  is_active: false,
  counted: 0,
  total: 50,
  tier: 1,
  can_request_more: false,
});
const isRequestingMore = ref(false);

const fetchUserQuota = async () => {
  try {
    const { data } = await axios.get("/inventory/user-quota-status");
    quotaStatus.value = data;
  } catch (error) {
    console.error("Error al obtener estado de cuota:", error);
  }
};

const handleRequestMoreProducts = async () => {
  if (isRequestingMore.value) return;
  isRequestingMore.value = true;
  try {
    const { data } = await axios.post("/inventory/request-more-quota");
    toast.success(data.message || "¡Nuevo lote cargado exitosamente!");
    await Promise.all([
      fetchUserQuota(),
      fetchProducts(),
    ]);
  } catch (error) {
    toast.error(error.response?.data?.message || "No se pudo solicitar más productos.");
  } finally {
    isRequestingMore.value = false;
  }
};

// ── Modal de conteo — compartido para las 3 pestañas ───────────────────────
const isCountDialogVisible        = ref(false);
const currentProduct              = ref({});
const countType                   = ref("product");

// ── Modal de lotes — compartido (modo simple) ───────────────────────────────
const showLotDistributionModal    = ref(false);
const itemForLotDistribution      = ref(null);
const targetQuantityForDistribution = ref(0);
const pendingCountData            = ref(null);

// ── Store ───────────────────────────────────────────────────────────────────
const brandingStore = useBrandingStore();
const isSimpleMode  = computed(() => brandingStore.settings?.cyclic_inventory_mode === "simple");

// ── Carga de selects y ciclo activo ────────────────────────────────────────
const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labRes, originRes, cycleRes, locRes] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/inventory/cycle/active"),
      axios.get("/locations"),
    ]);

    laboratories.value = labRes.data;
    origins.value      = originRes.data;
    locations.value    = locRes.data?.data ?? locRes.data ?? [];

    if (cycleRes.data.success) {
      hasActiveCycle.value = cycleRes.data.has_active_cycle;
      if (!hasActiveCycle.value) {
        toast.warning("No existe un ciclo de inventario activo. Los conteos no podrán ser registrados.");
      }
    }
  } catch {
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

onMounted(() => Promise.all([
  fetchSelectOptions(),
  fetchUserQuota(),
  fetchProducts(),
  fetchInvoiceProducts(),
  fetchSalesProducts(),
]));

// ── Limpiar filtros ─────────────────────────────────────────────────────────
const handleClearFilters = () => {
  filters.q             = "";
  filters.laboratoryId  = null;
  filters.originId      = null;
  filters.hasStock      = null;
  filters.startDate     = null;
  filters.endDate       = null;
  filters.isStrictSearch = false;
  [productOptions, invoiceOptions, salesOptions].forEach(opt => {
    opt.sortBy  = undefined;
    opt.orderBy = undefined;
  });
};

// ── Ordenamiento global ─────────────────────────────────────────────────────
const handleSort = ({ key, order }) => {
  [productOptions, invoiceOptions, salesOptions].forEach(opt => {
    opt.sortBy  = key;
    opt.orderBy = order;
  });
};

// ── Abrir modal de conteo ───────────────────────────────────────────────────
const handleCountProduct = (product, type) => {
  if (!hasActiveCycle.value) {
    toast.error("No se puede realizar el conteo. No existe un ciclo de inventario activo.");
    return;
  }
  countType.value    = type;
  currentProduct.value = { ...product };
  isCountDialogVisible.value = true;
};

// ── Envío del conteo ────────────────────────────────────────────────────────
const refetchByType = async () => {
  await fetchUserQuota();
  if (countType.value === "invoice") return fetchInvoiceProducts();
  if (countType.value === "sales")   return fetchSalesProducts();
  return fetchProducts();
};

const sendCountRequest = async (endpoint, payload) => {
  try {
    const { data } = await axios.post(endpoint, payload);
    if (data.success) {
      toast.success(data.message || "Conteo registrado exitosamente");
      isCountDialogVisible.value = false;
      await refetchByType();
    } else {
      toast.error(data.message || "Error al registrar el conteo");
    }
  } catch (error) {
    if (error.response?.status === 422) {
      const msgs = Object.values(error.response.data.errors).flat().join(", ");
      toast.error(`Errores de validación: ${msgs}`);
    } else if (error.response?.status === 400) {
      toast.error(error.response.data.message);
    } else {
      toast.error("Hubo un error al registrar el conteo.");
    }
  }
};

// ── Guardar conteo desde el diálogo ────────────────────────────────────────
const handleSaveCount = async (countData) => {
  const productId = currentProduct.value.id;
  const endpoint =
    countType.value === "invoice" ? `/inventory/count/invoice-count/${productId}` :
    countType.value === "sales"   ? `/inventory/count/sales-count/${productId}`   :
                                    `/inventory/count/${productId}`;

  const systemQuantity = Number(currentProduct.value.stock_calculado ?? currentProduct.value.stock ?? 0);
  const payload = {
    counted_quantity: countData.countedQuantity,
    system_quantity:  systemQuantity,
    discrepancy:      countData.countedQuantity - systemQuantity,
    ...(countData.allowWithoutBarcode
      ? { allow_without_barcode: true }
      : { barcode: countData.barcode }),
  };

  // Modo simple con lotes → derivar al modal de distribución
  const enableLots = brandingStore.settings?.enable_lots ?? true;
  if (enableLots && isSimpleMode.value && countType.value === "product") {
    isCountDialogVisible.value      = false;
    pendingCountData.value          = { endpoint, payload };
    itemForLotDistribution.value    = currentProduct.value;
    targetQuantityForDistribution.value = countData.countedQuantity;
    showLotDistributionModal.value  = true;
    return;
  }

  await sendCountRequest(endpoint, payload);
};

// ── Confirmación de distribución de lotes ─────────────────────────────────
const handleLotsDistributed = async ({ updatedLots, newLots }) => {
  if (!pendingCountData.value) {
    toast.error("Error: no hay datos de conteo pendientes.");
    return;
  }
  const { endpoint, payload } = pendingCountData.value;
  try {
    await sendCountRequest(endpoint, { ...payload, updated_lots: updatedLots, new_lots: newLots });
  } finally {
    showLotDistributionModal.value  = false;
    itemForLotDistribution.value    = null;
    pendingCountData.value          = null;
  }
};
</script>

<template>
  <div class="inventory-users-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">

      <!-- Filtros -->
      <ProductFilters
        v-model:searchQuery="filters.q"
        v-model:selectedLaboratory="filters.laboratoryId"
        v-model:selectedOrigin="filters.originId"
        v-model:stockStatusFilter="filters.hasStock"
        v-model:startDate="filters.startDate"
        v-model:endDate="filters.endDate"
        v-model:isStrictSearch="filters.isStrictSearch"
        :laboratories="laboratories"
        :origins="origins"
        :loading="isLoadingFilters"
        mode="inventory"
        class="py-1"
        @clear="handleClearFilters"
        @sort="handleSort"
      />

      <!-- Card con pestañas — mismo patrón que cyclic.vue -->
      <VCard variant="outlined" class="rounded-lg bg-surface">
        <div class="d-flex align-center justify-space-between flex-wrap gap-2 pr-3">
          <VTabs v-model="activeTab" color="primary" align-tabs="start">

            <VTab value="products" class="text-none font-weight-medium">
              <VIcon start icon="mdi-package-variant-closed" />
              Productos por Contar
              <VChip size="x-small" class="ml-2" color="primary" variant="tonal">
                {{ totalProduct }}
              </VChip>
            </VTab>

            <VTab value="invoices" class="text-none font-weight-medium">
              <VIcon start icon="mdi-file-document-outline" />
              Por Factura
              <VChip size="x-small" class="ml-2" color="info" variant="tonal">
                {{ totalInvoiceProducts }}
              </VChip>
            </VTab>

            <VTab value="sales" class="text-none font-weight-medium">
              <VIcon start icon="mdi-cart-outline" />
              Por Punto de Venta
              <VChip size="x-small" class="ml-2" color="success" variant="tonal">
                {{ totalSalesProducts }}
              </VChip>
            </VTab>

          </VTabs>

          <!-- Indicador de Cuota Diaria y Botón Solicitar Más -->
          <div v-if="quotaStatus.is_active && activeTab === 'products'" class="d-flex align-center gap-2 py-1">
            <VChip
              :color="quotaStatus.counted >= quotaStatus.total ? 'success' : 'primary'"
              variant="flat"
              size="small"
              class="font-weight-bold"
            >
              <VIcon start icon="mdi-counter" size="16" />
              {{ quotaStatus.counted }}/{{ quotaStatus.total }}
            </VChip>

            <VChip
              v-if="quotaStatus.tier > 1"
              color="purple"
              variant="tonal"
              size="small"
              class="font-weight-bold"
            >
              <VIcon start icon="mdi-fire" size="16" />
              Nivel {{ quotaStatus.tier }} (+{{ quotaStatus.tier >= 3 ? 4 : 2 }} pts/conteo)
            </VChip>

            <VBtn
              v-if="quotaStatus.can_request_more"
              color="success"
              variant="elevated"
              size="small"
              class="text-none font-weight-bold"
              :loading="isRequestingMore"
              @click="handleRequestMoreProducts"
            >
              <VIcon start icon="mdi-plus-box-multiple" />
              Solicitar más (+{{ quotaStatus.tier >= 2 ? 4 : 2 }} pts)
            </VBtn>
          </div>
        </div>

        <VDivider />

        <VWindow v-model="activeTab">

          <!-- Pestaña: Productos -->
          <VWindowItem value="products">
            <ProductTable
              :products="products"
              :loading="productLoading"
              :total-product="totalProduct"
              :items-per-page="productOptions.itemsPerPage"
              :page="productOptions.page"
              mode="inventory"
              @update:options="updateProductTableOptions"
              @count-product="(p) => handleCountProduct(p, 'product')"
            />
          </VWindowItem>

          <!-- Pestaña: Facturas -->
          <VWindowItem value="invoices">
            <InvoiceToCountTable
              :products="invoiceProducts"
              :loading="invoiceLoading"
              :total-product="totalInvoiceProducts"
              :items-per-page="invoiceOptions.itemsPerPage"
              :page="invoiceOptions.page"
              mode="inventory"
              @update:options="updateInvoiceTableOptions"
              @count-product="(p) => handleCountProduct(p, 'invoice')"
            />
          </VWindowItem>

          <!-- Pestaña: Punto de Venta -->
          <VWindowItem value="sales">
            <SalesToCountTable
              :products="salesProducts"
              :loading="salesLoading"
              :total-product="totalSalesProducts"
              :items-per-page="salesOptions.itemsPerPage"
              :page="salesOptions.page"
              mode="inventory"
              @update:options="updateSalesTableOptions"
              @count-product="(p) => handleCountProduct(p, 'sales')"
            />
          </VWindowItem>

        </VWindow>
      </VCard>

    </div>

    <!-- Modal de conteo — único, compartido entre las 3 pestañas -->
    <InventoryCountDialog
      v-model="isCountDialogVisible"
      :product="currentProduct"
      @save="handleSaveCount"
    />

    <!-- Modal de lotes — único, solo en modo simple -->
    <LotDistributionModal
      v-model="showLotDistributionModal"
      :product-name="itemForLotDistribution?.name || 'Producto'"
      :lots="itemForLotDistribution?.lots || []"
      :target-quantity="targetQuantityForDistribution"
      :locations="locations"
      mode="adjustment"
      @save="handleLotsDistributed"
    />
  </div>
</template>
