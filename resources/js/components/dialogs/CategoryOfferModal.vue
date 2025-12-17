<script setup>
import { computed, watch, ref, nextTick } from "vue";

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

const defaultCategoryOffer = {
  category_id: null,
  discount_percentage: null,
  start_date: "",
  end_date: "",
  is_active: true,
};

const localFormData = ref({ ...defaultCategoryOffer });

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta por Categoría" : "Crear Oferta por Categoría";
});

// Computed para el display de la categoría seleccionada
const selectedCategory = computed(() => {
  if (!localFormData.value.category_id) return null;
  return props.categoriesData.find(c => c.id === localFormData.value.category_id) || null;
});

// Computed para mostrar el texto en el VAutocomplete
const selectedCategoryDisplay = computed(() => {
  if (!localFormData.value.category_id) return '';
  const category = props.categoriesData.find(c => c.id === localFormData.value.category_id);
  return category ? `${category.id} - ${category.name}` : '';
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
  // Preparar datos para enviar
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
        // Modo edición - usar nextTick para asegurar que el DOM esté actualizado
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
        // Modo creación
        localFormData.value = { ...defaultCategoryOffer };
      }
    }
  },
  { immediate: true }
);

// Watch para categoriesData - forzar actualización cuando lleguen los datos
watch(
  () => props.categoriesData,
  (newCategories) => {
    if (newCategories.length > 0 && localFormData.value.category_id && !props.isEditing) {
      // Forzar la actualización del VAutocomplete en modo creación
      const currentCategoryId = localFormData.value.category_id;
      nextTick(() => {
        localFormData.value.category_id = currentCategoryId;
      });
    }
  },
  { immediate: true }
);

// Watch específico para el category_id en modo edición
watch(
  () => localFormData.value.category_id,
  (newCategoryId, oldCategoryId) => {
    if (props.isEditing && newCategoryId && newCategoryId !== oldCategoryId) {
      console.log('Category ID changed:', newCategoryId);
    }
  }
);
</script>

<template>
  <VDialog :model-value="props.modelValue" max-width="600px" persistent>
    <VCard :loading="props.loading" class="d-flex flex-column">
      <VContainer>
        <VCardTitle class="d-flex align-center p-2">
          <span class="text-h5 font-weight-bold pr-1">{{ dialogTitle }}</span>
          <VSpacer />
        </VCardTitle>
        <VDivider />
        <VCardText class="flex-grow-1" style="overflow-y: auto">
          <VRow>
            <VCol cols="12">
              <VAutocomplete
                v-if="!props.isEditing"
                v-model="localFormData.category_id"
                label="Seleccionar una Categoría"
                variant="outlined"
                :items="props.categoriesData"
                :item-title="(item) => `${item.id} - ${item.name}`"
                item-value="id"
                :model-value="localFormData.category_id"
                placeholder="Buscar categoría por ID o nombre"
                :error="!!props.formErrors.category_id"
                :error-messages="props.formErrors.category_id"
                clearable
                no-data-text="No se encontraron categorías"
                return-object
                :hide-no-data="false"
              />
              <VTextField
                v-else
                :model-value="selectedCategoryDisplay"
                label="Categoría"
                readonly
                variant="outlined"
                bg-color="grey-lighten-5"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="localFormData.discount_percentage"
                label="% Descuento"
                variant="outlined"
                type="number"
                min="0"
                max="100"
                step="0.01"
                :error="!!props.formErrors.discount_percentage"
                :error-messages="props.formErrors.discount_percentage"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="localFormData.start_date"
                label="Fecha de Inicio"
                variant="outlined"
                type="date"
                placeholder="YYYY-MM-DD"
                :error="!!props.formErrors.start_date"
                :error-messages="props.formErrors.start_date"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="localFormData.end_date"
                label="Fecha de Final"
                variant="outlined"
                type="date"
                placeholder="YYYY-MM-DD"
                :error="!!props.formErrors.end_date"
                :error-messages="props.formErrors.end_date"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="localFormData.is_active"
                label="Estado"
                variant="outlined"
                :items="[
                  { value: true, title: 'Activa' },
                  { value: false, title: 'Inactiva' },
                ]"
                item-title="title"
                item-value="value"
                placeholder="Seleccione un estado"
                :error="!!props.formErrors.is_active"
                :error-messages="props.formErrors.is_active"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VContainer>

      <VCardActions class="pa-4 px-10">
        <VRow>
          <VCol cols="6" class="pe-1">
            <VBtn color="secondary" variant="outlined" block @click="onCancel">
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="ps-1">
            <VBtn 
              color="primary" 
              variant="flat" 
              block 
              @click="onSave"
              :disabled="props.loading || (!localFormData.category_id && !props.isEditing)"
            >
              {{ props.isEditing ? "Actualizar Oferta" : "Guardar Oferta" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
