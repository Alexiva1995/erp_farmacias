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
              icon="tabler-hourglass-high"
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
                Incentivo por Productos Próximos a Caducar • Barrio Sucre
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
            :disabled="props.loading"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- Parámetros de la Oferta -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Parámetros de Aplicación</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm mb-0"
        >
          <VRow dense>
            <VCol cols="12">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Plazo de Vencimiento Crítico</span>
                <VTextField
                  v-model="offerData.months_to_expiration"
                  type="number"
                  min="1"
                  max="60"
                  placeholder="CANTIDAD DE MESES"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-calendar-time"
                  class="rounded-lg font-weight-black"
                  :error="!!formErrors.months_to_expiration"
                  :disabled="props.loading"
                />
                <p class="text-super-xs font-weight-bold text-disabled uppercase mt-2 mb-0 italic">
                  * Esta oferta se aplicará a productos con menos de este tiempo de vida útil.
                </p>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4 mb-sm-0">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">% Descuento Sugerido</span>
                <VTextField
                  v-model="offerData.discount_percentage"
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  placeholder="0.00"
                  suffix="%"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-percentage"
                  class="rounded-lg font-weight-black"
                  :error="!!formErrors.discount_percentage"
                  :disabled="props.loading"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Estado de la Regla</span>
                <VSelect
                  v-model="offerData.is_active"
                  :items="[
                    { value: true, title: 'REGLA ACTIVA' },
                    { value: false, title: 'REGLA INACTIVA' },
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg font-weight-black"
                  :disabled="props.loading"
                />
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Mensaje Informativo -->
        <div class="mt-6 pa-4 rounded-xl bg-primary bg-opacity-10 border-dashed-2 d-flex align-center gap-4">
          <VAvatar
            color="primary"
            variant="tonal"
            size="40"
            class="rounded-lg"
          >
            <VIcon
              icon="tabler-info-circle-filled"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1">Información de Operación</span>
            <p class="text-super-xs text-medium-emphasis mb-0 leading-tight">
              El sistema identificará automáticamente los lotes de productos que entren en este rango de tiempo para aplicar el incentivo en el punto de venta.
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
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
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
