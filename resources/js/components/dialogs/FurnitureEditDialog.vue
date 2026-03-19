<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  furniture: { type: Object, default: () => ({}) },
  acquisitionYears: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const { mobile } = useDisplay();
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
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-xl border shadow-sm overlap-overflow">
      <!-- Header Premium -->
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4 border-b bg-surface">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded class="rounded-lg shadow-sm">
            <VIcon icon="tabler-sofa" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-black mb-0 uppercase leading-none">
              {{ isNewFurniture ? "Añadir Mobiliario" : "Editar Mobiliario" }}
            </h3>
            <span class="text-xs text-disabled font-weight-medium uppercase">Gestiona los activos de la empresa</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeDialog" />
      </VCardTitle>

      <VCardText class="pa-6">
        <VForm @submit.prevent="submitForm">
          <!-- Información Básica -->
          <div class="d-flex align-center gap-2 mb-4">
            <VIcon icon="tabler-info-circle" size="18" color="primary" />
            <span class="text-subtitle-2 font-weight-black uppercase text-primary">Información Básica</span>
          </div>

          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="formData.name"
                label="Nombre del Mobiliario"
                placeholder="Ej: Escritorio ejecutivo, Silla ergonómica..."
                prepend-inner-icon="tabler-tag"
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded-lg"
                :error-messages="formErrors.name"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model.number="formData.cost"
                label="Costo de Adquisición"
                type="number"
                step="0.01"
                min="0"
                prefix="$"
                prepend-inner-icon="tabler-currency-dollar"
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded-lg"
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
                prepend-inner-icon="tabler-calendar"
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded-lg"
                :error-messages="formErrors.acquisition_year"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model.number="formData.annual_depreciation_rate"
                label="Tasa de Depreciación Anual (%)"
                type="number"
                step="0.1"
                min="0"
                max="100"
                suffix="%"
                prepend-inner-icon="tabler-trending-down"
                placeholder="Ej: 10, 15.5, 20..."
                variant="outlined"
                density="compact"
                hide-details="auto"
                class="rounded-lg"
                :error-messages="formErrors.annual_depreciation_rate"
              />
              <div class="text-caption text-disabled mt-2 d-flex align-center">
                <VIcon icon="tabler-help-circle" size="14" class="mr-1" />
                Referencia: Mobiliario (10-20%), Equipos (25-33%)
              </div>
            </VCol>
          </VRow>

          <!-- Cálculos y Valor Actual -->
          <template v-if="formData.cost && formData.acquisition_year && formData.annual_depreciation_rate">
            <div class="d-flex align-center gap-2 mt-8 mb-4">
              <VIcon icon="tabler-calculator" size="18" color="primary" />
              <span class="text-subtitle-2 font-weight-black uppercase text-primary">Resumen de Valoración</span>
            </div>

            <VCard variant="tonal" color="secondary" class="rounded-xl border-dashed overflow-hidden">
              <VCardText class="pa-4 bg-surface-variant bg-opacity-10">
                <VRow>
                  <VCol cols="6" class="pr-2">
                    <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Costo Original</div>
                    <div class="text-h6 font-weight-bold">{{ formatCurrency(formData.cost) }}</div>
                  </VCol>

                  <VCol cols="6" class="text-right border-l-dashed pl-4">
                    <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Valor Actual</div>
                    <div :class="`text-h6 font-weight-black text-${depreciationStatus.color}`">
                      {{ formatCurrency(calculateCurrentValue) }}
                    </div>
                  </VCol>
                </VRow>

                <VDivider class="my-3 border-dashed" />

                <VRow class="text-center">
                  <VCol cols="4">
                    <div class="text-xs text-disabled uppercase font-weight-bold">Uso</div>
                    <div class="text-body-2 font-weight-bold">{{ new Date().getFullYear() - formData.acquisition_year }} años</div>
                  </VCol>
                  <VCol cols="4">
                    <div class="text-xs text-disabled uppercase font-weight-bold">Depreciación</div>
                    <div class="text-body-2 font-weight-bold text-error">{{ depreciationInfo.percentage.toFixed(1) }}%</div>
                  </VCol>
                  <VCol cols="4">
                    <div class="text-xs text-disabled uppercase font-weight-bold">Estado</div>
                    <VChip :color="depreciationStatus.color" size="x-small" label class="font-weight-bold">
                      {{ depreciationStatus.text }}
                    </VChip>
                  </VCol>
                </VRow>

                <div class="mt-4">
                  <VProgressLinear
                    :model-value="depreciationInfo.percentage"
                    :color="depreciationStatus.color"
                    height="10"
                    rounded
                    class="rounded-pill"
                  />
                  <div class="d-flex justify-space-between text-xs font-weight-bold text-disabled mt-1">
                    <span>INICIO</span>
                    <span>FINAL</span>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </template>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4 px-6 border-t bg-surface">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          class="rounded-lg flex-grow-1"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="rounded-lg flex-grow-1"
          :disabled="!formData.name || !formData.cost || !formData.acquisition_year || !formData.annual_depreciation_rate"
        >
          {{ isNewFurniture ? "Crear Activo" : "Guardar Cambios" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.border-dashed {
  border-style: dashed !important;
  opacity: 1;
}

.border-l-dashed {
  border-inline-start: 1px dashed rgba(var(--v-border-color), 0.15);
}

.bg-surface-variant {
  background-color: rgb(var(--v-theme-surface-variant));
}

.leading-none {
  line-height: 1 !important;
}

:deep(.v-field__outline) {
  --v-field-border-opacity: 0.12;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1;
}
</style>
