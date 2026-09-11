<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch, nextTick } from "vue";
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

const endDateConfig = computed(() => ({
  altFormat: "Y-m-d",
  dateFormat: "Y-m-d",
  minDate: doctorsOfferData.value.start_date || undefined,
}));

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
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'rounded overflow-hidden border-0 shadow-xl bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="38"
            class="me-3 elevation-1 text-primary font-weight-black"
          >
            <VIcon
              icon="tabler-stethoscope"
              size="22"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Gestión de Beneficios para Médicos Aliados
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
        <!-- Bloque 1: Médico Aliado -->
        <div class="mb-4">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Médico Aliado</span>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background">
            <!-- Modo Edición: Resumen de Médico -->
            <div v-if="props.isEditing" class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-2">
                <span class="text-xs font-weight-bold text-primary bg-primary-lighten-5 px-2 py-0-5 rounded">
                  ID #{{ doctorsOfferData.doctor_id }}
                </span>
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase">
                  {{ selectedDoctorDisplay }}
                </span>
              </div>
            </div>

            <!-- Modo Creación: Autocomplete -->
            <div v-else>
              <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Seleccionar Médico *</span>
              <VAutocomplete
                v-model="doctorsOfferData.doctor_id"
                :items="props.doctorsData"
                :item-title="(item) => `${item.id} - ${item.name}`"
                item-value="id"
                placeholder="Buscar médico por ID o nombre..."
                variant="outlined"
                density="compact"
                hide-details="auto"
                clearable
                :disabled="isSaving"
                class="rounded font-weight-bold"
                :error="!!formErrors.doctor_id"
                :error-messages="formErrors.doctor_id"
              />
            </div>
          </div>
        </div>

        <!-- Bloque 2: Parámetros del Beneficio -->
        <div class="mb-2">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Parámetros del Beneficio</span>
            </div>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-bold text-high-emphasis uppercase">Activa</span>
              <VSwitch
                v-model="doctorsOfferData.is_active"
                color="primary"
                hide-details
                density="compact"
                inset
              />
            </div>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background">
            <VRow dense>
              <VCol cols="12" sm="4">
                <div class="mb-2 mb-sm-0">
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">% Descuento *</span>
                  <VTextField
                    v-model.number="doctorsOfferData.discount"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="0.00"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    prepend-inner-icon="tabler-percentage"
                    class="rounded font-weight-black"
                    :error="!!formErrors.discount"
                    :error-messages="formErrors.discount"
                    :disabled="isSaving"
                  />
                </div>
              </VCol>

              <VCol cols="12" sm="4">
                <div class="mb-2 mb-sm-0">
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Vigencia Inicio</span>
                  <AppDateTimePicker
                    v-model="doctorsOfferData.start_date"
                    placeholder="Seleccionar fecha"
                    prepend-inner-icon="tabler-calendar-event"
                    density="compact"
                    hide-details="auto"
                    class="rounded font-weight-bold"
                    :error="!!formErrors.start_date"
                    :error-messages="formErrors.start_date"
                    :disabled="isSaving"
                    :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                  />
                </div>
              </VCol>

              <VCol cols="12" sm="4">
                <div>
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Vigencia Cierre</span>
                  <AppDateTimePicker
                    v-model="doctorsOfferData.end_date"
                    placeholder="Seleccionar fecha"
                    prepend-inner-icon="tabler-calendar-off"
                    density="compact"
                    hide-details="auto"
                    class="rounded font-weight-bold"
                    :error="!!formErrors.end_date"
                    :error-messages="formErrors.end_date"
                    :disabled="isSaving"
                    :config="endDateConfig"
                  />
                </div>
              </VCol>
            </VRow>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
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
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
}
</style>
