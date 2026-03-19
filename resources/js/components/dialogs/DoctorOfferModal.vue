<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, defineProps, ref, watch, nextTick } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  loading: { type: Boolean, default: false },
  doctorsData: {
    type: Array,
    default: () => [],
  },
  isEditing: { type: Boolean, default: false },
  doctorsOfferToEdit: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const { mobile } = useDisplay();

// Form data
const doctorsOfferData = ref({
  doctor_id: null,
  start_date: "",
  end_date: "",
  discount: "",
  is_active: true,
});

const isSaving = ref(false);
const formErrors = ref({});

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta de Médico" : "Nueva Oferta de Médico";
});

const selectedDoctorDisplay = computed(() => {
  if (!doctorsOfferData.value.doctor_id) return '';
  const doctor = props.doctorsData.find(d => d.id === doctorsOfferData.value.doctor_id);
  return doctor ? `${doctor.id} - ${doctor.name}` : `ID: ${doctorsOfferData.value.doctor_id}`;
});

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const onCancel = () => {
  resetForm();
  emit("update:modelValue", false);
};

const resetForm = () => {
  doctorsOfferData.value = {
    doctor_id: null,
    start_date: "",
    end_date: "",
    discount: "",
    is_active: true,
  };
  formErrors.value = {};
};

const onSave = async () => {
  if (!doctorsOfferData.value.doctor_id || !doctorsOfferData.value.discount) {
    toast.error("POR FAVOR COMPLETE LOS CAMPOS OBLIGATORIOS");
    return;
  }

  isSaving.value = true;
  formErrors.value = {};

  try {
    const payload = {
      ...doctorsOfferData.value,
      discount: parseFloat(doctorsOfferData.value.discount),
    };

    const url = props.isEditing
      ? `/tpv/promotions/doctor-offer/${props.doctorsOfferToEdit.id}`
      : "/tpv/promotions/doctor-offer";

    const method = props.isEditing ? "put" : "post";

    await axios[method](url, payload);
    toast.success("LA OFERTA SE HA GUARDADO CORRECTAMENTE");
    emit("saved");
    onCancel();
  } catch (error) {
    console.error("Error saving doctor offer:", error);
    if (error.response?.data?.errors) {
      formErrors.value = error.response.data.errors;
      toast.error("POR FAVOR REVISE EL FORMULARIO");
    } else {
      toast.error(error.response?.data?.message || "ERROR AL GUARDAR LA OFERTA");
    }
  } finally {
    isSaving.value = false;
  }
};

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.doctorsOfferToEdit) {
        nextTick(() => {
          doctorsOfferData.value = {
            id: props.doctorsOfferToEdit.id,
            doctor_id: props.doctorsOfferToEdit.doctor_id,
            start_date: formatDateForInput(props.doctorsOfferToEdit.start_date),
            end_date: formatDateForInput(props.doctorsOfferToEdit.end_date),
            discount: props.doctorsOfferToEdit.discount,
            is_active: Boolean(props.doctorsOfferToEdit.is_active),
          };
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
    max-width="700px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'rounded-xl overflow-hidden border-0 elevation-24 text-none'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-stethoscope" color="primary" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              Gestión de beneficios para médicos aliados
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
        <VRow dense>
          <!-- Selector de Médico -->
          <VCol cols="12" md="8">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Médico Aliado</span>
            <VAutocomplete
              v-if="!props.isEditing"
              v-model="doctorsOfferData.doctor_id"
              :items="props.doctorsData"
              :item-title="(item) => `${item.id} - ${item.name}`"
              item-value="id"
              placeholder="BUSCAR MÉDICO POR ID O NOMBRE..."
              variant="outlined"
              density="compact"
              hide-details
              clearable
              :disabled="isSaving"
              class="premium-input-compact mb-4"
              :error="!!formErrors.doctor_id"
            />
            <VTextField
              v-else
              :model-value="selectedDoctorDisplay"
              readonly
              variant="outlined"
              density="compact"
              class="premium-input-compact mb-4"
              bg-color="white"
            />
          </VCol>

          <VCol cols="12" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Estado</span>
            <VSelect
              v-model="doctorsOfferData.is_active"
              :items="[
                { value: true, title: 'ACTIVA' },
                { value: false, title: 'INACTIVA' },
              ]"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="compact"
              hide-details
              class="premium-input-compact mb-4"
              :disabled="isSaving"
            />
          </VCol>

          <VCol cols="12" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">% Descuento</span>
            <AppTextField
              v-model="doctorsOfferData.discount"
              type="number"
              placeholder="0"
              suffix="%"
              min="0"
              max="100"
              variant="outlined"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-percentage"
              class="premium-input-compact"
              :error="!!formErrors.discount"
              :disabled="isSaving"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Inicio</span>
            <AppDateTimePicker
              v-model="doctorsOfferData.start_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-event"
              density="compact"
              hide-details
              class="premium-input-compact"
              :error="!!formErrors.start_date"
              :disabled="isSaving"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Final</span>
            <AppDateTimePicker
              v-model="doctorsOfferData.end_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-off"
              density="compact"
              hide-details
              class="premium-input-compact"
              :error="!!formErrors.end_date"
              :disabled="isSaving"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="isSaving"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg shadow-primary-lg text-button uppercase"
              :loading="isSaving"
              @click="onSave"
            >
              <VIcon icon="tabler-device-floppy" class="me-2" />
              {{ props.isEditing ? "Guardar Cambios" : "Crear Oferta" }}
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

.bg-light {
  background-color: #f8fafc !important;
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
  border-radius: 8px !important;
  min-height: 38px !important;
  background-color: white !important;
}

.premium-input-compact :deep(.v-field__input) {
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-height: 38px !important;
  text-transform: uppercase;
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

.leading-tight {
  line-height: 1.25 !important;
}
</style>
