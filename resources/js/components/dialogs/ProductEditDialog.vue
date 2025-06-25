<script setup>
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  allProducts: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'save', 'clearErrors']);

const formData = ref({});
const imageFile = ref(null);

const formErrors = ref({});

const alternativeProductIdInput = ref(null);
const alternativeProducts = ref([]);
const isDeletingAlternative = ref(null);

const isNewProduct = computed(() => !formData.value.id);

const imagePreviewUrl = computed(() => {
  if (imageFile.value) {
    return URL.createObjectURL(imageFile.value);
  }
  if (formData.value.photo_url) {
    return formData.value.photo_url;
  }
  return null;
});

const alternativeProductHeaders = [
  { title: 'Nombre', key: 'name', sortable: false },
  { title: 'Laboratorio', key: 'laboratory.name', sortable: false },
  { title: 'Stock', key: 'lots', sortable: false },
  { title: 'Acción', key: 'actions', sortable: false, align: 'end' },
];

const calculateStock = (product) => {
    if (!product.lots || !Array.isArray(product.lots)) return 0;
    return product.lots.reduce((sum, lot) => sum + Number(lot.quantity || 0), 0);
}

function addAlternativeProduct() {
  const id = Number(alternativeProductIdInput.value);
  if (!id) {
    toast.warning('Por favor, introduce un ID de producto válido.');
    return;
  }

  if (id === formData.value.id) {
    toast.warning('No puedes añadir el producto a sí mismo como alternativo.');
    return;
  }

  const isAlreadyAdded = alternativeProducts.value.some(p => p.id === id);
  if (isAlreadyAdded) {
    toast.warning('Este producto ya ha sido añadido.');
    return;
  }

  const productToAdd = props.allProducts.find(p => p.id === id);

  if (productToAdd) {
    alternativeProducts.value.push(productToAdd);
    alternativeProductIdInput.value = null;
    toast.success('Producto alternativo añadido.');
  } else {
    toast.error('Producto no encontrado. Verifica el ID.');
  }
}

async function removeAlternativeProduct(alternativeId) {
  if (isDeletingAlternative.value) return;
  isDeletingAlternative.value = alternativeId;
  try {
    const mainProductId = formData.value.id;
    if (mainProductId) { 
        const url = `/products/${mainProductId}/related/${alternativeId}`;
        await axios.delete(url);
        alternativeProducts.value = alternativeProducts.value.filter(p => p.id !== alternativeId);
        toast.success('Relación eliminada correctamente.');
    } else {
        alternativeProducts.value = alternativeProducts.value.filter(p => p.id !== alternativeId);
        console.warn('Producto principal no guardado aún, eliminando solo de la lista local.');
    }
  } catch (error) {
    console.error('Error al eliminar el producto alternativo:', error);
    toast.error('No se pudo eliminar el producto alternativo.');
  } finally {
    isDeletingAlternative.value = null;
  }
}

watch(() => props.errors, (newErrors) => {
  formErrors.value = newErrors || {};
}, { deep: true });


watch(() => props.product, (newProduct) => {
  if (newProduct && Object.keys(newProduct).length > 0) {
    formData.value = JSON.parse(JSON.stringify(newProduct));
    alternativeProducts.value = newProduct.related_products && Array.isArray(newProduct.related_products) ? [...newProduct.related_products] : [];
  } else {
    formData.value = {
      name: '',
      active_ingredient: '',
      laboratory_id: null,
      cost_price: 0,
      origin_id: null,
      category_id: null,
      barcode: '',
      iva: 0, 
      psychotropic: 0, 
      from_colombia: 0, 
      lots: [],
      photo_url: null,
    };
    alternativeProducts.value = []; 
  }
  imageFile.value = null;
  formErrors.value = {};
}, { deep: true, immediate: true });


// MODIFICADO: Cambios en los títulos de la tabla de lotes
const lotHeaders = [
  { title: 'Stock', key: 'quantity', sortable: false },
  { title: 'Exp.', key: 'expiration_date', sortable: false },
  { title: 'Acción', key: 'actions', sortable: false, align: 'end' },
];

// NUEVO: Función para formatear la fecha
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, '0');
    const day = date.getUTCDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
  } catch (error) {
    return 'Fecha inválida';
  }
};


const closeDialog = () => {
  emit('update:modelValue', false);
  formErrors.value = {}; 
  emit('clearErrors');
};

const submitForm = () => {
  formErrors.value = {};
  emit('clearErrors');

  const payload = new FormData();

  Object.keys(formData.value).forEach(key => {
    const value = formData.value[key];
    if (value !== null && value !== undefined && !Array.isArray(value) && typeof value !== 'object') {
       payload.append(key, value);
    }
  });

  const relatedIds = alternativeProducts.value.map(p => p.id);
  relatedIds.forEach(id => {
    payload.append('related_product_ids[]', id);
  });
  
  if (imageFile.value) {
    payload.append('photo_url', imageFile.value);
  }

  payload.append('sale_price', 0);

  emit('save', payload);
};
</script>


<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData">
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ isNewProduct ? 'Añadir Nuevo Producto' : 'Editar Producto' }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />
      <p class="font-weight-bold text-h6 px-6 pt-4">Datos Generales</p>

      <VCardText>
        <VForm @submit.prevent="submitForm">
          <VRow>
            <VCol cols="12" md="8">
              <VFileInput
                v-model="imageFile"
                label="Imagen del Producto"
                accept="image/*"
                variant="outlined"
                prepend-icon="tabler-camera"
                clearable
                :error-messages="formErrors.photo_url"
              />
            </VCol>
            <VCol v-if="imagePreviewUrl" cols="12" md="4" class="d-flex align-center justify-center">
                <VImg
                    :src="imagePreviewUrl"
                    :width="150"
                    aspect-ratio="1"
                    class="border rounded"
                />
            </VCol>
          </VRow>
          <VDivider class="my-4" />
          <VRow>
            <VCol cols="12" md="6">
              <VTextField 
                v-model="formData.name" 
                label="Nombre" 
                variant="outlined" 
                :error-messages="formErrors.name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField 
                v-model="formData.active_ingredient" 
                label="Principio Activo" 
                variant="outlined" 
                :error-messages="formErrors.active_ingredient"
              />
            </VCol>
          </VRow>
          <VDivider class="my-4" />
          
          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.laboratory_id"
                label="Laboratorio"
                :items="props.laboratories"
                item-title="name"
                item-value="id"
                variant="outlined"
                clearable
                :error-messages="formErrors.laboratory_id"
              />
            </VCol>
            
            <VCol cols="12" md="6">
              <VTextField 
                v-model="formData.cost_price" 
                label="Costo de Compra" 
                type="number" 
                prefix="$" 
                variant="outlined"
                :error-messages="formErrors.cost_price"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.origin_id"
                label="Origen"
                :items="props.origins"
                item-title="name"
                item-value="id"
                variant="outlined"
                clearable
                :error-messages="formErrors.origin_id"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.category_id"
                label="Categoría"
                :items="props.categories"
                item-title="name"
                item-value="id"
                variant="outlined"
                clearable
                :error-messages="formErrors.category_id"
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField 
                v-model="formData.barcode" 
                label="Código de Barra" 
                variant="outlined" 
                :error-messages="formErrors.barcode"
              />
            </VCol>
            <VCol cols="12" md="6" class="d-flex align-center flex-wrap gap-x-4">
              <VCheckbox
                v-model="formData.iva"
                label="Aplica IVA"
                :true-value="1"
                :false-value="0"
              />
              <VCheckbox
                v-model="formData.psychotropic"
                label="Psicotrópico"
                :true-value="1"
                :false-value="0"
              />
              <VCheckbox
                v-model="formData.from_colombia"
                label="P.Colombia"
                :true-value="1"
                :false-value="0"
              />
            </VCol>
          </VRow>
        
          <template v-if="!isNewProduct">
            <VDivider class="my-4" />
            <p class="font-weight-medium mb-2">Productos Alternativos</p>
            <VRow>
              <VCol cols="12" md="9">
                <VTextField
                  v-model="alternativeProductIdInput"
                  label="ID del Producto Alternativo"
                  type="number"
                  variant="outlined"
                  hide-details
                  @keydown.enter.prevent="addAlternativeProduct"
                />
              </VCol>
              <VCol cols="12" sm="3">
                <VBtn
                  color="primary"
                  @click="addAlternativeProduct"
                  block
                  height="40"
                >
                  Añadir
                </VBtn>
              </VCol>
            </VRow>
            
            <!-- MODIFICADO: Título de 'lots' cambiado a 'Stock' en los headers -->
            <VDataTable
              :headers="alternativeProductHeaders"
              :items="alternativeProducts"
              density="compact"
              class="mt-4 mb-4"
              no-data-text="No se han añadido productos alternativos."
            >
              <template #item.lots="{ item }">
                <span>{{ calculateStock(item) }}</span>
              </template>

               <template #item.actions="{ item }">
                <IconBtn 
                  @click="removeAlternativeProduct(item.id)"
                  :disabled="isDeletingAlternative === item.id"
                >
                  <VProgressCircular
                    v-if="isDeletingAlternative === item.id"
                    indeterminate
                    size="20"
                    color="primary"
                  />
                  <VIcon v-else icon="tabler-trash" color="error" />
                </IconBtn>
              </template>
            </VDataTable>
          </template>

          <template v-if="!isNewProduct">
            <VDivider class="my-4" />
            <p class="font-weight-medium mb-2">Lotes del Producto</p>
            <VDataTable
              :headers="lotHeaders"
              :items="formData.lots || []"
              density="compact"
              class="mb-4"
              no-data-text="Este producto no tiene lotes registrados."
            >
              <template #item.quantity="{ item }">
                  <span>{{ Number(item.quantity) || 0 }}</span>
              </template>
              <!-- NUEVO: Slot para formatear la fecha de expiración -->
              <template #item.expiration_date="{ item }">
                <span>{{ formatDate(item.expiration_date) }}</span>
              </template>
              <template #item.actions>
                  <IconBtn>
                      <VIcon icon="tabler-edit" />
                  </IconBtn>
              </template>
            </VDataTable>
          </template>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="closeDialog">Cancelar</VBtn>
        <VBtn color="primary" variant="flat" @click="submitForm">Guardar Cambios</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
