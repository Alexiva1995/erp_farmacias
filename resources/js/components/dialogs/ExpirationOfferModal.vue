<script setup>
import { computed, ref, watch, nextTick } from "vue";
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

const { mobile } = useDisplay();

const defaultOfferData = {
  months_to_expiration: "",
  discount_percentage: "",
  is_active: true,
};

const offerData = ref({ ...defaultOfferData });
const formErrors = ref({});
const isSaving = ref(false);

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta por Vencimiento" : "Nueva Oferta por Vencimiento";
});

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed");
  resetForm();
};

const onSave = () => {
  formErrors.value = {};

  if (!offerData.value.months_to_expiration) {
    formErrors.value.months_to_expiration = ["LOS MESES SON REQUERIDOS"];
  }

  if (!offerData.value.discount_percentage) {
    formErrors.value.discount_percentage = ["EL DESCUENTO ES REQUERIDO"];
  }

  if (Object.keys(formErrors.value).length > 0) {
    return;
  }

  isSaving.value = true;
  emit("save", { ...offerData.value });
  // El padre maneja el cierre y la carga, pero reseteamos aquí por seguridad ante errores.
  isSaving.value = false;
};

const resetForm = () => {
  offerData.value = { ...defaultOfferData };
  formErrors.value = {};
};

// Watchers
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.offerToEdit) {
        nextTick(() => {
          offerData.value = {
            id: props.offerToEdit.id,
            months_to_expiration: props.offerToEdit.months_to_expiration,
            discount_percentage: props.offerToEdit.discount_percentage,
            is_active: Boolean(props.offerToEdit.is_active),
          };
        });
      } else {
        resetForm();
      }
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
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
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-hourglass-high" color="primary" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              Incentivo por productos próximos a caducar
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
            :disabled="props.loading"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light">
        <VRow dense>
          <VCol cols="12">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Plazo de Vencimiento</span>
            <AppTextField
              v-model="offerData.months_to_expiration"
              type="number"
              min="1"
              max="60"
              placeholder="CANTIDAD DE MESES"
              variant="outlined"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-calendar-time"
              class="premium-input-compact mb-2"
              :error="!!formErrors.months_to_expiration"
              :disabled="props.loading"
            />
            <div class="text-super-xs font-weight-bold text-disabled uppercase ms-1 mb-4">
              Esta oferta se aplicará a productos con menos de este tiempo de vida útil.
            </div>
          </VCol>

          <VCol cols="12" sm="6">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Porcentaje de Descuento</span>
            <AppTextField
              v-model="offerData.discount_percentage"
              type="number"
              step="0.01"
              min="0"
              max="100"
              placeholder="0.00"
              suffix="%"
              variant="outlined"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-percentage"
              class="premium-input-compact mb-4"
              :error="!!formErrors.discount_percentage"
              :disabled="props.loading"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Estado Operativo</span>
            <VSelect
              v-model="offerData.is_active"
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
              :disabled="props.loading"
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
              :disabled="props.loading"
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
              :loading="props.loading"
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
