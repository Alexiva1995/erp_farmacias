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
    currentYear - formData.value.acquisition_year,
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
  { deep: true },
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
  { deep: true, immediate: true },
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
      formData.value.annual_depreciation_rate,
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
      <!-- Header Estilizado -->
      <VCardTitle class="d-flex align-center pa-4 bg-primary text-white">
        <VIcon icon="tabler-sofa" size="24" color="white" class="me-2" />
        <span class="text-h5 font-weight-bold">
          {{ isNewFurniture ? "Añadir Nuevo Mobiliario" : "Editar Mobiliario" }}
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
          <!-- Información Básica -->
          <div class="text-overline mb-4 text-primary font-weight-bold">
            Información Básica
          </div>

          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="formData.name"
                label="Nombre del Mobiliario"
                placeholder="Ej: Escritorio ejecutivo, Silla ergonómica..."
                prepend-inner-icon="tabler-tag"
                :error-messages="formErrors.name"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="formData.cost"
                label="Costo de Adquisición"
                type="number"
                step="0.01"
                min="0"
                prefix="$"
                prepend-inner-icon="tabler-currency-dollar"
                :error-messages="formErrors.cost"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppSelect
                v-model="formData.acquisition_year"
                label="Año de Adquisición"
                :items="props.acquisitionYears"
                item-title="title"
                item-value="value"
                prepend-inner-icon="tabler-calendar"
                :error-messages="formErrors.acquisition_year"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model.number="formData.annual_depreciation_rate"
                label="Tasa de Depreciación Anual (%)"
                type="number"
                step="0.1"
                min="0"
                max="100"
                suffix="%"
                prepend-inner-icon="tabler-trending-down"
                placeholder="Ej: 10, 15.5, 20..."
                :error-messages="formErrors.annual_depreciation_rate"
              />
              <div class="text-caption text-disabled mt-1 d-flex align-center">
                <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                Valores comunes: Mobiliario de oficina (10-20%), Equipos
                informáticos (25-33%)
              </div>
            </VCol>
          </VRow>

          <!-- Cálculos y Valor Actual -->
          <template
            v-if="
              formData.cost &&
              formData.acquisition_year &&
              formData.annual_depreciation_rate
            "
          >
            <div class="text-overline mt-6 mb-4 text-primary font-weight-bold">
              Cálculos de Valor
            </div>

            <VCard variant="tonal" color="secondary" class="pa-4 rounded-lg">
              <VRow>
                <VCol cols="12" md="6">
                  <VCard variant="flat" class="text-center pa-2">
                    <div class="text-caption text-disabled mb-1">
                      Costo Original
                    </div>
                    <div class="text-h6 font-weight-bold">
                      {{ formatCurrency(formData.cost) }}
                    </div>
                  </VCard>
                </VCol>

                <VCol cols="12" md="6">
                  <VCard
                    variant="flat"
                    class="text-center pa-2"
                    :color="depreciationStatus.color + '-lighten-5'"
                  >
                    <div class="text-caption mb-1">Valor Actual</div>
                    <div
                      class="text-h6 font-weight-bold"
                      :class="`text-${depreciationStatus.color}`"
                    >
                      {{ formatCurrency(calculateCurrentValue) }}
                    </div>
                    <VChip
                      :color="depreciationStatus.color"
                      size="x-small"
                      label
                      class="mt-1"
                    >
                      {{ depreciationStatus.text }}
                    </VChip>
                  </VCard>
                </VCol>
              </VRow>

              <VRow class="mt-2">
                <VCol cols="12" md="4" class="text-center border-e">
                  <div class="text-caption text-disabled">Años de Uso</div>
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ new Date().getFullYear() - formData.acquisition_year }}
                    años
                  </div>
                </VCol>

                <VCol cols="12" md="4" class="text-center border-e">
                  <div class="text-caption text-disabled">
                    Depreciación Total
                  </div>
                  <div class="text-subtitle-1 font-weight-bold text-error">
                    {{ depreciationInfo.percentage.toFixed(1) }}%
                  </div>
                </VCol>

                <VCol cols="12" md="4" class="text-center">
                  <div class="text-caption text-disabled">Valor Depreciado</div>
                  <div class="text-subtitle-1 font-weight-bold text-error">
                    {{ formatCurrency(depreciationInfo.amount) }}
                  </div>
                </VCol>
              </VRow>

              <!-- Gráfico de progreso de depreciación -->
              <div class="mt-4">
                <div
                  class="d-flex justify-space-between text-caption text-disabled mb-1"
                >
                  <span>Progreso de Depreciación</span>
                  <span>{{ depreciationInfo.percentage.toFixed(1) }}%</span>
                </div>
                <VProgressLinear
                  :model-value="depreciationInfo.percentage"
                  :color="depreciationStatus.color"
                  height="8"
                  rounded
                />
              </div>
            </VCard>
          </template>
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
          :disabled="
            !formData.name ||
            !formData.cost ||
            !formData.acquisition_year ||
            !formData.annual_depreciation_rate
          "
        >
          {{ isNewFurniture ? "Crear" : "Actualizar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
