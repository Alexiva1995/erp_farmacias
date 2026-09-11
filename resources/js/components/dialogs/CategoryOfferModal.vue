<script setup>
import { computed, watch, ref, nextTick } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  formData: { 
    type: Object, 
    default: () => ({}) 
  },
  loading: { type: Boolean, default: false },
  categoriesData: {
    type: Array,
    default: () => [],
  },
  formErrors: { 
    type: Object, 
    default: () => ({}) 
  },
  isEditing: { type: Boolean, default: false },
  categoryOfferToEdit: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "save", "modal-closed"]);

const { mobile } = useDisplay();

const defaultCategoryOffer = {
  category_id: null,
  discount_percentage: null,
  start_date: "",
  end_date: "",
  is_active: true,
};

const localFormData = ref({ ...defaultCategoryOffer });

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta" : "Nueva Oferta por Categoría";
});

// Computed para el display de la categoría seleccionada
const selectedCategoryDisplay = computed(() => {
  if (!localFormData.value.category_id) return '';
  const category = props.categoriesData.find(c => c.id === localFormData.value.category_id);
  return category ? category.name : '';
});

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

function onSave() {
  const dataToSend = {
    ...localFormData.value,
    discount_percentage: parseFloat(localFormData.value.discount_percentage) || 0,
    is_active: Boolean(localFormData.value.is_active)
  };
  
  emit("save", dataToSend);
}

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed");
};

// Configuración dinámica para la fecha de fin (no permitir antes de la de inicio)
const endDateConfig = computed(() => ({
  altFormat: "Y-m-d",
  dateFormat: "Y-m-d",
  minDate: localFormData.value.start_date || undefined,
}));

// Resetear formulario cuando se abre el modal
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.categoryOfferToEdit) {
        nextTick(() => {
          localFormData.value = {
            id: props.categoryOfferToEdit.id,
            category_id: props.categoryOfferToEdit.category_id,
            discount_percentage: props.categoryOfferToEdit.discount_percentage,
            start_date: formatDateForInput(props.categoryOfferToEdit.start_date),
            end_date: formatDateForInput(props.categoryOfferToEdit.end_date),
            is_active: Boolean(props.categoryOfferToEdit.is_active),
          };
        });
      } else {
        localFormData.value = { ...defaultCategoryOffer };
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
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1">
            <VIcon icon="tabler-folder" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                Promoción por Categoría de Productos
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

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Bloque 1: Categoría en Oferta -->
        <div class="mb-5">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Categoría en Oferta</span>
          </div>

          <!-- Modo Edición: Tarjeta Resumen en una sola línea -->
          <div v-if="props.isEditing" class="pa-3 rounded-lg border bg-var-theme-background d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-2">
              <span class="text-xs font-weight-bold text-primary bg-primary-lighten-5 px-2 py-0-5 rounded">
                ID {{ localFormData.category_id }}
              </span>
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase">
                {{ selectedCategoryDisplay }}
              </span>
            </div>
          </div>

          <!-- Modo Creación: Autocomplete Ancho Completo -->
          <div v-else>
            <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Seleccionar Categoría</span>
            <VAutocomplete
              v-model="localFormData.category_id"
              :items="props.categoriesData"
              :item-title="(item) => `${item.id} - ${item.name}`"
              item-value="id"
              placeholder="BUSCAR CATEGORÍA POR ID O NOMBRE..."
              variant="outlined"
              density="compact"
              hide-details="auto"
              clearable
              :disabled="props.loading"
              class="rounded-lg font-weight-bold"
              :error="!!props.formErrors.category_id"
              :error-messages="props.formErrors.category_id"
            />
          </div>
        </div>

        <!-- Bloque 2: Parámetros de la Oferta -->
        <div class="mb-2">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Parámetros de la Oferta</span>
            </div>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-bold text-disabled uppercase">Activa</span>
              <VSwitch
                v-model="localFormData.is_active"
                color="primary"
                hide-details
                density="compact"
                inset
              />
            </div>
          </div>

          <VRow dense>
            <VCol cols="12" sm="4">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">% Descuento</span>
                <VTextField
                  v-model="localFormData.discount_percentage"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  prepend-inner-icon="tabler-percentage"
                  class="rounded-lg font-weight-black"
                  :error="!!props.formErrors.discount_percentage"
                  :error-messages="props.formErrors.discount_percentage"
                  :disabled="props.loading"
                />
              </div>
            </VCol>

            <VCol cols="12" sm="4">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha Inicio</span>
                <AppDateTimePicker
                  v-model="localFormData.start_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-event"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg"
                  :error="!!props.formErrors.start_date"
                  :error-messages="props.formErrors.start_date"
                  :disabled="props.loading"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>

            <VCol cols="12" sm="4">
              <div>
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha Final</span>
                <AppDateTimePicker
                  v-model="localFormData.end_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-off"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg"
                  :error="!!props.formErrors.end_date"
                  :error-messages="props.formErrors.end_date"
                  :disabled="props.loading"
                  :config="endDateConfig"
                />
              </div>
            </VCol>
          </VRow>
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
              block
              height="44"
              class="font-weight-bold rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="props.loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              block
              height="44"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="props.loading"
              @click="onSave"
            >
              <VIcon icon="tabler-device-floppy" class="me-1" size="18" />
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

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 4px;
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

.leading-none {
  line-height: 1 !important;
}

.gap-1-5 {
  gap: 6px !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.04);
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
