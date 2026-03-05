<script setup>
import PurchaseOrderEditDialog from "@/components/dialogs/PurchaseOrderEditDialog.vue";
import PurchaseOrderRequestedProducts from "@/components/dialogs/PurchaseOrderRequestedProducts.vue";
import PurchaseOrderShowDialog from "@/components/dialogs/PurchaseOrderShowDialog.vue";
import PurchaseOrdersFilter from "@/components/PurchaseOrdersFilter.vue";
import PurchaseOrdersTable from "@/components/PurchaseOrdersTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const currentPurchaseOrder = ref({});
const purchaseOrders = ref([]);
const suppliers = ref([]);
const selectedSupplier = ref(null);
const loading = ref(false);
const formErrors = ref({});

const page = ref(1);
const itemsPerPage = ref(10);
const totalPurchaseOrders = ref(0);
const stats = ref({
  total_orders: 0,
  total_amount: 0,
  pending_orders: 0,
});

const isEditDialogVisible = ref(false);
const isShowDialogVisible = ref(false);
const isShowRequestedProductsVisible = ref(false);

const { isAdmin } = useAuthStore();

const fetchSuppliers = async () => {
  try {
    const response = await axios.get("/available-suppliers");
    suppliers.value = response.data.data;
  } catch (error) {
    console.error("Hubo un error al obtener los proveedores:", error);
    toast.error("Error al obtener los proveedores.");
  } finally {
    loading.value = false;
  }
};

const fetchStats = async () => {
  try {
    const params = {
      selectedSupplier: selectedSupplier.value,
    };
    const { data } = await axios.get("/suppliers/purchase-orders/stats", { params });
    stats.value = data.data;
  } catch (error) {
    console.error("Error al obtener estadísticas:", error);
  }
};

const fetchPurchaseOrders = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    selectedSupplier: selectedSupplier.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const { data } = await axios.get("/suppliers/purchase-orders", { params });
    purchaseOrders.value = data.data.data;
    totalPurchaseOrders.value = data.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las órdenes de compra:", error);
    toast.error("Error al obtener las órdenes de compra.");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSuppliers();
  fetchPurchaseOrders();
  fetchStats();
});

let debounceTimer;
watch(
  [page, itemsPerPage, selectedSupplier],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchPurchaseOrders();
      fetchStats();
    }, 300);
  },
  { deep: true }
);

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const handleClearFilters = () => {
  selectedSupplier.value = null;
};

const handleEditPurchaseOrder = (purchaseOrder) => {
  currentPurchaseOrder.value = { ...purchaseOrder };
  isEditDialogVisible.value = true;
};

const handleShowPurchaseOrder = (purchaseOrder) => {
  currentPurchaseOrder.value = { ...purchaseOrder };
  isShowDialogVisible.value = true;
};

const handleShowRequestedProducts = (purchaseOrder) => {
  currentPurchaseOrder.value = { ...purchaseOrder };
  isShowRequestedProductsVisible.value = true;
};

const handleDeletePurchaseOrderDetail = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de este producto para esta orden de compra!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/suppliers/purchase-orders/details/${id}`);
      toast.success("Detalle de Orden de compra eliminado correctamente.");
      isEditDialogVisible.value = false;
      currentPurchaseOrder.value = false;
      fetchPurchaseOrders();
    } catch (error) {
      console.error(
        "Hubo un error al eliminar el detalle de la orden de compra:",
        error
      );
      toast.error("Error al eliminar el detalle de la orden de compra.");
    }
  }
};

const handleDeletePurchaseOrder = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de esta orden de compra!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    try {
      const { data } = await axios.delete(`/suppliers/purchase-orders/${id}`);

      if (data.data.status === "ok") {
        toast.success("Orden de compra eliminada correctamente.");
        fetchPurchaseOrders();
      } else {
        toast.error(
          `No se pudo eliminar la orden de compra ${currentPurchaseOrder.value.id}`
        );
      }
    } catch (error) {
      console.error(
        "Hubo un error al eliminar el detalle de la orden de compra:",
        error
      );
      toast.error("Error al eliminar la orden de compra.");
    }
  }
};

const handleClearErrors = () => {
  formErrors.value = {};
};

const handleSaveDetails = async (detailsData) => {
  try {
    const { data } = await axios.put(
      `/suppliers/purchase-orders/${currentPurchaseOrder.value.id}`,
      detailsData
    );
    if (data.status === "ok") {
      toast.success(
        `Se actualizaron ${data.count} productos de la orden de compra ${currentPurchaseOrder.value.id}`
      );
    } else {
      toast.error(
        `No se pudo actualizar la orden de compra ${currentPurchaseOrder.value.id}`
      );
    }
    isEditDialogVisible.value = false;
    currentPurchaseOrder.value = false;
    fetchPurchaseOrders();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    }
  }
};
</script>

<template>
  <VRow class="match-height">
    <!-- ── Estadísticas ───────────────────────────────────────────── -->
    <VCol cols="12" md="4">
      <VCard>
        <VCardText class="d-flex align-center">
          <VAvatar
            rounded
            variant="tonal"
            color="primary"
            icon="tabler-clipboard-list"
            size="44"
            class="me-4"
          />
          <div>
            <div class="text-body-1">Total Órdenes</div>
            <div class="text-h5 font-weight-bold">{{ stats.total_orders }}</div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12" md="4">
      <VCard>
        <VCardText class="d-flex align-center">
          <VAvatar
            rounded
            variant="tonal"
            color="success"
            icon="tabler-currency-dollar"
            size="44"
            class="me-4"
          />
          <div>
            <div class="text-body-1">Monto Total</div>
            <div class="text-h5 font-weight-bold">
              {{
                Intl.NumberFormat("es-VE", {
                  style: "currency",
                  currency: "USD",
                }).format(stats.total_amount)
              }}
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12" md="4">
      <VCard>
        <VCardText class="d-flex align-center">
          <VAvatar
            rounded
            variant="tonal"
            color="warning"
            icon="tabler-alert-circle"
            size="44"
            class="me-4"
          />
          <div>
            <div class="text-body-1">Órdenes Pendientes</div>
            <div class="text-h5 font-weight-bold text-warning">
              {{ stats.pending_orders }}
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <PurchaseOrderRequestedProducts
        v-show="isShowRequestedProductsVisible"
        v-model="isShowRequestedProductsVisible"
        :purchaseOrder="currentPurchaseOrder"
      />

      <PurchaseOrderEditDialog
        v-show="isEditDialogVisible"
        v-model="isEditDialogVisible"
        :purchaseOrder="currentPurchaseOrder"
        :errors="formErrors"
        @clearErrors="handleClearErrors"
        @delete-detail="handleDeletePurchaseOrderDetail"
        @save="handleSaveDetails"
      />

      <PurchaseOrderShowDialog
        v-show="isShowDialogVisible"
        v-model="isShowDialogVisible"
        :purchaseOrder="currentPurchaseOrder"
      />

      <div class="mt-4">
        <PurchaseOrdersFilter
          v-model:selectedSupplier="selectedSupplier"
          :suppliers="suppliers"
          @clear="handleClearFilters"
        />
      </div>

      <PurchaseOrdersTable
        :purchaseOrders="purchaseOrders"
        :loading="loading"
        :total-purchaseOrders="totalPurchaseOrders"
        :items-per-page="itemsPerPage"
        :page="page"
        :is-admin="isAdmin"
        @update:options="updateTableOptions"
        @edit-purchaseOrder="handleEditPurchaseOrder"
        @delete-purchaseOrder="handleDeletePurchaseOrder"
        @show-purchaseOrder="handleShowPurchaseOrder"
        @show-requested-products="handleShowRequestedProducts"
      />
    </VCol>
  </VRow>
</template>
