<script setup>
import axios from '@/plugins/axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  allProducts: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'save']);

const formData = ref({});

const alternativeProductIdInput = ref(null);
const alternativeProducts = ref([]);
const isDeletingAlternative = ref(null);

const isNewProduct = computed(() => !formData.value.id);

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
    alert('Por favor, introduce un ID de producto válido.');
    return;
  }

  if (id === formData.value.id) {
    alert('No puedes añadir el producto a sí mismo como alternativo.');
    return;
  }

  const isAlreadyAdded = alternativeProducts.value.some(p => p.id === id);
  if (isAlreadyAdded) {
    alert('Este producto ya ha sido añadido.');
    return;
  }

  const productToAdd = props.allProducts.find(p => p.id === id);

  if (productToAdd) {
    alternativeProducts.value.push(productToAdd);
    alternativeProductIdInput.value = null;
  } else {
    alert('Producto no encontrado. Verifica el ID.');
  }
}

async function removeAlternativeProduct(alternativeId) {
  
  if (isDeletingAlternative.value) return;
  
  if (!confirm('¿Estás seguro de que quieres eliminar la relación con este producto alternativo?')) {
    return;
  }

  isDeletingAlternative.value = alternativeId;

  try {
    const mainProductId = formData.value.id;
    if (mainProductId) { 
        const url = `/products/${mainProductId}/related/${alternativeId}`;
        await axios.delete(url);
        alternativeProducts.value = alternativeProducts.value.filter(p => p.id !== alternativeId);
    } else {
        alternativeProducts.value = alternativeProducts.value.filter(p => p.id !== alternativeId);
        console.warn('Producto principal no guardado aún, eliminando solo de la lista local.');
    }
  } catch (error) {
    console.error('Error al eliminar el producto alternativo:', error);
    alert('No se pudo eliminar el producto alternativo. Por favor, inténtelo de nuevo.');
  } finally {
    isDeletingAlternative.value = null;
  }
}

watch(() => props.product, (newProduct) => {
  if (newProduct && Object.keys(newProduct).length > 0) {
    formData.value = JSON.parse(JSON.stringify(newProduct));
    
    if (newProduct.related_products && Array.isArray(newProduct.related_products)) {
        alternativeProducts.value = [...newProduct.related_products];
    } else {
        alternativeProducts.value = [];
    }
  } else {
    formData.value = {
      name: '',
      active_ingredient: '',
      laboratory_id: null,
      sale_price: 0, 
      cost_price: 0,
      origin_id: null,
      category_id: null,
      barcode: '',
      iva: 0, 
      psychotropic: 0, 
      from_colombia: 0, 
      lots: [],
    };
    alternativeProducts.value = []; 
  }
}, { deep: true, immediate: true });


const lotHeaders = [
  { title: 'Cantidad Disponible', key: 'quantity', sortable: false },
  { title: 'Fecha de Vencimiento', key: 'expiration_date', sortable: false },
  { title: 'Acción', key: 'actions', sortable: false, align: 'end' },
];

const closeDialog = () => {
  emit('update:modelValue', false);
};

const submitForm = () => {
  formData.value.related_product_ids = alternativeProducts.value.map(p => p.id);
  emit('save', formData.value);
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
            <VCol cols="12" md="6">
              <VTextField v-model="formData.name" label="Nombre" variant="outlined" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="formData.active_ingredient" label="Principio Activo" variant="outlined" />
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
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="formData.sale_price" label="Costo de Venta" type="number" prefix="$" variant="outlined" />
            </VCol>
          </VRow>
          <VRow v-if="isNewProduct"> 
            <VCol cols="12" md="6">
              <VTextField v-model="formData.cost_price" label="Costo de Compra" type="number" prefix="$" variant="outlined" />
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
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="formData.barcode" label="Código de Barra" variant="outlined" />
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
