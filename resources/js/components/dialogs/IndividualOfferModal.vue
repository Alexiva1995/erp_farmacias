<script setup>
import { computed, watch, ref } from "vue";

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
  productsData: {
    type: Array,
    default: () => [],
  },
  formErrors: { 
    type: Object, 
    default: () => ({}) 
  },
  isEditing: { type: Boolean, default: false },
  productOfferToEdit: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "save", "modal-closed"]);

const defaultIndividualOffer = {
  product_id: null,
  discount_percent: null,
  start_date: "",
  end_date: "",
};

const localFormData = ref({ ...defaultIndividualOffer });

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta" : "Crear Oferta";
});

// Computed para mostrar el producto seleccionado en modo edición
const selectedProductDisplay = computed(() => {
  if (!localFormData.value.product_id) return '';
  
  const product = props.productsData.find(p => p.id === localFormData.value.product_id);
  return product ? `${product.id} - ${product.name}` : '';
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
    discount_percent: parseFloat(localFormData.value.discount_percent) || 0
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
      if (props.isEditing && props.productOfferToEdit) {
        // Modo edición
        localFormData.value = {
          id: props.productOfferToEdit.id,
          product_id: props.productOfferToEdit.product_id,
          discount_percent: props.productOfferToEdit.discount_percent,
          start_date: formatDateForInput(props.productOfferToEdit.start_date),
          end_date: formatDateForInput(props.productOfferToEdit.end_date),
        };
      } else {
        // Modo creación
        localFormData.value = { ...defaultIndividualOffer };
      }
    }
  },
  { immediate: true }
);

// Sincronizar con formData del padre
watch(
  () => props.formData,
  (newFormData) => {
    if (newFormData) {
      Object.assign(localFormData.value, newFormData);
    }
  },
  { deep: true, immediate: true }
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
          <p class="text-h6 font-weight-medium mb-4">
            {{ dialogTitle }}
          </p>
          
          <VRow>
            <VCol cols="12">
              <VAutocomplete
                v-if="!props.isEditing"
                v-model="localFormData.product_id"
                label="Seleccionar Producto"
                variant="outlined"
                :items="props.productsData"
                :item-title="(item) => `${item.id} - ${item.name}`"
                item-value="id"
                placeholder="Buscar producto por ID o nombre"
                :error="!!props.formErrors.product_id"
                :error-messages="props.formErrors.product_id"
                clearable
                no-data-text="No se encontraron productos"
              />
              <VTextField
                v-else
                :model-value="selectedProductDisplay"
                label="Producto"
                readonly
                variant="outlined"
                bg-color="grey-lighten-5"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="localFormData.discount_percent"
                label="% Descuento"
                variant="outlined"
                type="number"
                min="0"
                max="100"
                step="0.01"
                :error="!!props.formErrors.discount_percent"
                :error-messages="props.formErrors.discount_percent"
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
              :disabled="props.loading"
            >
              {{ props.isEditing ? "Actualizar Oferta" : "Guardar Oferta" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
