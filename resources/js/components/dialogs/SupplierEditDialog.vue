<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({
  dispatch_days: [],
  order_days: {},
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

const isNewSupplier = computed(() => !formData.value.id);

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  const original = props.supplier || {};
  const current = formData.value;

  const filteredPayload = {};

  Object.entries(current).forEach(([key, value]) => {
    const originalValue = original[key];

    const hasChanged =
      typeof value === "object"
        ? JSON.stringify(value) !== JSON.stringify(originalValue)
        : value !== originalValue;

    const isFilled = Array.isArray(value)
      ? value.length > 0
      : typeof value === "object" && value !== null
      ? Object.values(value).some((v) => (Array.isArray(v) ? v.length > 0 : !!v))
      : typeof value === "boolean"
      ? true
      : value !== null && value !== "" && value !== undefined;

    if (hasChanged && isFilled) {
      filteredPayload[key] = value;
    }
  });

  if (
    typeof formData.value.order_days === "object" &&
    Object.keys(formData.value.order_days).length > 0 &&
    Object.values(formData.value.order_days).some((v) => Array.isArray(v) && v.length > 0)
  ) {
    filteredPayload.order_days = formData.value.order_days;
  }

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
  { deep: true }
);

watch(
  () => props.supplier,
  (newSupplier) => {
    if (newSupplier && Object.keys(newSupplier).length > 0) {
      formData.value = JSON.parse(JSON.stringify(newSupplier));

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
      };
    }
    formErrors.value = {};
  },
  { deep: true, immediate: true }
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
  { immediate: true }
);
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
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">{{
          isNewSupplier ? "Añadir Nuevo Proveedor" : "Editar Proveedor"
        }}</span>

        <VSpacer />

        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.name"
                label="Nombre"
                variant="outlined"
                :error-messages="formErrors.name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.social_reason"
                label="Razón Social"
                variant="outlined"
                :error-messages="formErrors.social_reason"
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.sales_phone"
                label="Teléfono Ventas"
                type="tel"
                prefix="+"
                variant="outlined"
                :error-messages="formErrors.sales_phone"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.collections_phone"
                label="Teléfono Cobranza"
                type="tel"
                prefix="+"
                variant="outlined"
                :error-messages="formErrors.collections_phone"
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.credit_days"
                label="Días de Crédito"
                type="number"
                variant="outlined"
                :error-messages="formErrors.credit_days"
              />
            </VCol>
            <VCol cols="12" md="6" class="d-flex align-center flex-wrap gap-x-4">
              <VCheckbox
                v-model="formData.cash_payment"
                label="Pago de Contado"
                :true-value="1"
                :false-value="0"
              />
              <VCheckbox
                v-model="formData.charges_igtf"
                label="Cobra IGTF"
                :true-value="1"
                :false-value="0"
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6" class="d-flex align-center flex-wrap gap-x-4">
              <VRadioGroup v-model="formData.payment_method" label="Forma de Pago">
                <VRow>
                  <VCol cols="auto" v-for="opcion in opciones" :key="opcion.value">
                    <VRadio :label="opcion.label" :value="opcion.value" />
                  </VCol>
                </VRow>
              </VRadioGroup>
            </VCol>
          </VRow>

          <VDivider />

          <VRow>
            <VCol cols="12" md="12">
              <h6 class="text-subtitle-1 font-weight-medium mt-3 mb-6">
                Días de Despacho
              </h6>

              <VRow>
                <VCol v-for="dia in dias" :key="dia.value" cols="6" sm="4" class="pa-0">
                  <VCheckbox
                    v-model="formData.dispatch_days"
                    :label="dia.label"
                    :value="dia.value"
                    hide-details
                    density="compact"
                    color="primary"
                  />
                </VCol>
                <div v-if="formErrors.dispatch_days" class="text-error text-sm mt-1">
                  {{ formErrors.dispatch_days[0] }}
                </div>
              </VRow>
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="12">
              <h6
                class="text-subtitle-1 font-weight-medium mt-3 mb-6"
                v-if="formData.dispatch_days.length"
              >
                Días de Pedido por Despacho
              </h6>
              <div
                v-for="diaDespacho in formData.dispatch_days"
                :key="diaDespacho"
                class="mb-4"
              >
                <h6 class="text-caption font-weight-medium text-primary mb-2">
                  Pedidos requeridos para
                  <strong>{{ diaDespachoLabel(diaDespacho) }}</strong
                  >:
                </h6>

                <VRow>
                  <VCol
                    v-for="diaPedido in dias"
                    :key="diaPedido.value"
                    cols="6"
                    sm="4"
                    class="pa-0"
                  >
                    <VCheckbox
                      v-model="formData.order_days[diaDespacho]"
                      :label="diaPedido.label"
                      :value="diaPedido.value"
                      hide-details
                      density="compact"
                      color="primary"
                    />
                  </VCol>
                </VRow>

                <div
                  v-if="formErrors.order_days?.[diaDespacho]"
                  class="text-error text-sm mt-1"
                >
                  {{ formErrors.order_days[diaDespacho][0] }}
                </div>
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="flat" @click="submitForm" class="flex-grow-1 w-0">
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
