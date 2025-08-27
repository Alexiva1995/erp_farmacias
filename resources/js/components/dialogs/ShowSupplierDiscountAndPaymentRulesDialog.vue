<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
  enableDiscounts: { type: Boolean, default: false },
  enablePaymentRules: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modalValue"]);

const localEnableDiscounts = ref(props.enableDiscounts);
const localEnablePaymentRules = ref(props.enablePaymentRules);

watch(
  () => props.enableDiscounts,
  (newVal) => {
    localEnableDiscounts.value = newVal;
  },
  { deep: true, immediate: true },
);

watch(
  () => props.enablePaymentRules,
  (newVal) => {
    localEnablePaymentRules.value = newVal;
  },
  { deep: true, immediate: true },
);

const discountPage = ref(1);
const discountItemsPerPage = ref(10);
const discountTotalPages = ref(0);
const discounts = ref([]);
const discountLoading = ref(false);

const paymentRulesPage = ref(1);
const paymentRulesItemsPerPage = ref(10);
const paymentRulesTotalPages = ref(0);
const paymentRules = ref([]);
const paymentRuleLoading = ref(false);

const closeDialog = () => {
  emit("update:modelValue", false);
};

watch(
  () => props.selectedSupplier,
  (selectedSupplier) => {
    if (selectedSupplier?.id) {
      discountPage.value = 1;
      paymentRulesPage.value = 1;
      Promise.all([fetchSupplierDiscounts(selectedSupplier.id), fetchSupplierPayments(selectedSupplier.id)]);
    }
  },
  { deep: true, immediate: true },
);

watch([paymentRulesPage, discountItemsPerPage], () => {
  if (props.selectedSupplier?.id) {
    fetchSupplierDiscounts(props.selectedSupplier.id);
  }
});

watch([discountPage, paymentRulesItemsPerPage], () => {
  if (props.selectedSupplier?.id) {
    fetchSupplierPayments(props.selectedSupplier.id);
  }
});

const discountHeaders = [
  { title: "Nombre", key: "name", sortable: false },
  { title: "% De Descuento", key: "laboratory", sortable: false },
];

const paymentRulesHeaders = [
  { title: "Días", key: "days", sortable: false },
  { title: "% De Descuento", key: "discount_percentage", sortable: false },
];

const fetchSupplierDiscounts = async (id) => {
  try {
    discountLoading.value = true;
    const { data } = await axios.get(`/suppliers/${id}/discounts`, {
      params: {
        page: discountPage.value,
        perPage: discountItemsPerPage.value,
      },
    });
    discounts.value = data.data;
    discountTotalPages.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener los descuentos del proveedor.");
  } finally {
    discountLoading.value = false;
  }
};

const fetchSupplierPayments = async (id) => {
  try {
    paymentRuleLoading.value = true;
    const { data } = await axios.get(`/suppliers/${id}/payment-rules`, {
      params: {
        page: paymentRulesPage.value,
        perPage: paymentRulesItemsPerPage.value,
      },
    });
    paymentRules.value = data.data;
    paymentRulesTotalPages.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener las reglas de pronto pago del proveedor.");
  } finally {
    paymentRuleLoading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString || dateString === "No se ha establecido conexión") return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};

const updateDiscountsTableOptions = (options) => {
  discountPage.value = options.page;
  discountItemsPerPage.value = options.itemsPerPage;
};

const updatePaymentRulesTableOptions = (options) => {
  paymentRulesPage.value = options.page;
  paymentRulesItemsPerPage.value = options.itemsPerPage;
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Actualizar Productos</span>

        <VSpacer />

        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VSheet color="#f5f5f5" variant="tonal" rounded="lg" class="pa-4">
        <VRow>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Proveedor</span>
              <VChip color="primary" label>{{ selectedSupplier.name }}</VChip>
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Última actualización</span>
              <VChip color="primary" label>{{ formatDate(selectedSupplier.last_connection) }}</VChip>
              <VSpacer />
            </div>
          </VCol>
        </VRow>

        <VRow>
          <VCol>
            <span class="text-h6">Descuentos</span>
          </VCol>
          <VCol>
            <VSwitch v-model="localEnableDiscounts" label="Activar Descuentos" :inset="true" />
          </VCol>
        </VRow>

        <VDataTableServer
          :headers="discountHeaders"
          :items="discounts"
          :loading="discountLoading"
          density="compact"
          class="mt-4 rounded-lg"
          no-data-text="Este proveedor no tiene descuentos registrados."
          :items-per-page="discountItemsPerPage"
          :page="discountPage"
          :server-items-length="discountTotalPages"
          :items-length="discountTotalPages"
          @update:options="updateDiscountsTableOptions"
        />

        <div class="my-8" />

        <VRow>
          <VCol>
            <span class="text-h6">Pronto Pago</span>
          </VCol>
          <VCol>
            <VSwitch v-model="localEnablePaymentRules" label="Activar Pronto Pago" :inset="true" />
          </VCol>
        </VRow>
        <VDataTableServer
          :headers="paymentRulesHeaders"
          :items="paymentRules"
          :loading="loading"
          density="compact"
          class="mt-4 rounded-lg"
          no-data-text="Este proveedor no tiene reglas de pronto pago registradas."
          :items-per-page="paymentRulesItemsPerPage"
          :page="paymentRulesPage"
          :server-items-length="paymentRulesTotalPages"
          :items-length="paymentRulesTotalPages"
          @update:options="updatePaymentRulesTableOptions"
        />
      </VSheet>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn color="secondary" variant="outlined" @click="closeDialog" class="flex-grow-1 w-0 mr-4"> Cerrar </VBtn>
        <VBtn color="primary" variant="flat" @click="closeDialog" class="flex-grow-1 w-0"> Aceptar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
