<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  loading: { type: Boolean, default: false },
  isEditing: { type: Boolean, default: false },
  offerToEdit: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "save", "modal-closed"]);

const defaultOfferData = {
  months_to_expiration: "",
  discount_percentage: "",
  is_active: true,
};

const offerData = ref({ ...defaultOfferData });
const formErrors = ref({});
const { mobile } = useDisplay();

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta" : "Crear Nueva Oferta";
});

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed");
  resetForm();
};

const onSave = () => {
  // Validaciones básicas
  if (!offerData.value.months_to_expiration) {
    formErrors.value.months_to_expiration = ["Este campo es obligatorio"];
    return;
  }

  if (!offerData.value.discount_percentage) {
    formErrors.value.discount_percentage = ["Este campo es obligatorio"];
    return;
  }

  // Preparar datos para enviar
  const saveData = {
    ...offerData.value,
  };
  emit("save", saveData);
};

const resetForm = () => {
  offerData.value = { ...defaultOfferData };
  formErrors.value = {};
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("es-ES");
};

// Watchers
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.offerToEdit) {
        // Cargar datos de edición
        offerData.value = {
          id: props.offerToEdit.id,
          months_to_expiration: props.offerToEdit.months_to_expiration,
          discount_percentage: props.offerToEdit.discount_percentage,
          is_active: Boolean(props.offerToEdit.is_active),
        };
      } else {
        resetForm();
      }
    }
  }
);

// Limpiar errores cuando se cambian los valores
watch(
  () => offerData.value.months_to_expiration,
  () => {
    if (formErrors.value.months_to_expiration) {
      delete formErrors.value.months_to_expiration;
    }
  }
);

watch(
  () => offerData.value.discount_percentage,
  () => {
    if (formErrors.value.discount_percentage) {
      delete formErrors.value.discount_percentage;
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="500px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    @update:model-value="onCancel"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard :loading="props.loading" :class="mobile ? 'rounded-0' : 'rounded-lg overflow-hidden border-0 elevation-12'">
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-clock" size="24" color="white" />
          <span class="text-h6 text-white">{{ dialogTitle }}</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="onCancel" :disabled="props.loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VForm>
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="offerData.months_to_expiration"
                label="Meses para Expirar *"
                type="number"
                min="1"
                max="36"
                variant="outlined"
                :error-messages="formErrors.months_to_expiration"
                placeholder="Ej: 3"
                :disabled="props.loading"
              />
              <div class="text-caption text-disabled mt-1">
                Número de meses antes de la expiración para aplicar la oferta
              </div>
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="offerData.discount_percentage"
                label="Porcentaje de Descuento *"
                type="number"
                min="0.01"
                max="100"
                step="0.01"
                suffix="%"
                variant="outlined"
                :error-messages="formErrors.discount_percentage"
                placeholder="Ej: 15.50"
                :disabled="props.loading"
              />
              <div class="text-caption text-disabled mt-1">
                Porcentaje de descuento a aplicar (0.01% - 100%)
              </div>
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="offerData.is_active"
                label="Oferta Activa"
                :true-value="true"
                :false-value="false"
                color="primary"
                :disabled="props.loading"
              />
            </VCol>
          </VRow>
        </VForm>
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
              :disabled="props.loading"
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
              :loading="props.loading"
              :disabled="
                !offerData.months_to_expiration || !offerData.discount_percentage
              "
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
