<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  furniture: { type: Object, default: () => ({}) },
  acquisitionYears: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({});
const formErrors = ref({});

const isNewFurniture = computed(() => !formData.value.id);

const calculateCurrentValue = computed(() => {
  if (
    !formData.value.cost ||
    !formData.value.acquisition_year ||
    !formData.value.annual_depreciation_rate
  ) {
    return 0;
  }

  const currentYear = new Date().getFullYear();
  const yearsDepreciated = Math.max(
    0,
    currentYear - formData.value.acquisition_year
  );
  const totalDepreciation =
    (formData.value.annual_depreciation_rate / 100) * yearsDepreciated;
  const depreciationFactor = Math.max(0, 1 - totalDepreciation);

  return formData.value.cost * depreciationFactor;
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};

const depreciationInfo = computed(() => {
  if (!formData.value.cost || !calculateCurrentValue.value) {
    return { percentage: 0, amount: 0 };
  }

  const depreciationAmount = formData.value.cost - calculateCurrentValue.value;
  const depreciationPercentage =
    (depreciationAmount / formData.value.cost) * 100;

  return {
    percentage: depreciationPercentage,
    amount: depreciationAmount,
  };
});

const depreciationStatus = computed(() => {
  const percentage = depreciationInfo.value.percentage;

  if (percentage <= 20) {
    return { text: "Excelente", color: "success" };
  } else if (percentage <= 50) {
    return { text: "Bueno", color: "info" };
  } else if (percentage <= 80) {
    return { text: "Regular", color: "warning" };
  } else {
    return { text: "Depreciado", color: "error" };
  }
});

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true }
);

watch(
  () => props.furniture,
  (newFurniture) => {
    if (newFurniture && Object.keys(newFurniture).length > 0) {
      formData.value = JSON.parse(JSON.stringify(newFurniture));
    } else {
      formData.value = {
        name: "",
        cost: null,
        acquisition_year: new Date().getFullYear(),
        annual_depreciation_rate: null,
      };
    }
    formErrors.value = {};
  },
  { deep: true, immediate: true }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  const payload = {
    name: formData.value.name,
    cost: parseFloat(formData.value.cost),
    acquisition_year: parseInt(formData.value.acquisition_year),
    annual_depreciation_rate: parseFloat(
      formData.value.annual_depreciation_rate
    ),
  };

  emit("save", payload);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="700px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard v-if="formData" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-sofa" class="mr-3" color="orange" />
        <span class="text-h5 font-weight-bold">{{
          isNewFurniture ? "Añadir Nuevo Mobiliario" : "Editar Mobiliario"
        }}</span>

        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <!-- Información Básica -->
          <div class="mb-6">
            <p class="text-h6 font-weight-medium mb-4">Información Básica</p>

            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="formData.name"
                  label="Nombre del Mobiliario"
                  variant="outlined"
                  placeholder="Ej: Escritorio ejecutivo, Silla ergonómica..."
                  prepend-inner-icon="tabler-tag"
                  :error-messages="formErrors.name"
                />
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="formData.cost"
                  label="Costo de Adquisición"
                  type="number"
                  step="0.01"
                  min="0"
                  prefix="$"
                  variant="outlined"
                  prepend-inner-icon="tabler-currency-dollar"
                  :error-messages="formErrors.cost"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect
                  v-model="formData.acquisition_year"
                  label="Año de Adquisición"
                  :items="props.acquisitionYears"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  prepend-inner-icon="tabler-calendar"
                  :error-messages="formErrors.acquisition_year"
                />
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="formData.annual_depreciation_rate"
                  label="Tasa de Depreciación Anual (%)"
                  type="number"
                  step="0.1"
                  min="0"
                  max="100"
                  suffix="%"
                  variant="outlined"
                  prepend-inner-icon="tabler-trending-down"
                  placeholder="Ej: 10, 15.5, 20..."
                  :error-messages="formErrors.annual_depreciation_rate"
                />
                <div class="text-caption text-disabled mt-1">
                  Valores comunes: Mobiliario de oficina (10-20%), Equipos
                  informáticos (25-33%), Muebles (5-15%)
                </div>
              </VCol>
            </VRow>
          </div>

          <!-- Cálculos y Valor Actual -->
          <template
            v-if="
              formData.cost &&
              formData.acquisition_year &&
              formData.annual_depreciation_rate
            "
          >
            <VDivider class="my-6" />

            <VSheet
              color="grey-lighten-4"
              variant="tonal"
              rounded="lg"
              class="pa-6"
            >
              <p class="text-h6 font-weight-medium mb-4 d-flex align-center">
                <VIcon icon="tabler-calculator" class="mr-2" />
                Cálculos de Valor
              </p>

              <VRow>
                <VCol cols="12" md="6">
                  <VCard variant="outlined">
                    <VCardText class="text-center">
                      <div class="text-body-2 text-disabled mb-1">
                        Costo Original
                      </div>
                      <div class="text-h6 font-weight-bold">
                        {{ formatCurrency(formData.cost) }}
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>

                <VCol cols="12" md="6">
                  <VCard variant="outlined" :color="depreciationStatus.color">
                    <VCardText class="text-center">
                      <div class="text-body-2 mb-1">Valor Actual</div>
                      <div class="text-h6 font-weight-bold">
                        {{ formatCurrency(calculateCurrentValue) }}
                      </div>
                      <VChip
                        :color="depreciationStatus.color"
                        size="small"
                        class="mt-2"
                      >
                        {{ depreciationStatus.text }}
                      </VChip>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>

              <VRow class="mt-4">
                <VCol cols="12" md="4">
                  <div class="text-center">
                    <div class="text-body-2 text-disabled">Años de Uso</div>
                    <div class="text-h6 font-weight-bold">
                      {{ new Date().getFullYear() - formData.acquisition_year }}
                      años
                    </div>
                  </div>
                </VCol>

                <VCol cols="12" md="4">
                  <div class="text-center">
                    <div class="text-body-2 text-disabled">
                      Depreciación Total
                    </div>
                    <div class="text-h6 font-weight-bold text-error">
                      {{ depreciationInfo.percentage.toFixed(1) }}%
                    </div>
                  </div>
                </VCol>

                <VCol cols="12" md="4">
                  <div class="text-center">
                    <div class="text-body-2 text-disabled">
                      Valor Depreciado
                    </div>
                    <div class="text-h6 font-weight-bold text-error">
                      {{ formatCurrency(depreciationInfo.amount) }}
                    </div>
                  </div>
                </VCol>
              </VRow>

              <!-- Gráfico de progreso de depreciación -->
              <div class="mt-6">
                <div class="text-body-2 text-disabled mb-2">
                  Progreso de Depreciación
                </div>
                <VProgressLinear
                  :model-value="depreciationInfo.percentage"
                  :color="depreciationStatus.color"
                  height="8"
                  rounded
                />
                <div
                  class="d-flex justify-space-between text-caption text-disabled mt-1"
                >
                  <span>0%</span>
                  <span>50%</span>
                  <span>100%</span>
                </div>
              </div>
            </VSheet>
          </template>
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
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
          :disabled="
            !formData.name ||
            !formData.cost ||
            !formData.acquisition_year ||
            !formData.annual_depreciation_rate
          "
        >
          {{ isNewFurniture ? "Crear" : "Actualizar" }} Mobiliario
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
