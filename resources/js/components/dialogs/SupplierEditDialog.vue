<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({
  dispatch_days: [],
  order_days: {},
  //supplier_payment_method: null,
  //supplier_payment_days: null,
});
const formErrors = ref({});

const opciones = [
  { label: "Bs", value: "Bs" },
  { label: "Divisas", value: "Divisas" },
];

const dias = [
  { label: "Lunes", value: "monday" },
  { label: "Martes", value: "tuesday" },
  { label: "Miércoles", value: "wednesday" },
  { label: "Jueves", value: "thursday" },
  { label: "Viernes", value: "friday" },
  { label: "Sábado", value: "saturday" },
];

/*const paymentMethodOptions = [
  { label: "Fecha de vencimiento", value: "due_date" },
  { label: "Pronto Pago", value: "early_payment" },
  { label: "Fecha de creación", value: "creation_date" },
  { label: "Días de crédito", value: "credit_days" },
];*/

const isNewSupplier = computed(() => !formData.value.id);

// const shouldShowDaysInput = computed(() => {
//   return formData.value.supplier_payment_method === "credit_days";
// });

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  // if (!formData.value.supplier_payment_method) {
  //   formErrors.value.supplier_payment_method = [
  //     "El método de pago es requerido",
  //   ];
  //   return;
  // }

  // if (formData.value.supplier_payment_method === "credit_days") {
  //   if (
  //     !formData.value.supplier_payment_days ||
  //     formData.value.supplier_payment_days <= 0
  //   ) {
  //     formErrors.value.supplier_payment_days = [
  //       "Los días de crédito son requeridos y deben ser mayor a 0",
  //     ];
  //     return;
  //   }
  // }

  const original = props.supplier || {};
  const current = formData.value;

  const filteredPayload = {};

  Object.entries(current).forEach(([key, value]) => {
    const originalValue = original[key];

    const hasChanged =
      typeof value === "object"
        ? JSON.stringify(value) !== JSON.stringify(originalValue)
        : value !== originalValue;

    if (
      hasChanged &&
      (key === "payment_due_type" ||
        key === "payment_due_reference" ||
        key === "custom_due_days")
    ) {
      filteredPayload[key] = value === undefined ? null : value;
      return;
    }

    const isFilled = Array.isArray(value)
      ? value.length > 0
      : typeof value === "object" && value !== null
        ? Object.values(value).some((v) =>
            Array.isArray(v) ? v.length > 0 : !!v,
          )
        : typeof value === "boolean"
          ? true
          : value !== null && value !== "" && value !== undefined;

    if (hasChanged && isFilled) {
      filteredPayload[key] = value;
    }
  });

  // if (formData.value.supplier_payment_method) {
  //   filteredPayload.supplier_payment_method =
  //     formData.value.supplier_payment_method;

  //   if (
  //     formData.value.supplier_payment_method === "credit_days" &&
  //     formData.value.supplier_payment_days
  //   ) {
  //     filteredPayload.supplier_payment_days =
  //       formData.value.supplier_payment_days;
  //   }
  // }

  const currentRef = formData.value.invoice_date_reference;
  const originalRef = original.invoice_date_reference;

  if (formData.value.payment_due_type === "invoice_date") {
    if (currentRef !== originalRef) {
      filteredPayload.invoice_date_reference = currentRef;
    } else if (currentRef !== undefined && currentRef !== null) {
      filteredPayload.invoice_date_reference = currentRef;
    }
  }

  if (
    typeof formData.value.order_days === "object" &&
    Object.keys(formData.value.order_days).length > 0 &&
    Object.values(formData.value.order_days).some(
      (v) => Array.isArray(v) && v.length > 0,
    )
  ) {
    filteredPayload.order_days = formData.value.order_days;
  }

  console.log(filteredPayload);
  emit("save", filteredPayload);
};

const diaDespachoLabel = (value) => {
  const match = dias.find((d) => d.value === value);
  return match?.label || value;
};

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true },
);

watch(
  () => props.supplier,
  (newSupplier) => {
    if (newSupplier && Object.keys(newSupplier).length > 0) {
      formData.value = JSON.parse(JSON.stringify(newSupplier));

      //if (newSupplier.payment_date && newSupplier.payment_date.type) {
      //formData.value.supplier_payment_method = newSupplier.payment_date.type;

      // if (
      //   newSupplier.payment_date.type === "credit_days" &&
      //   newSupplier.payment_date.days
      // ) {
      //   formData.value.supplier_payment_days = newSupplier.payment_date.days;
      // }
      // } else if (
      //   newSupplier.payment_methods &&
      //   newSupplier.payment_methods.length > 0
      // ) {
      // formData.value.supplier_payment_method =
      //   newSupplier.payment_methods[0].type;

      // if (
      //   newSupplier.payment_methods[0].type === "credit_days" &&
      //   newSupplier.payment_methods[0].days
      // ) {
      // formData.value.supplier_payment_days =
      //   newSupplier.payment_methods[0].days;
      //   }
      // } else {
      //formData.value.supplier_payment_method = null;
      //formData.value.supplier_payment_days = null;
      //}

      const normalized = {};
      (formData.value.dispatch_days || []).forEach((day) => {
        const old = newSupplier.order_days;
        if (
          old &&
          typeof old === "object" &&
          !Array.isArray(old) &&
          Array.isArray(old[day])
        ) {
          normalized[day] = [...old[day]];
        } else {
          normalized[day] = [];
        }
      });
      formData.value.order_days = normalized;
    } else {
      formData.value = {
        name: "",
        dispatch_days: [],
        order_days: {},
        //supplier_payment_method: null,
        //supplier_payment_days: null,
      };
    }
    formErrors.value = {};
  },
  { deep: true, immediate: true },
);

watch(
  () => formData.value.dispatch_days,
  (newDispatchDays) => {
    newDispatchDays.forEach((dia) => {
      if (!Array.isArray(formData.value.order_days[dia])) {
        formData.value.order_days[dia] = [];
      }
    });
  },
  { immediate: true },
);

/*watch(
  () => formData.value.supplier_payment_method,
  (newMethod) => {
    if (newMethod !== "credit_days") {
      formData.value.supplier_payment_days = null;
      if (formErrors.value.supplier_payment_days) {
        delete formErrors.value.supplier_payment_days;
      }
    }
  }
);*/
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
    <VCard v-if="formData" class="d-flex flex-column">
      <!-- Header Estilizado -->
      <VCardTitle class="d-flex align-center pa-4 bg-primary text-white">
        <VIcon
          icon="tabler-truck-delivery"
          size="24"
          color="white"
          class="me-2"
        />
        <span class="text-h5 font-weight-bold">
          {{ isNewSupplier ? "Añadir Nuevo Proveedor" : "Editar Proveedor" }}
        </span>

        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-6" style="overflow-y: auto;">
        <VForm @submit.prevent="submitForm">
          <!-- Información Principal -->
          <div class="text-overline mb-4 text-primary font-weight-bold">
            Información Principal
          </div>

          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="formData.name"
                label="Nombre Comercial"
                placeholder="Ej: Droguería Nena"
                prepend-inner-icon="tabler-user"
                :error-messages="formErrors.name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="formData.social_reason"
                label="Razón Social"
                placeholder="Ej: Inversiones Nena C.A."
                prepend-inner-icon="tabler-building"
                :error-messages="formErrors.social_reason"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="formData.rif"
                label="RIF"
                placeholder="J-12345678-9"
                prepend-inner-icon="tabler-id"
                :error-messages="formErrors.rif"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="formData.address"
                label="Dirección"
                placeholder="Dirección fiscal completa"
                prepend-inner-icon="tabler-map-pin"
                :error-messages="formErrors.address"
              />
            </VCol>
          </VRow>

          <div class="text-overline mt-6 mb-4 text-primary font-weight-bold">
            Contacto
          </div>

          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="formData.sales_phone"
                label="Teléfono Ventas"
                type="tel"
                placeholder="4121234567"
                prepend-inner-icon="tabler-phone"
                :error-messages="formErrors.sales_phone"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="formData.collections_phone"
                label="Teléfono Cobranza"
                type="tel"
                placeholder="4147654321"
                prepend-inner-icon="tabler-phone-call"
                :error-messages="formErrors.collections_phone"
              />
            </VCol>
          </VRow>

          <div class="text-overline mt-6 mb-4 text-primary font-weight-bold">
            Configuración de Pago
          </div>

          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="formData.credit_days"
                label="Días de Crédito"
                type="number"
                placeholder="0"
                prepend-inner-icon="tabler-calendar-time"
                :error-messages="formErrors.credit_days"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VRadioGroup
                v-model="formData.payment_method"
                label="Forma de Pago"
                density="compact"
                hide-details
              >
                <div class="d-flex gap-x-4">
                  <VRadio
                    v-for="opcion in opciones"
                    :key="opcion.value"
                    :label="opcion.label"
                    :value="opcion.value"
                    color="primary"
                  />
                </div>
              </VRadioGroup>
            </VCol>
          </VRow>

          <VRow class="mt-4">
            <VCol cols="12" md="6">
              <AppSelect
                v-model="formData.payment_due_type"
                :items="[
                  { title: 'Fecha de la factura', value: 'invoice_date' },
                  { title: 'Pronto pago', value: 'early_payment' },
                  { title: 'Otro (personalizado)', value: 'custom' },
                ]"
                label="Fecha Límite de Pago"
                prepend-inner-icon="tabler-calendar-stats"
                :error-messages="formErrors.payment_due_type"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
              v-if="formData.payment_due_type === 'invoice_date'"
            >
              <AppSelect
                v-model="formData.invoice_date_reference"
                :items="[
                  { title: 'Fecha de Recibo', value: 'receipt_date' },
                  { title: 'Fecha de Vencimiento', value: 'expiration_date' },
                  { title: 'Fecha de emisión', value: 'issue_date' },
                ]"
                label="Referencia de Fecha de Factura"
                prepend-inner-icon="tabler-timeline"
                :error-messages="formErrors.invoice_date_reference"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
              v-if="formData.payment_due_type === 'custom'"
            >
              <AppTextField
                v-model.number="formData.custom_due_days"
                label="Días personalizados"
                type="number"
                placeholder="0"
                prepend-inner-icon="tabler-numbers"
                :error-messages="formErrors.custom_due_days"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
              v-if="
                formData.payment_due_type !== 'invoice_date' &&
                formData.payment_due_type
              "
            >
              <AppSelect
                v-model="formData.payment_due_reference"
                :items="[
                  { title: 'Fecha de emisión', value: 'issue_date' },
                  { title: 'Fecha de recibo', value: 'receipt_date' },
                ]"
                label="Contar días desde"
                prepend-inner-icon="tabler-clock-play"
                :error-messages="formErrors.payment_due_reference"
              />
            </VCol>
          </VRow>

          <div class="text-overline mt-6 mb-4 text-primary font-weight-bold">
            Logística y Días de Despacho
          </div>

          <VRow>
            <VCol cols="12">
              <VCard variant="tonal" color="primary" class="pa-5 rounded-lg border">
                <div class="d-flex align-center mb-4">
                  <VIcon icon="tabler-truck" color="primary" class="me-2" />
                  <span class="text-subtitle-1 font-weight-bold">Configuración de Despacho</span>
                </div>
                
                <div class="text-body-2 mb-3 text-medium-emphasis">
                  Seleccione los días en los que el proveedor realiza entregas:
                </div>

                <div class="d-flex flex-wrap gap-4">
                  <VCheckbox
                    v-for="dia in dias"
                    :key="dia.value"
                    v-model="formData.dispatch_days"
                    :label="dia.label"
                    :value="dia.value"
                    hide-details
                    density="comfortable"
                    color="primary"
                  />
                </div>

                <div
                  v-if="formErrors.dispatch_days"
                  class="text-error text-caption mt-2 d-flex align-center"
                >
                  <VIcon icon="tabler-alert-circle" size="14" class="me-1" />
                  {{ formErrors.dispatch_days[0] }}
                </div>
              </VCard>
            </VCol>
          </VRow>

          <VRow
            v-if="formData.dispatch_days && formData.dispatch_days.length"
            class="mt-4"
          >
            <VCol cols="12">
              <div class="text-subtitle-2 mb-4">
                Días de Pedido por Despacho:
              </div>
              <div
                v-for="diaDespacho in formData.dispatch_days"
                :key="diaDespacho"
                class="mb-6"
              >
                <div class="d-flex align-center mb-2">
                  <VIcon
                    icon="tabler-arrow-right"
                    size="18"
                    color="primary"
                    class="me-2"
                  />
                  <span class="text-body-2 font-weight-bold">
                    Pedidos requeridos para {{ diaDespachoLabel(diaDespacho) }}:
                  </span>
                </div>

                <div class="d-flex flex-wrap gap-x-4 ps-6">
                  <VCheckbox
                    v-for="diaPedido in dias"
                    :key="diaPedido.value"
                    v-model="formData.order_days[diaDespacho]"
                    :label="diaPedido.label"
                    :value="diaPedido.value"
                    hide-details
                    density="compact"
                    color="primary"
                  />
                </div>

                <div
                  v-if="formErrors.order_days?.[diaDespacho]"
                  class="text-error text-caption mt-1 ps-6"
                >
                  {{ formErrors.order_days[diaDespacho][0] }}
                </div>
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1"
          prepend-icon="tabler-device-floppy"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
