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

const emit = defineEmits(["update:modalValue", "request-update"]);

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

const discounts = ref([]);
const discountLoading = ref(false);

const paymentRules = ref([]);
const paymentRuleLoading = ref(false);

const closeDialog = () => {
  emit("update:modelValue", false);
};

watch(
  () => props.selectedSupplier,
  (selectedSupplier) => {
    if (selectedSupplier?.id) {
      Promise.all([fetchSupplierDiscounts(selectedSupplier.id), fetchSupplierPayments(selectedSupplier.id)]);
    }
  },
  { deep: true, immediate: true },
);

const fetchSupplierDiscounts = async (id) => {
  try {
    discountLoading.value = true;
    const { data } = await axios.get(`/suppliers/${id}/discounts`);
    discounts.value = data.supplier_discount;
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
    const { data } = await axios.get(`/suppliers/${id}/payment-rules`);
    paymentRules.value = data.payment_rules;
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

const submitForm = async () => {
  emit("request-update", props.selectedSupplier, {
    discount: localEnableDiscounts.value,
    payment: localEnablePaymentRules.value,
  });

  closeModal();
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
            <span class="text-h6">Pronto Pago</span>
          </VCol>
          <VCol>
            <VSwitch v-model="localEnablePaymentRules" label="Activar Pronto Pago" :inset="true" />
          </VCol>
        </VRow>

        <VDataTable
          :headers="[
            { title: 'Días', key: 'days' },
            { title: '% de Descuento', key: 'discount_percentage' },
          ]"
          :items="paymentRules"
          density="compact"
          no-data-text="No hay reglas registradas para este proveedor."
          :hide-default-footer="true"
        />

        <div class="my-8" />

        <VRow>
          <VCol>
            <span class="text-h6">Descuentos</span>
          </VCol>
          <VCol>
            <VSwitch v-model="localEnableDiscounts" label="Activar Descuentos" :inset="true" />
          </VCol>
        </VRow>

        <VDataTable
          :headers="[
            { title: 'Nombre', key: 'name' },
            { title: '% de Descuento', key: 'discount_percentage' },
          ]"
          :items="discounts"
          density="compact"
          no-data-text="No hay descuentos registrados para este proveedor."
          :hide-default-footer="true"
        />
      </VSheet>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn color="secondary" variant="outlined" @click="closeDialog" class="flex-grow-1 w-0 mr-4"> Cancelar </VBtn>
        <VBtn color="primary" variant="flat" @click="submitForm" class="flex-grow-1 w-0"> Solicitar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
