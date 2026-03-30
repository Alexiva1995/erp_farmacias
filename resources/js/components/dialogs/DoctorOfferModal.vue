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
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-stethoscope"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Gestión de Beneficios para Médicos Aliados • Barrio Sucre
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="isSaving"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- Configuración de la Oferta -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración del Beneficio</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm mb-0"
        >
          <VRow dense>
            <!-- Selector de Médico -->
            <VCol
              cols="12"
              md="8"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Médico Aliado</span>
                <VAutocomplete
                  v-if="!props.isEditing"
                  v-model="doctorsOfferData.doctor_id"
                  :items="props.doctorsData"
                  :item-title="(item) => `${item.id} - ${item.name}`"
                  item-value="id"
                  placeholder="BUSCAR MÉDICO POR ID O NOMBRE..."
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  clearable
                  :disabled="isSaving"
                  class="rounded-lg"
                  :error="!!formErrors.doctor_id"
                />
                <VTextField
                  v-else
                  :model-value="selectedDoctorDisplay"
                  readonly
                  variant="flat"
                  density="comfortable"
                  bg-color="grey-lighten-4"
                  class="rounded-lg font-weight-bold"
                  hide-details
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Estado de la Oferta</span>
                <VSelect
                  v-model="doctorsOfferData.is_active"
                  :items="[
                    { value: true, title: 'OBSEQUIO ACTIVO' },
                    { value: false, title: 'OBSEQUIO INACTIVO' },
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg font-weight-black"
                  :disabled="isSaving"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <div class="mb-4 mb-md-0">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">% Descuento</span>
                <VTextField
                  v-model="doctorsOfferData.discount"
                  type="number"
                  placeholder="0.00"
                  suffix="%"
                  min="0"
                  max="100"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-percentage"
                  class="rounded-lg font-weight-black"
                  :error="!!formErrors.discount"
                  :disabled="isSaving"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <div class="mb-4 mb-md-0">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Vigencia Inicio</span>
                <AppDateTimePicker
                  v-model="doctorsOfferData.start_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-event"
                  density="comfortable"
                  hide-details
                  class="rounded-lg"
                  :error="!!formErrors.start_date"
                  :disabled="isSaving"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Vigencia Cierre</span>
                <AppDateTimePicker
                  v-model="doctorsOfferData.end_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-off"
                  density="comfortable"
                  hide-details
                  class="rounded-lg"
                  :error="!!formErrors.end_date"
                  :disabled="isSaving"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Mensaje Informativo -->
        <div class="mt-6 pa-4 rounded-xl bg-info bg-opacity-10 border-dashed-2 d-flex align-center gap-4">
          <VAvatar
            color="info"
            variant="tonal"
            size="40"
            class="rounded-lg"
          >
            <VIcon
              icon="tabler-info-circle"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <span class="text-xs font-weight-black text-info uppercase letter-spacing-1 mb-1">Nota de Gestión</span>
            <p class="text-super-xs text-medium-emphasis mb-0 leading-tight">
              Los beneficios configurados se aplicarán automáticamente a las órdenes prescritas por el médico seleccionado durante el periodo de vigencia.
            </p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="isSaving"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="isSaving"
              @click="onSave"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
              />
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
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
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
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.italic {
  font-style: italic;
}
</style>
