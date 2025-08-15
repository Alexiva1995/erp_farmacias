<script setup>
import SupplierFilters from "@/components/SupplierFilters.vue";
import SupplierTable from "@/components/SupplierTable.vue";
import PaymentRuleEditDialog from "@/components/dialogs/PaymentRuleEditDialog.vue";
import SupplierDiscountEditDialog from "@/components/dialogs/SupplierDiscountEditDialog.vue";
import SupplierDiscountRulesDialog from "@/components/dialogs/SupplierDiscountRulesDialog.vue";
import SupplierEditDialog from "@/components/dialogs/SupplierEditDialog.vue";
import SupplierLaboratoryEditDialog from "@/components/dialogs/SupplierLaboratoryEditDialog.vue";
import SupplierPendingInvoicesDialog from "@/components/dialogs/SupplierPendingInvoicesDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const suppliers = ref([]);
const totalSupplier = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const searchQuery = ref("");

const laboratories = ref([]);
const laboratoryLinks = ref([]);
const pendingInvoices = ref({});
const discountRules = ref([]);
const paymentRules = ref([]);
const supplierDiscount = ref([]);

const isLoadingFilters = ref(false);

const currentSupplier = ref({});
const supplierFormErrors = ref({});
const isEditDialogVisible = ref(false);
const isPaymentRuleDialogVisible = ref(false);
const isSupplierLaboratoryDialogVisible = ref(false);
const isPendingInvoicesDialogVisible = ref(false);
const isSupplierDiscountRuleDialogVisible = ref(false);
const isSupplierDiscountDialogVisible = ref(false);

const checkingApiSupplierId = ref(null);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse] = await Promise.all([axios.get("/laboratories")]);
    laboratories.value = labResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchSuppliers = async () => {
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
    const response = await axios.get("/suppliers", { params });
    suppliers.value = response.data.data;
    totalSupplier.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los proveedores:", error);
    toast.error("Error al obtener los proveedores.");
  } finally {
    loading.value = false;
  }
};

const fetchLaboratoryLinks = async () => {
  const { data } = await axios.get(`/suppliers/${currentSupplier.value.id}/laboratories`);
  laboratoryLinks.value = data.laboratory_links;
};

const fetchDiscountRules = async () => {
  try {
    const { data } = await axios.get(
      `/supplier-laboratories/${currentSupplier.value.id}/discount-rules`
    );
    discountRules.value = data.discount_rules;
  } catch (error) {
    toast.error("Error al cargar las reglas de descuento");
  } finally {
    loading.value = false;
  }
};

const fetchPendingInvoices = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(
      `/suppliers/${currentSupplier.value.id}/pending-invoices`
    );
    pendingInvoices.value = data.pending_invoices;
  } catch (error) {
    toast.error("Error al cargar facturas pendientes");
  } finally {
    loading.value = false;
  }
};

const fetchPaymentRules = async () => {
  try {
    const { data } = await axios.get(
      `/suppliers/${currentSupplier.value.id}/payment-rules`
    );
    paymentRules.value = data.payment_rules;
  } catch (error) {
    toast.error("Error al cargar las reglas de pronto pago");
  } finally {
    loading.value = false;
  }
};

const fetchSupplierDiscount = async () => {
  try {
    const { data } = await axios.get(`/suppliers/${currentSupplier.value.id}/discounts`);
    supplierDiscount.value = data.supplier_discount;
  } catch (error) {
    toast.error("Error al cargar los descuentos");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSelectOptions();
  fetchSuppliers();
});

const handleClearFilters = () => {
  searchQuery.value = "";
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleAddSupplier = () => {
  currentSupplier.value = {};
  supplierFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleSaveSupplier = async (supplierFormData) => {
  const isNewSupplier = !currentSupplier.value.id;
  const url = isNewSupplier ? "/suppliers" : `/suppliers/${currentSupplier.value.id}`;

  const payloadKeys = Object.keys(supplierFormData);
  if (!isNewSupplier && payloadKeys.length === 0) {
    toast.info("No se realizaron cambios en el proveedor.");
    return;
  }

  try {
    const payload = { ...supplierFormData };

    if (!isNewSupplier) {
      payload._method = "PUT";
    }

    await axios.post(url, payload);

    toast.success(`Proveedor ${isNewSupplier ? "creado" : "actualizado"} con éxito`);
    isEditDialogVisible.value = false;
    await fetchSuppliers();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      supplierFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar/crear el proveedor:", error);
      toast.error("Hubo un error al guardar el proveedor.");
    }
  }
};

const handleEditSupplier = (supplier) => {
  currentSupplier.value = { ...supplier };
  supplierFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleDeleteSupplier = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de este proveedor!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/suppliers/${id}`);
      toast.success("Proveedor eliminado con éxito.");
      fetchSuppliers();
    } catch (error) {
      console.error(`Error al borrar el proveedor ${id}:`, error);
      toast.error("No se pudo eliminar el proveedor.");
    }
  }
};

const handleCheckSupplierApi = async (supplier) => {
  checkingApiSupplierId.value = supplier.id;

  try {
    const { data } = await axios.get(`/suppliers/${supplier.id}/connection`);

    if (data.status === "ok") {
      toast.success(
        `Se añadieron ${data.count_product} productos y ${data.count_invoice} facturas del proveedor ${supplier.name}`
      );
    } else {
      toast.error(`Respuesta inesperada de la API de ${supplier.name}`);
    }
  } catch (error) {
    console.error(`Error al verificar API de ${supplier.name}:`, error);
    toast.error(`No se pudo verificar la API de ${supplier.name}`);
  } finally {
    checkingApiSupplierId.value = null;
  }
};

const handlePaymentRule = async (supplier) => {
  currentSupplier.value = { ...supplier };
  supplierFormErrors.value = {};
  isPaymentRuleDialogVisible.value = true;

  loading.value = true;
  await fetchPaymentRules();
};

const handleSavePaymentRule = async (paymentRuleFormData) => {
  const isNewSupplier = !currentSupplier.value.id;
  const url = `/suppliers/${currentSupplier.value.id}/payment-rules`;
  try {
    const payload = { ...paymentRuleFormData };

    await axios.post(url, payload);

    toast.success(`Reglas de pago creadas con éxito`);
    isPaymentRuleDialogVisible.value = false;

    await fetchPaymentRules();
    await fetchSuppliers();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      supplierFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar/crear las reglas de pago:", error);
      toast.error("Hubo un error al guardar las reglas de pago.");
    }
  }
};

const handleSupplierLaboratory = async (supplier) => {
  currentSupplier.value = { ...supplier };
  supplierFormErrors.value = {};
  isSupplierLaboratoryDialogVisible.value = true;

  await fetchLaboratoryLinks();
};

const handleSaveSupplierLaboratory = async (supplierLaboratoryFormData) => {
  const url = `/suppliers/${currentSupplier.value.id}/laboratories`;
  try {
    const payload = { ...supplierLaboratoryFormData };

    await axios.post(url, payload);

    toast.success("Laboratorio vinculado con éxito");

    isSupplierLaboratoryDialogVisible.value = false;
    await fetchLaboratoryLinks();
    await fetchSuppliers();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      supplierFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar/crear la regla de pago:", error);
      toast.error("Hubo un error al guardar la regla de pago.");
    }
  }
};

const handleSupplierPendingInvoices = async (supplier) => {
  currentSupplier.value = { ...supplier };
  isPendingInvoicesDialogVisible.value = true;

  await fetchPendingInvoices();
};

const handleSupplierDiscountRule = async (supplier) => {
  currentSupplier.value = { ...supplier };
  isSupplierDiscountRuleDialogVisible.value = true;

  loading.value = true;
  await fetchLaboratoryLinks();
  await fetchDiscountRules();
};

const handleSaveDiscountRules = async (formData) => {
  try {
    await axios.post(
      `/supplier-laboratories/${formData.supplier_laboratory_id}/discount-rules`,
      formData
    );

    toast.success("Reglas de descuento guardadas con éxito");
    isSupplierDiscountRuleDialogVisible.value = false;

    await fetchDiscountRules();
    await fetchSuppliers();
  } catch (error) {
    if (error.response?.status === 422) {
      supplierFormErrors.value = error.response.data.errors;
      toast.error("Corrige los errores del formulario.");
    } else {
      console.error("Error al guardar reglas de descuento:", error);
      toast.error("Hubo un error al guardar las reglas.");
    }
  }
};

const handleSupplierDiscount = async (supplier) => {
  currentSupplier.value = { ...supplier };
  supplierFormErrors.value = {};
  isSupplierDiscountDialogVisible.value = true;

  loading.value = true;
  await fetchSupplierDiscount();
};

const handleSaveSupplierDiscount = async (supplierDiscountFormData) => {
  const isNewSupplier = !currentSupplier.value.id;
  const url = `/suppliers/${currentSupplier.value.id}/discounts`;
  try {
    const payload = { ...supplierDiscountFormData };

    await axios.post(url, payload);

    toast.success(`Descuentos creados con éxito`);
    isPaymentRuleDialogVisible.value = false;

    await fetchSupplierDiscount();
    await fetchSuppliers();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      supplierFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar/crear los descuentos:", error);
      toast.error("Hubo un error al guardar los descuentos.");
    }
  }
};

const clearFormErrors = () => {
  supplierFormErrors.value = {};
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSuppliers(), 300);
  },
  { deep: true }
);

watch([searchQuery], () => {
  page.value = 1;
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};
</script>

<template>
  <div>
    <SupplierFilters
      v-model:searchQuery="searchQuery"
      @clear="handleClearFilters"
      @sort="handleSort"
      @add-supplier="handleAddSupplier"
    />

    <SupplierTable
      :suppliers="suppliers"
      :loading="loading"
      :total-supplier="totalSupplier"
      :items-per-page="itemsPerPage"
      :page="page"
      :checking-api-id="checkingApiSupplierId"
      @update:options="updateTableOptions"
      @edit-supplier="handleEditSupplier"
      @delete-supplier="handleDeleteSupplier"
      @payment-rule="handlePaymentRule"
      @supplier-laboratory="handleSupplierLaboratory"
      @supplier-pending-invoices="handleSupplierPendingInvoices"
      @supplier-discount-rule="handleSupplierDiscountRule"
      @check-supplier-api="handleCheckSupplierApi"
      @supplier-discount="handleSupplierDiscount"
    />

    <SupplierEditDialog
      v-model="isEditDialogVisible"
      :supplier="currentSupplier"
      :errors="supplierFormErrors"
      @save="handleSaveSupplier"
      @clear-errors="clearFormErrors"
    />

    <PaymentRuleEditDialog
      v-model="isPaymentRuleDialogVisible"
      :supplier="currentSupplier"
      :payment-rules="paymentRules"
      :loading="loading"
      :errors="supplierFormErrors"
      @save="handleSavePaymentRule"
      @clear-errors="clearFormErrors"
    />

    <SupplierLaboratoryEditDialog
      v-model="isSupplierLaboratoryDialogVisible"
      :supplier="currentSupplier"
      :laboratories="laboratories"
      :laboratory-links="laboratoryLinks"
      :errors="supplierFormErrors"
      @save="handleSaveSupplierLaboratory"
      @clear-errors="clearFormErrors"
    />

    <SupplierPendingInvoicesDialog
      v-model="isPendingInvoicesDialogVisible"
      :pending-invoices="pendingInvoices"
      :loading="loading"
    />

    <SupplierDiscountRulesDialog
      v-model="isSupplierDiscountRuleDialogVisible"
      :supplier="currentSupplier"
      :laboratory-links="laboratoryLinks"
      :discount-rules="discountRules"
      :loading="loading"
      @save="handleSaveDiscountRules"
      @clear-errors="clearFormErrors"
    />

    <SupplierDiscountEditDialog
      v-model="isSupplierDiscountDialogVisible"
      :supplier="currentSupplier"
      :supplier-discount="supplierDiscount"
      :loading="loading"
      :errors="supplierFormErrors"
      @save="handleSaveSupplierDiscount"
      @clear-errors="clearFormErrors"
    />
  </div>
</template>
