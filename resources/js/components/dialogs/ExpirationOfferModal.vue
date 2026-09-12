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
    formErrors.value.months_to_expiration = ["Los meses son requeridos"];
  }

  if (!offerData.value.discount_percentage) {
    formErrors.value.discount_percentage = ["El descuento es requerido"];
  }

  if (Object.keys(formErrors.value).length > 0) {
    return;
  }

  isSaving.value = true;
  emit("save", { ...offerData.value });
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
              icon="tabler-hourglass-high"
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
                Incentivo por Productos Próximos a Caducar
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
            :disabled="props.loading"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Bloque 1: Parámetros de la Oferta -->
        <div class="mb-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Parámetros de Aplicación</span>
            </div>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-bold text-high-emphasis uppercase">Activa</span>
              <VSwitch
                v-model="offerData.is_active"
                color="primary"
                hide-details
                density="compact"
                inset
              />
            </div>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background">
            <VRow dense>
              <VCol cols="12" sm="6">
                <div class="mb-2 mb-sm-0">
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">Plazo de Vencimiento *</span>
                  <VTextField
                    v-model.number="offerData.months_to_expiration"
                    type="number"
                    min="1"
                    max="60"
                    placeholder="Cantidad de meses"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    prepend-inner-icon="tabler-calendar-time"
                    suffix="Meses"
                    class="rounded font-weight-bold"
                    :error="!!formErrors.months_to_expiration"
                    :error-messages="formErrors.months_to_expiration"
                    :disabled="props.loading"
                  />
                  <span class="text-super-xs font-weight-medium text-disabled mt-1 d-block">
                    Aplica a productos con este tiempo o menos de vida útil.
                  </span>
                </div>
              </VCol>

              <VCol cols="12" sm="6">
                <div>
                  <span class="text-super-xs font-weight-bold text-high-emphasis uppercase mb-1 d-block">% Descuento *</span>
                  <VTextField
                    v-model.number="offerData.discount_percentage"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    placeholder="0.00"
                    suffix="%"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    prepend-inner-icon="tabler-percentage"
                    class="rounded font-weight-black"
                    :error="!!formErrors.discount_percentage"
                    :error-messages="formErrors.discount_percentage"
                    :disabled="props.loading"
                  />
                  <span class="text-super-xs font-weight-medium text-disabled mt-1 d-block">
                    Porcentaje de rebaja aplicado automáticamente en TPV.
                  </span>
                </div>
              </VCol>
            </VRow>
          </div>
        </div>

        <!-- Mensaje Informativo -->
        <div class="pa-3 rounded border bg-var-theme-background d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            class="rounded"
          >
            <VIcon
              icon="tabler-info-circle-filled"
              size="20"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-tight">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-0-5">Regla Dinámica de Inventario</span>
            <p class="text-super-xs text-medium-emphasis mb-0">
              El sistema identificará automáticamente los lotes de productos que entren en este rango de tiempo para aplicar el incentivo en el punto de venta.
            </p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
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
              variant="outlined"
              height="44"
              block
              class="font-weight-black rounded text-button uppercase"
              @click="onCancel"
              :disabled="props.loading"
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
              height="44"
              block
              class="font-weight-black rounded shadow-primary text-button uppercase"
              :loading="props.loading"
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

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  border-radius: 5px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.mb-0-5 {
  margin-bottom: 2px !important;
}

.gap-1-5 {
  gap: 6px !important;
}

.gap-3 {
  gap: 12px !important;
}
</style>
