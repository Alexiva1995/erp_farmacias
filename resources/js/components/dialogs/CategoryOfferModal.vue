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
  return category ? `${category.id} - ${category.name}` : `ID: ${localFormData.value.category_id}`;
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
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'rounded-xl overflow-hidden border-0 elevation-24'">
      <!-- Header Premium con Gradiente -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-folder" color="primary" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              Configuración de promociones por categoría
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
          <!-- Selector de Categoría -->
          <VCol cols="12" md="8">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Categoría</span>
            <VAutocomplete
              v-if="!props.isEditing"
              v-model="localFormData.category_id"
              :items="props.categoriesData"
              :item-title="(item) => `${item.id} - ${item.name}`"
              item-value="id"
              placeholder="BUSCAR CATEGORÍA POR ID O NOMBRE..."
              variant="outlined"
              density="compact"
              hide-details
              clearable
              :disabled="props.loading"
              class="premium-input-compact mb-4"
              :error="!!props.formErrors.category_id"
            />
            <VTextField
              v-else
              :model-value="selectedCategoryDisplay"
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
              v-model="localFormData.is_active"
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
              :error="!!props.formErrors.is_active"
              :disabled="props.loading"
            />
          </VCol>

          <VCol cols="12" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">% Descuento</span>
            <AppTextField
              v-model="localFormData.discount_percentage"
              type="number"
              min="0"
              max="100"
              step="0.01"
              variant="outlined"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-percentage"
              class="premium-input-compact"
              :error="!!props.formErrors.discount_percentage"
              :disabled="props.loading"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Inicio</span>
            <AppDateTimePicker
              v-model="localFormData.start_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-event"
              density="compact"
              hide-details
              class="premium-input-compact"
              :error="!!props.formErrors.start_date"
              :disabled="props.loading"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block ms-1">Fecha Final</span>
            <AppDateTimePicker
              v-model="localFormData.end_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-off"
              density="compact"
              hide-details
              class="premium-input-compact"
              :error="!!props.formErrors.end_date"
              :disabled="props.loading"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
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

.leading-none {
  line-height: 1 !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
