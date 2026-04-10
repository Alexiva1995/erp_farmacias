<script setup>
import { computed, ref, watch, nextTick } from "vue";
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  loading: { type: Boolean, default: false },
  companiesData: {
    type: Array,
    default: () => [],
  },
  isEditing: { type: Boolean, default: false },
  companiesOfferToEdit: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const { mobile } = useDisplay();

// Form data
const companiesOfferData = ref({
  company_id: null,
  start_date: "",
  end_date: "",
  is_active: true,
});

const scalesData = ref([
  {
    min_amount: "",
    max_amount: "",
    discount_percentage: "",
  },
]);

const isSaving = ref(false);
const formErrors = ref({});

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta de Empresa" : "Nueva Oferta de Empresa";
});

const onCancel = () => {
  resetForm();
  emit("update:modelValue", false);
};

const resetForm = () => {
  companiesOfferData.value = {
    company_id: null,
    start_date: "",
    end_date: "",
    is_active: true,
  };
  scalesData.value = [
    {
      min_amount: "",
      max_amount: "",
      discount_percentage: "",
    },
  ];
  formErrors.value = {};
};

const addScale = () => {
  scalesData.value.push({
    min_amount: "",
    max_amount: "",
    discount_percentage: "",
  });
};

const removeScale = (index) => {
  if (scalesData.value.length > 1) {
    scalesData.value.splice(index, 1);
  }
};

const validateScales = () => {
  const errors = [];
  for (let i = 0; i < scalesData.value.length; i++) {
    const scale = scalesData.value[i];
    if (!scale.min_amount || !scale.max_amount || !scale.discount_percentage) {
      errors.push(`LA ESCALA ${i + 1} TIENE CAMPOS VACÍOS`);
    }
    if (parseFloat(scale.min_amount) >= parseFloat(scale.max_amount)) {
      errors.push(`EN LA ESCALA ${i + 1}, EL MONTO MÁXIMO DEBE SER MAYOR AL MÍNIMO`);
    }
  }
  return errors;
};

const onSave = async () => {
  // Validar datos básicos
  if (!companiesOfferData.value.company_id || !companiesOfferData.value.start_date || !companiesOfferData.value.end_date) {
    toast.error("POR FAVOR COMPLETA LOS DATOS GENERALES");
    return;
  }

  const scaleErrors = validateScales();
  if (scaleErrors.length > 0) {
    scaleErrors.forEach((error) => toast.error(error));
    return;
  }

  isSaving.value = true;
  try {
    const payload = {
      ...companiesOfferData.value,
      scales: scalesData.value.map((scale) => ({
        min_amount: parseFloat(scale.min_amount),
        max_amount: parseFloat(scale.max_amount),
        discount_percentage: parseFloat(scale.discount_percentage),
      })),
    };

    const url = props.isEditing
      ? `/tpv/promotions/company-offer/${props.companiesOfferToEdit.id}`
      : "/tpv/promotions/company-offer";

    const method = props.isEditing ? "put" : "post";

    await axios[method](url, payload);
    toast.success("LA OFERTA SE HA GUARDADO CORRECTAMENTE");
    emit("saved");
    onCancel();
  } catch (error) {
    console.error("Error saving company offer:", error);
    if (error.response?.data?.errors) {
      formErrors.value = error.response.data.errors;
      Object.values(error.response.data.errors).flat().forEach((err) => toast.error(err));
    } else {
      toast.error(error.response?.data?.message || "ERROR AL GUARDAR LA OFERTA");
    }
  } finally {
    isSaving.value = false;
  }
};

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// Configuración dinámica para la fecha de fin
const endDateConfig = computed(() => ({
  altFormat: "Y-m-d",
  dateFormat: "Y-m-d",
  minDate: companiesOfferData.value.start_date || undefined,
}));

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.companiesOfferToEdit) {
        nextTick(() => {
          companiesOfferData.value = {
            id: props.companiesOfferToEdit.id,
            company_id: props.companiesOfferToEdit.company_id,
            start_date: formatDateForInput(props.companiesOfferToEdit.start_date),
            end_date: formatDateForInput(props.companiesOfferToEdit.end_date),
            is_active: Boolean(props.companiesOfferToEdit.is_active),
          };

          if (props.companiesOfferToEdit.scales?.length > 0) {
            scalesData.value = props.companiesOfferToEdit.scales.map((scale) => ({
              id: scale.id,
              min_amount: scale.min_amount,
              max_amount: scale.max_amount,
              discount_percentage: scale.discount_percentage,
            }));
          }
        });
      } else {
        resetForm();
      }
    }
  },
  { immediate: true }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="850px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'rounded-xl overflow-hidden border-0 elevation-24'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2 text-primary font-weight-black">
            <VIcon icon="tabler-building" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
              Configuración de Escalas de Descuento
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="isSaving"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light">
          <!-- Datos Generales -->
          <div class="d-flex align-center gap-2 mb-4">
            <div class="header-indicator primary shadow-sm"></div>
            <span class="text-subtitle-2 font-weight-black text-primary uppercase letter-spacing-1">Información General</span>
          </div>

          <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border mb-8">
            <VRow dense>
              <VCol cols="12" md="8">
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Empresa</span>
                <VAutocomplete
                  v-model="companiesOfferData.company_id"
                  :items="props.companiesData"
                  :item-title="(item) => `${item.id} - ${item.name}`"
                  item-value="id"
                  placeholder="BUSCAR EMPRESA..."
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  clearable
                  :disabled="isSaving"
                  class="premium-input-compact mb-4"
                  :error="!!formErrors.company_id"
                  :error-messages="formErrors.company_id"
                />
              </VCol>

              <VCol cols="12" md="4">
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Estado</span>
                <VSelect
                  v-model="companiesOfferData.is_active"
                  :items="[
                    { value: true, title: 'ACTIVA' },
                    { value: false, title: 'INACTIVA' },
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="premium-input-compact mb-4"
                  :error="!!formErrors.is_active"
                  :error-messages="formErrors.is_active"
                />
              </VCol>

              <VCol cols="12" sm="6">
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Inicio</span>
                <AppDateTimePicker
                  v-model="companiesOfferData.start_date"
                  placeholder="YYYY-MM-DD"
                  prepend-inner-icon="tabler-calendar-event"
                  density="compact"
                  hide-details="auto"
                  class="premium-input-compact"
                  :error="!!formErrors.start_date"
                  :error-messages="formErrors.start_date"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </VCol>

              <VCol cols="12" sm="6">
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Final</span>
                <AppDateTimePicker
                  v-model="companiesOfferData.end_date"
                  placeholder="YYYY-MM-DD"
                  prepend-inner-icon="tabler-calendar-off"
                  density="compact"
                  hide-details="auto"
                  class="premium-input-compact"
                  :error="!!formErrors.end_date"
                  :error-messages="formErrors.end_date"
                  :config="endDateConfig"
                />
              </VCol>
            </VRow>
          </VCard>

          <!-- Tablero de Escalas -->
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center gap-2">
              <div class="header-indicator secondary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-secondary uppercase letter-spacing-1">Tabla de Escalas</span>
            </div>
            <VBtn
              prepend-icon="tabler-plus"
              color="primary"
              variant="tonal"
              size="small"
              class="rounded-lg font-weight-black shadow-sm"
              @click="addScale"
            >
              Añadir Escala
            </VBtn>
          </div>

          <div v-for="(scale, index) in scalesData" :key="index" class="scale-premium-row pa-4 mb-4 rounded-xl bg-white elevation-1 border animate-in">
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="d-flex align-center gap-2 font-weight-black text-primary uppercase">
                <VAvatar size="24" color="primary" variant="flat" class="text-xs">
                  {{ index + 1 }}
                </VAvatar>
                <span class="text-super-xs letter-spacing-1">Configuración de Escala</span>
              </div>
              <VBtn
                v-if="scalesData.length > 1"
                icon="tabler-trash"
                variant="tonal"
                color="error"
                size="28"
                class="rounded-lg shadow-sm"
                @click="removeScale(index)"
              />
            </div>
            
            <VRow dense>
              <VCol cols="12" sm="4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block ms-1">Inversión Mín. (USD)</span>
                <AppTextField
                  v-model="scale.min_amount"
                  type="number"
                  placeholder="0.00"
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block ms-1">Inversión Máx. (USD)</span>
                <AppTextField
                  v-model="scale.max_amount"
                  type="number"
                  placeholder="0.00"
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block ms-1">% Descuento</span>
                <AppTextField
                  v-model="scale.discount_percentage"
                  type="number"
                  placeholder="0"
                  suffix="%"
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-input-compact font-weight-black"
                />
              </VCol>
            </VRow>
          </div>
          
          <VAlert
            v-if="scalesData.length > 0"
            variant="tonal"
            color="info"
            icon="tabler-info-circle"
            class="rounded-lg mt-4 border-0"
          >
            <span class="text-super-xs uppercase font-weight-black opacity-75 d-block mb-1">Nota sobre escalas</span>
            <span class="text-caption">Define el porcentaje de descuento que se aplicará según el monto total de la venta para esta empresa.</span>
          </VAlert>
      </VCardText>

      <VDivider />

      <!-- Acciones Únicas -->
      <VCardActions class="pa-6 bg-light">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg text-button uppercase shadow-sm"
              @click="onCancel"
              :disabled="isSaving"
            >
              Cancelar Operación
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="48"
              prepend-icon="tabler-device-floppy"
              class="font-weight-black rounded-lg shadow-primary-lg text-button uppercase"
              :loading="isSaving"
              @click="onSave"
            >
              {{ props.isEditing ? 'Actualizar Oferta' : 'Guardar Nueva Oferta' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.premium-input-compact :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15 !important;
  color: rgba(var(--v-border-color), 1) !important;
}

.premium-input-compact :deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1 !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.premium-input-compact :deep(.v-field) {
  border-radius: 10px !important;
  min-height: 42px !important;
  background-color: white !important;
  transition: all 0.2s ease;
}

.premium-input-compact :deep(.v-field:hover) {
  background-color: #fcfcfc !important;
}

.premium-input-compact :deep(.v-field__input) {
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-height: 42px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.shadow-primary-lg {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.25) !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.scale-premium-row {
  border-color: rgba(var(--v-border-color), 0.1) !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.scale-premium-row:hover {
  border-color: rgba(var(--v-theme-primary), 0.4) !important;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.06) !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

.animate-in {
  animation: slideBottom 0.3s ease-out backwards;
}

@keyframes slideBottom {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.4;
}
</style>
