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
    max-width="680px"
    width="680px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'rounded overflow-hidden border-0 shadow-xl'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 text-primary font-weight-black">
            <VIcon icon="tabler-building" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                Configuración de Escalas de Descuento
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded"
            @click="onCancel"
            :disabled="isSaving"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Bloque 1: Datos Generales de la Oferta -->
        <div class="mb-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Información de la Empresa</span>
            </div>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-bold text-disabled uppercase">Activa</span>
              <VSwitch
                v-model="companiesOfferData.is_active"
                color="primary"
                hide-details
                density="compact"
                inset
              />
            </div>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background">
            <VRow dense>
              <VCol cols="12">
                <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Empresa Beneficiaria *</span>
                <VAutocomplete
                  v-model="companiesOfferData.company_id"
                  :items="props.companiesData"
                  :item-title="(item) => `${item.id} - ${item.name}`"
                  item-value="id"
                  placeholder="Buscar empresa por ID o nombre..."
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  clearable
                  :disabled="isSaving"
                  class="rounded font-weight-bold"
                  :error="!!formErrors.company_id"
                  :error-messages="formErrors.company_id"
                />
              </VCol>

              <VCol cols="12" sm="6" class="mt-2">
                <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Fecha Inicio *</span>
                <AppDateTimePicker
                  v-model="companiesOfferData.start_date"
                  placeholder="Seleccionar fecha"
                  prepend-inner-icon="tabler-calendar-event"
                  density="compact"
                  hide-details="auto"
                  class="rounded font-weight-bold"
                  :error="!!formErrors.start_date"
                  :error-messages="formErrors.start_date"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </VCol>

              <VCol cols="12" sm="6" class="mt-2">
                <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Fecha Final *</span>
                <AppDateTimePicker
                  v-model="companiesOfferData.end_date"
                  placeholder="Seleccionar fecha"
                  prepend-inner-icon="tabler-calendar-off"
                  density="compact"
                  hide-details="auto"
                  class="rounded font-weight-bold"
                  :error="!!formErrors.end_date"
                  :error-messages="formErrors.end_date"
                  :config="endDateConfig"
                />
              </VCol>
            </VRow>
          </div>
        </div>

        <!-- Bloque 2: Escalas de Descuento -->
        <div class="mb-2">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Escalas de Descuento</span>
              <span class="text-super-xs font-weight-bold text-medium-emphasis">({{ scalesData.length }})</span>
            </div>
            <VBtn
              variant="outlined"
              color="primary"
              size="small"
              class="rounded font-weight-black"
              @click="addScale"
            >
              <VIcon start size="16">tabler-plus</VIcon>
              Añadir Escala
            </VBtn>
          </div>

          <div class="d-flex flex-column gap-2">
            <div
              v-for="(scale, index) in scalesData"
              :key="index"
              class="scale-row pa-3 rounded border bg-var-theme-background"
            >
              <VRow dense class="align-center">
                <VCol cols="12" sm="3" md="4">
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Monto Mín. (USD)</span>
                  <VTextField
                    v-model.number="scale.min_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    class="rounded font-weight-bold"
                  />
                </VCol>

                <VCol cols="12" sm="4" md="4">
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Monto Máx. (USD)</span>
                  <VTextField
                    v-model.number="scale.max_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    class="rounded font-weight-bold"
                  />
                </VCol>

                <VCol cols="9" sm="3" md="3">
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">% Descuento</span>
                  <VTextField
                    v-model.number="scale.discount_percentage"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="0"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    prepend-inner-icon="tabler-percentage"
                    class="rounded font-weight-black"
                  />
                </VCol>

                <VCol cols="3" sm="2" md="1" class="d-flex justify-end pt-5">
                  <IconBtn
                    v-if="scalesData.length > 1"
                    color="error"
                    size="small"
                    @click="removeScale(index)"
                    :disabled="isSaving"
                  >
                    <VIcon icon="tabler-trash" size="18" />
                    <VTooltip activator="parent">Eliminar Escala</VTooltip>
                  </IconBtn>
                </VCol>
              </VRow>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones del Modal -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-bold rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="isSaving"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              prepend-icon="tabler-device-floppy"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="isSaving"
              @click="onSave"
            >
              {{ props.isEditing ? 'Guardar Cambios' : 'Crear Oferta' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.scale-row {
  border-color: rgba(var(--v-border-color), 0.12) !important;
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  border-radius: 5px !important;
}
</style>
