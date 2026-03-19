<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, defineProps, ref, watch } from "vue";
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

// Form data
const doctorsOfferData = ref({
  doctor_id: null,
  start_date: "",
  end_date: "",
  discount: "", // Nuevo campo descuento
  is_active: true,
});

const loading = ref(false);
const formErrors = ref({});
const { mobile } = useDisplay();

// Opciones para el select de estatus
const statusOptions = [
  { title: "Activa", value: true },
  { title: "Inactiva", value: false },
];

// Computed properties
const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta" : "Crear Nueva Oferta";
});

// Resetear formulario
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

const onCancel = () => {
  resetForm();
  emit("update:modelValue", false);
};

const onSave = async () => {
  // Validación simple del lado del cliente antes de enviar
  if (!doctorsOfferData.value.doctor_id || !doctorsOfferData.value.discount) {
    toast.error("Por favor complete los campos obligatorios");
    return;
  }

  loading.value = true;
  formErrors.value = {}; // Limpiar errores previos

  try {
    // Preparamos los datos. Aseguramos que el descuento sea numérico.
    const payload = {
      ...doctorsOfferData.value,
      discount: parseFloat(doctorsOfferData.value.discount),
    };

    const url = props.isEditing
      ? `/tpv/promotions/doctor-offer/${props.doctorsOfferToEdit.id}`
      : "/tpv/promotions/doctor-offer";

    const method = props.isEditing ? "put" : "post";

    await axios[method](url, payload);

    toast.success("La oferta se ha guardado correctamente");

    emit("saved");
    onCancel();
  } catch (error) {
    console.error("Error al guardar la oferta:", error);

    if (error.response?.data?.errors) {
      formErrors.value = error.response.data.errors;
      // Mostrar toast solo si hay error general, si es de validación ya sale en el input
      const errors = Object.values(error.response.data.errors).flat();
      if (errors.length > 0) toast.error("Por favor revise el formulario");
    } else {
      toast.error(
        error.response?.data?.message || "Error al guardar la oferta"
      );
    }
  } finally {
    loading.value = false;
  }
};

// Watchers
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible && props.isEditing && props.doctorsOfferToEdit) {
      // Cargar datos para edición
      doctorsOfferData.value = {
        id: props.doctorsOfferToEdit.id,
        doctor_id: props.doctorsOfferToEdit.doctor_id,
        start_date: formatDateForInput(props.doctorsOfferToEdit.start_date),
        end_date: formatDateForInput(props.doctorsOfferToEdit.end_date),
        // Asumimos que la API ahora devuelve 'discount' en el objeto principal
        discount: props.doctorsOfferToEdit.discount,
        is_active: Boolean(props.doctorsOfferToEdit.is_active),
      };
    } else if (isVisible) {
      resetForm();
    }
  }
);

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};
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
    @update:model-value="onCancel"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard :loading="loading" :class="mobile ? 'rounded-0' : 'rounded-lg overflow-hidden border-0 elevation-12'">
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-stethoscope" size="24" color="white" />
          <span class="text-h6 text-white">{{ dialogTitle }}</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="onCancel" :disabled="loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <p class="text-h6 font-weight-medium mb-4">Información de la Oferta</p>

        <VRow>
          <VCol cols="12">
            <VSelect
              v-model="doctorsOfferData.doctor_id"
              label="Seleccionar Médico"
              :items="props.doctorsData"
              :item-title="(item) => `${item.id} - ${item.name}`"
              item-value="id"
              placeholder="Buscar médico..."
              variant="outlined"
              :error-messages="formErrors.doctor_id"
              clearable
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="doctorsOfferData.start_date"
              label="Fecha de Inicio"
              type="date"
              placeholder="YYYY-MM-DD"
              variant="outlined"
              :error-messages="formErrors.start_date"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="doctorsOfferData.end_date"
              label="Fecha de Finalización"
              type="date"
              placeholder="YYYY-MM-DD"
              variant="outlined"
              :error-messages="formErrors.end_date"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="doctorsOfferData.discount"
              label="Porcentaje de Descuento"
              type="number"
              placeholder="0"
              suffix="%"
              min="0"
              max="100"
              variant="outlined"
              :error-messages="formErrors.discount"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              v-model="doctorsOfferData.is_active"
              label="Estatus"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              placeholder="Seleccione un estatus"
              variant="outlined"
              :error-messages="formErrors.is_active"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="onCancel"
              :disabled="loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              :loading="loading"
              @click="onSave"
            >
              {{ props.isEditing ? "Actualizar" : "Guardar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
