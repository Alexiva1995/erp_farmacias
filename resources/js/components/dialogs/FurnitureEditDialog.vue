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
    <VCard v-if="formData" class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-sofa" color="primary" size="24" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              {{ isNewFurniture ? "Añadir Mobiliario" : "Editar Mobiliario" }}
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Gestión de Activos Fijos • Mobiliario
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <VForm @submit.prevent="submitForm">
          <!-- Sección: Información Básica -->
          <div class="d-flex align-center gap-2 mb-3">
            <div class="header-indicator primary shadow-sm" />
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información Básica</span>
          </div>

          <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-6">
            <VRow>
              <VCol cols="12">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Nombre del Activo</span>
                <VTextField
                  v-model="formData.name"
                  placeholder="Ej: Escritorio ejecutivo, Silla ergonómica..."
                  prepend-inner-icon="tabler-tag"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :error-messages="formErrors.name"
                />
              </VCol>

              <VCol cols="12" md="6">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Costo de Adquisición</span>
                <VTextField
                  v-model.number="formData.cost"
                  type="number"
                  step="0.01"
                  min="0"
                  prefix="$"
                  prepend-inner-icon="tabler-currency-dollar"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :error-messages="formErrors.cost"
                />
              </VCol>

              <VCol cols="12" md="6">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Año de Adquisición</span>
                <VSelect
                  v-model="formData.acquisition_year"
                  :items="props.acquisitionYears"
                  item-title="title"
                  item-value="value"
                  prepend-inner-icon="tabler-calendar"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :error-messages="formErrors.acquisition_year"
                />
              </VCol>

              <VCol cols="12">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Tasa de Depreciación Anual</span>
                <VTextField
                  v-model.number="formData.annual_depreciation_rate"
                  type="number"
                  step="0.1"
                  min="0"
                  max="100"
                  suffix="%"
                  prepend-inner-icon="tabler-trending-down"
                  placeholder="Ej: 10, 15.5, 20..."
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :error-messages="formErrors.annual_depreciation_rate"
                />
                <div class="text-super-xs text-disabled mt-2 d-flex align-center gap-1">
                  <VIcon icon="tabler-help-circle" size="14" />
                  Referencia: Mobiliario (10-20%), Equipos (25-33%)
                </div>
              </VCol>
            </VRow>
          </VCard>

          <!-- Sección: Resumen de Valoración -->
          <template v-if="formData.cost && formData.acquisition_year && formData.annual_depreciation_rate">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator secondary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Resumen de Valoración</span>
            </div>

            <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
              <VRow class="mb-4">
                <VCol cols="6">
                  <div class="pa-3 bg-light rounded-xl border-dashed-2 text-center">
                    <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-1">Costo Original</span>
                    <div class="text-h6 font-weight-black text-high-emphasis">{{ formatCurrency(formData.cost) }}</div>
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="pa-3 bg-light rounded-xl border-dashed-2 text-center">
                    <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1 letter-spacing-1">Valor Actual</span>
                    <div :class="`text-h6 font-weight-black text-${depreciationStatus.color}`">
                      {{ formatCurrency(calculateCurrentValue) }}
                    </div>
                  </div>
                </VCol>
              </VRow>

              <VDivider class="mb-4" />

              <VRow class="text-center mb-4">
                <VCol cols="4">
                  <div class="text-super-xs text-disabled uppercase font-weight-black letter-spacing-1 mb-1">Años de Uso</div>
                  <div class="text-subtitle-1 font-weight-black">{{ new Date().getFullYear() - formData.acquisition_year }}</div>
                </VCol>
                <VCol cols="4">
                  <div class="text-super-xs text-disabled uppercase font-weight-black letter-spacing-1 mb-1">Depreciación</div>
                  <div class="text-subtitle-1 font-weight-black text-error">{{ depreciationInfo.percentage.toFixed(1) }}%</div>
                </VCol>
                <VCol cols="4">
                  <div class="text-super-xs text-disabled uppercase font-weight-black letter-spacing-1 mb-1">Estado</div>
                  <VChip :color="depreciationStatus.color" size="small" class="font-weight-black rounded-lg shadow-sm">
                    {{ depreciationStatus.text }}
                  </VChip>
                </VCol>
              </VRow>

              <div class="pa-3 bg-light rounded-xl border-dashed-2">
                <VProgressLinear
                  :model-value="depreciationInfo.percentage"
                  :color="depreciationStatus.color"
                  height="10"
                  rounded
                  class="rounded-pill mb-1"
                />
                <div class="d-flex justify-space-between text-super-xs font-weight-black text-disabled">
                  <span>INICIO</span>
                  <span>FINAL</span>
                </div>
              </div>
            </VCard>
          </template>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :disabled="!formData.name || !formData.cost || !formData.acquisition_year || !formData.annual_depreciation_rate"
              @click="submitForm"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              {{ isNewFurniture ? "Crear Activo" : "Guardar Cambios" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

:deep(.v-field__outline) {
  --v-field-border-opacity: 0.12;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1;
}
</style>
