<script setup>
import { computed, defineEmits, defineProps, ref, watch, onMounted, nextTick } from "vue";
import axios from "@/plugins/axios";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  prescriptionData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed", "prescription-saved"]);

// Datos del formulario
const formData = ref({
  discount_percentage: 0,
  start_date: null,
  end_date: null,
  is_active: true,
  products: [],
  total_cost: 0,
});

const formErrors = ref({});
const currentStageIndex = ref(0);
const loading = ref(false);

// Productos disponibles
const availableProducts = ref([]);
const loadingProducts = ref(false);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const isEditing = computed(() => props.prescriptionData && props.prescriptionData.id);

// Cargar productos disponibles
const loadAvailableProducts = async () => {
  loadingProducts.value = true;
  try {
    const response = await axios.get('/products/autocomplete');
    if (response.data.success) {
      availableProducts.value = response.data.data;
    } else {
      console.error('Error al cargar los productos disponibles:', response.data.message);
    }
  } catch (error) {
    console.error('Error al cargar los productos disponibles:', error);
  } finally {
    loadingProducts.value = false;
  }
};

// Obtener oferta de receta por ID
const getPrescription = async (prescriptionId) => {
  try {
    const response = await axios.get(`/tpv/promotions/prescription-offer/${prescriptionId}`, prescriptionId);
    return response.data;
  } catch (error) {
    console.error('Error al cargar la oferta de recipe:', error);
    throw error;
  }
};

// Agregar producto al formulario
const addProduct = () => {
  formData.value.products.push({
    product: null,
    sale_price: 0,
    quantity: 1,
    calculated_price: 0,
  });
};

// Remover producto del formulario
const removeProduct = (index) => {
  formData.value.products.splice(index, 1);
  calculateTotalCost();
};

// Cuando se selecciona un producto, asignar automáticamente su precio de venta
const onProductSelected = (product, index) => {
  if (product) {
    formData.value.products[index].sale_price = product.sale_price;
    calculateTotalCost();
  } else {
    formData.value.products[index].sale_price = 0;
    calculateTotalCost();
  }
};

// Calcular precio con descuento para un producto
const calculateProductPrice = (product) => {
  if (!product.product) return 0;
  
  const discount = formData.value.discount_percentage || 0;
  const originalPrice = product.sale_price || product.product.sale_price;
  const discountedPrice = originalPrice * (1 - discount / 100);
  product.calculated_price = discountedPrice * product.quantity;
  return product.calculated_price;
};

// Calcular costo total de la oferta CON descuento aplicado
const calculateTotalCost = () => {
  let total = 0;
  formData.value.products.forEach(product => {
    total += calculateProductPrice(product);
  });
  formData.value.total_cost = parseFloat(total.toFixed(2));
};

// Calcular subtotal sin descuento (solo para mostrar)
const calculateSubtotalWithoutDiscount = () => {
  let subtotal = 0;
  formData.value.products.forEach(product => {
    if (product.product) {
      subtotal += (product.sale_price || product.product.sale_price) * product.quantity;
    }
  });
  return parseFloat(subtotal.toFixed(2));
};

// Calcular monto total del descuento
const calculateTotalDiscountAmount = () => {
  const subtotal = calculateSubtotalWithoutDiscount();
  const discountPercentage = formData.value.discount_percentage || 0;
  return parseFloat((subtotal * (discountPercentage / 100)).toFixed(2));
};

// Preparar datos para enviar a la API
const preparePrescriptionData = () => {
  const productsData = [];
  
  formData.value.products.forEach(prescriptionProduct => {
    if (prescriptionProduct.product) {
      productsData.push({
        product_id: prescriptionProduct.product.id,
        sale_price: parseFloat(prescriptionProduct.sale_price),
        quantity: prescriptionProduct.quantity,
      });
    }
  });

  return {
    id: formData.value.id || null,
    discount_percentage: parseFloat(formData.value.discount_percentage),
    start_date: formData.value.start_date,
    end_date: formData.value.end_date,
    is_active: formData.value.is_active,
    products: productsData,
    total_cost: formData.value.total_cost, // Ahora incluye el total con descuento
  };
};

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};

// Validar Stage 1
const validateStage1 = () => {
  formErrors.value = {};
  
  if (!formData.value.discount_percentage || formData.value.discount_percentage < 0) {
    formErrors.value.discount_percentage = "El porcentaje de descuento es requerido y debe ser mayor o igual a 0";
    return false;
  }
  
  if (formData.value.discount_percentage > 100) {
    formErrors.value.discount_percentage = "El porcentaje de descuento no puede ser mayor a 100";
    return false;
  }
  
  if (formData.value.start_date && formData.value.end_date) {
    const startDate = new Date(formData.value.start_date);
    const endDate = new Date(formData.value.end_date);
    
    if (endDate < startDate) {
      formErrors.value.end_date = "La fecha final no puede ser anterior a la fecha inicial";
      return false;
    }
  }
  
  return true;
};

// Validar Stage 2
const validateStage2 = () => {
  formErrors.value = {};
  let isValid = true;
  
  if (formData.value.products.length === 0) {
    formErrors.value.products = "Debe agregar al menos un producto";
    isValid = false;
  }
  
  formData.value.products.forEach((product, index) => {
    if (!product.product) {
      formErrors.value[`product_${index}`] = "Selecciona un producto";
      isValid = false;
    }
    
    if (!product.quantity || product.quantity < 1) {
      formErrors.value[`quantity_${index}`] = "La cantidad debe ser al menos 1";
      isValid = false;
    }
  });
  
  return isValid;
};

// Guardar oferta de receta
const savePrescription = async () => {
  loading.value = true;
  try {
    const prescriptionData = preparePrescriptionData();
    await emit("prescription-saved", prescriptionData);
  } catch (error) {
    throw error;
  } finally {
    loading.value = false;
  }
};

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
  resetForm();
  resetProgress();
};

const resetForm = () => {
  formData.value = {
    discount_percentage: 0,
    start_date: null,
    end_date: null,
    is_active: true,
    products: [],
    total_cost: 0,
  };
  formErrors.value = {};
};

const resetProgress = () => {
  currentStageIndex.value = 0;
};

// Computed properties para el wizard
const canProceedToNext = computed(() => {
  if (currentStageIndex.value === 0) {
    return validateStage1();
  } else if (currentStageIndex.value === 1) {
    return validateStage2();
  }
  return true;
})

const handleCompletePurchase = async () => {
  if (currentStageIndex.value === 0) {
    if (validateStage1()) {
      currentStageIndex.value++;
    }
  } else if (currentStageIndex.value === 1) {
    if (validateStage2()) {
      calculateTotalCost();
      currentStageIndex.value++;
    }
  } else {
    await savePrescription();
  }
};

const goBack = () => {
  if (currentStageIndex.value > 0) {
    currentStageIndex.value--;
  }
};

// Cargar datos de la oferta para edición
const loadPrescriptionData = async (prescriptionId) => {
  try {
    const response = await getPrescription(prescriptionId);
    if (response.success) {
      const prescription = response.data;
      
      formData.value = {
        id: prescription.id,
        discount_percentage: prescription.discount_percentage,
        start_date: formatDateForInput(prescription.start_date),
        end_date: formatDateForInput(prescription.end_date),
        is_active: prescription.is_active,
        products: [],
        total_cost: prescription.total_cost,
      };

      // Reconstruir productos desde el JSON
      if (prescription.products && prescription.products.length > 0) {
        prescription.products.forEach((productData, index) => {
          const product = availableProducts.value.find(p => p.id == productData.product_id);
          if (product) {
            formData.value.products[index] = {
              product: product,
              sale_price: productData.sale_price,
              quantity: productData.quantity,
              calculated_price: productData.sale_price * productData.quantity * (1 - prescription.discount_percentage / 100),
            };
          }
        });
      }
    }
  } catch (error) {
    console.error('Error loading prescription data:', error);
  }
};

// Watchers
watch(
  () => props.isDialogVisible,
  async (newVal) => {
    if (newVal) {
      resetProgress();
      await loadAvailableProducts();
      if (props.prescriptionData && props.prescriptionData.id) {
        await loadPrescriptionData(props.prescriptionData.id);
      }
    }
  }
);

watch(
  () => formData.value.discount_percentage,
  () => {
    calculateTotalCost();
  }
);

onMounted(() => {
  loadAvailableProducts();
});
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="800" persistent>
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center p-4">
        <span class="text-h5 font-weight-bold">
          {{ isEditing ? 'Editar Oferta de Receta' : 'Crear Oferta de Receta' }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal" :disabled="loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      
      <VDivider />
      
      <!-- Progress Steps -->
      <VCardText class="pa-4">
        <VStepper :model-value="currentStageIndex + 1" alt-labels>
          <VStepperHeader>
            <VStepperItem
              :value="1"
              title="Información de la Oferta"
              :complete="currentStageIndex > 0"
            />
            <VStepperDivider />
            <VStepperItem
              :value="2"
              title="Agregar Productos"
              :complete="currentStageIndex > 1"
            />
            <VStepperDivider />
            <VStepperItem
              :value="3"
              title="Resumen Final"
              :complete="currentStageIndex > 2"
            />
          </VStepperHeader>
        </VStepper>
      </VCardText>

      <VDivider />

      <!-- Stage 1: Información de la oferta -->
      <VCardText v-if="currentStageIndex === 0" class="flex-grow-1 pa-6">
        <p class="text-h6 font-weight-medium mb-4">Información de la Oferta de Receta</p>
        
        <VRow>
          <VCol cols="12" md="6">
            <VTextField
              v-model="formData.discount_percentage"
              label="Porcentaje de Descuento *"
              variant="outlined"
              type="number"
              min="0"
              max="100"
              suffix="%"
              :error-messages="formErrors.discount_percentage"
              :disabled="loading"
            />
          </VCol>
          
          <VCol cols="12" md="6">
            <VSelect
              v-model="formData.is_active"
              label="Estatus"
              :items="[
                { title: 'Activo', value: true },
                { title: 'Inactivo', value: false },
              ]"
              item-title="title"
              item-value="value"
              variant="outlined"
              :disabled="loading"
            />
          </VCol>
          
          <VCol cols="12" md="6">
            <VTextField
              v-model="formData.start_date"
              label="Fecha de Inicio"
              variant="outlined"
              type="date"
              :disabled="loading"
            />
          </VCol>
          
          <VCol cols="12" md="6">
            <VTextField
              v-model="formData.end_date"
              label="Fecha de Fin"
              variant="outlined"
              type="date"
              :error-messages="formErrors.end_date"
              :disabled="loading"
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Stage 2: Agregar productos -->
      <VCardText v-if="currentStageIndex === 1" class="flex-grow-1 pa-6">
        <div class="d-flex justify-space-between align-center mb-4">
          <p class="text-h6 font-weight-medium mb-0">Agregar Productos a la Oferta</p>
          <div class="d-flex gap-2">
            <VChip variant="outlined" color="primary">
              {{ formData.products.length }} producto(s) agregado(s)
            </VChip>
            <VBtn
              color="primary"
              variant="tonal"
              prepend-icon="tabler-plus"
              @click="addProduct"
              :disabled="loading"
            >
              Agregar Producto
            </VBtn>
          </div>
        </div>

        <p class="text-caption text-medium-emphasis mb-4">
          Agregue los productos que formarán parte de esta oferta de receta
        </p>

        <VCard
          v-for="(prescriptionProduct, index) in formData.products"
          :key="index"
          variant="outlined"
          class="mb-4"
        >
          <VCardTitle class="text-h6 pa-4 d-flex justify-space-between align-center">
            <span>Producto {{ index + 1 }}</span>
            <VBtn
              icon
              variant="text"
              color="error"
              size="small"
              @click="removeProduct(index)"
              :disabled="loading"
            >
              <VIcon>tabler-trash</VIcon>
            </VBtn>
          </VCardTitle>
          
          <VCardText>
            <VRow>
              <!-- Selección de producto -->
              <VCol cols="12" md="6">
                <VAutocomplete
                  v-model="prescriptionProduct.product"
                  :items="availableProducts"
                  item-title="name"
                  item-value="id"
                  label="Seleccionar Producto *"
                  variant="outlined"
                  :loading="loadingProducts"
                  :error-messages="formErrors[`product_${index}`]"
                  return-object
                  clearable
                  :disabled="loading"
                  @update:model-value="onProductSelected(prescriptionProduct.product, index)"
                />
              </VCol>
              
              <!-- Cantidad -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="prescriptionProduct.quantity"
                  label="Cantidad *"
                  variant="outlined"
                  type="number"
                  min="1"
                  :error-messages="formErrors[`quantity_${index}`]"
                  :disabled="loading"
                  @update:model-value="calculateTotalCost()"
                />
              </VCol>
            </VRow>

            <!-- Detalles del producto seleccionado -->
            <VCard v-if="prescriptionProduct.product" variant="tonal" class="mt-4">
              <VCardText>
                <p class="text-h6 mb-2">{{ prescriptionProduct.product.name }}</p>
                <VRow>
                  <VCol cols="6" sm="4">
                    <div class="text-caption font-weight-bold">Stock</div>
                    <div class="text-body-1">{{ prescriptionProduct.product.stock }} disp.</div>
                  </VCol>
                  <VCol cols="6" sm="4">
                    <div class="text-caption font-weight-bold">Precio de Venta</div>
                    <div class="text-body-1 text-primary">${{ prescriptionProduct.sale_price }}</div>
                  </VCol>
                  <VCol cols="6" sm="4">
                    <div class="text-caption font-weight-bold">Laboratorio</div>
                    <div class="text-body-1">{{ prescriptionProduct.product.laboratory?.name || 'N/A' }}</div>
                  </VCol>
                </VRow>

                <VRow class="mt-2">
                  <VCol cols="6" sm="4">
                    <div class="text-caption font-weight-bold">Principio Activo</div>
                    <div class="text-body-1">{{ prescriptionProduct.product.active_ingredient || 'N/A' }}</div>
                  </VCol>
                  <VCol cols="6" sm="4">
                    <div class="text-caption font-weight-bold">Descuento Aplicado</div>
                    <div class="text-body-1 text-success">{{ formData.discount_percentage }}%</div>
                  </VCol>
                  <VCol cols="6" sm="4">
                    <div class="text-caption font-weight-bold">Precio con Descuento</div>
                    <div class="text-body-1 text-primary">
                      ${{ (prescriptionProduct.sale_price * (1 - formData.discount_percentage / 100)).toFixed(2) }} c/u
                    </div>
                  </VCol>
                </VRow>
                
                <!-- Subtotal -->
                <VDivider class="my-2" />
                <VRow class="mt-2">
                  <VCol cols="12">
                    <div class="d-flex justify-space-between align-center">
                      <span class="font-weight-bold">Subtotal con Descuento:</span>
                      <span class="text-h6 text-success">
                        ${{ calculateProductPrice(prescriptionProduct).toFixed(2) }}
                      </span>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <VAlert
              v-else
              type="info"
              variant="tonal"
              class="mt-4"
            >
              Selecciona un producto para ver sus detalles
            </VAlert>
          </VCardText>
        </VCard>

        <VAlert
          v-if="formData.products.length === 0"
          type="warning"
          variant="tonal"
          class="mt-4"
        >
          No hay productos agregados. Haga clic en "Agregar Producto" para comenzar.
        </VAlert>

        <!-- Resumen de costos -->
        <VCard v-if="formData.products.length > 0" color="primary" variant="flat" class="mt-4">
          <VCardText>
            <VRow>
              <VCol cols="12" md="4">
                <div class="text-center">
                  <div class="text-caption text-white">Subtotal sin Descuento</div>
                  <div class="text-h6 font-weight-bold text-white">${{ calculateSubtotalWithoutDiscount() }}</div>
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <div class="text-center">
                  <div class="text-caption text-white">Descuento Aplicado</div>
                  <div class="text-h6 font-weight-bold text-white">- ${{ calculateTotalDiscountAmount() }}</div>
                </div>
              </VCol>
              <VCol cols="12" md="4">
                <div class="text-center">
                  <div class="text-caption text-white">Costo Total Final</div>
                  <div class="text-h6 font-weight-bold text-white">${{ formData.total_cost }}</div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCardText>

      <!-- Stage 3: Resumen final -->
      <VCardText v-if="currentStageIndex === 2" class="flex-grow-1 pa-6">
        <p class="text-h6 font-weight-medium mb-4">Resumen de la Oferta de Receta</p>

        <VRow>
          <VCol cols="12" md="6">
            <VCard variant="outlined">
              <VCardTitle class="text-h6">Información de la Oferta</VCardTitle>
              <VCardText>
                <VList>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold">Porcentaje de Descuento:</VListItemTitle>
                    <VListItemSubtitle class="text-h6 text-success">{{ formData.discount_percentage }}%</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold">Subtotal sin Descuento:</VListItemTitle>
                    <VListItemSubtitle class="text-body-1">${{ calculateSubtotalWithoutDiscount() }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold">Descuento Total:</VListItemTitle>
                    <VListItemSubtitle class="text-body-1 text-success">- ${{ calculateTotalDiscountAmount() }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold">Costo Total Final:</VListItemTitle>
                    <VListItemSubtitle class="text-h6 text-primary">${{ formData.total_cost }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold">Fecha de Inicio:</VListItemTitle>
                    <VListItemSubtitle>{{ formData.start_date || 'Sin fecha definida' }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold">Fecha de Fin:</VListItemTitle>
                    <VListItemSubtitle>{{ formData.end_date || 'Sin fecha definida' }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold">Estado:</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip :color="formData.is_active ? 'success' : 'error'" size="small">
                        {{ formData.is_active ? 'Activo' : 'Inactivo' }}
                      </VChip>
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" md="6">
            <VCard variant="outlined">
              <VCardTitle class="text-h6">Productos Incluidos</VCardTitle>
              <VCardText>
                <VList>
                  <template v-for="(prescriptionProduct, index) in formData.products" :key="index">
                    <VListItem v-if="prescriptionProduct && prescriptionProduct.product">
                      <VListItemTitle class="font-weight-bold">
                        {{ prescriptionProduct.product.name }}
                      </VListItemTitle>
                      <VListItemSubtitle>
                        {{ prescriptionProduct.quantity }} unidades - 
                        ${{ prescriptionProduct.sale_price }} c/u - 
                        {{ formData.discount_percentage }}% descuento
                      </VListItemSubtitle>
                      <VListItemSubtitle class="text-success font-weight-bold">
                        Subtotal: ${{ calculateProductPrice(prescriptionProduct).toFixed(2) }}
                      </VListItemSubtitle>
                    </VListItem>
                  </template>
                  
                  <!-- Mostrar mensaje si no hay productos válidos -->
                  <VListItem v-if="!formData.products.some(p => p && p.product)">
                    <VListItemTitle class="text-medium-emphasis">
                      No hay productos seleccionados
                    </VListItemTitle>
                  </VListItem>
                </VList>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Botones de navegación -->
      <VCardActions class="pa-4 px-6">
        <VRow>
          <VCol cols="6" class="pe-2">
            <VBtn
              v-if="currentStageIndex > 0"
              color="secondary"
              variant="outlined"
              block
              @click="goBack"
              :disabled="loading"
            >
              <VIcon>mdi-arrow-left</VIcon>
              Anterior
            </VBtn>
            <VBtn
              v-else
              color="secondary"
              variant="outlined"
              block
              @click="closeModal"
              :disabled="loading"
            >
              Cancelar
            </VBtn>
          </VCol>

          <VCol cols="6" class="ps-2">
            <VBtn
              color="primary"
              variant="flat"
              block
              :disabled="!canProceedToNext"
              :loading="loading"
              @click="handleCompletePurchase"
            >
              <template v-if="currentStageIndex < 2">
                Siguiente
                <VIcon>mdi-arrow-right</VIcon>
              </template>
              <template v-else>
                {{ isEditing ? "Actualizar" : "Guardar" }}
              </template>
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>