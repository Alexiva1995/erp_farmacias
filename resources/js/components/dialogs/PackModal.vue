<script setup>
import axios from "@/plugins/axios";
import { computed, defineEmits, defineProps, onMounted, ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  packData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "modal-closed",
  "pack-saved",
]);

// Datos del formulario
const formData = ref({
  name: "",
  products_count: 1,
  max_quantity: null,
  max_sale_date: null,
  is_active: true,
  pack_products: [],
  total_price: 0,
});

const formErrors = ref({});
const progressStages = [0, 50, 100];
const currentStageIndex = ref(0);
const loading = ref(false);

// Productos disponibles
const availableProducts = ref([]);
const loadingProducts = ref(false);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const isEditing = computed(() => props.packData && props.packData.id);

// Cargar productos disponibles
const loadAvailableProducts = async () => {
  loadingProducts.value = true;
  try {
    // Llamada simple sin parámetros
    const response = await axios.get("/products/autocomplete");
    if (response.data.success) {
      availableProducts.value = response.data.data;
    } else {
      console.error("Error loading products:", response.data.message);
    }
  } catch (error) {
    console.error("Error loading products:", error);
  } finally {
    loadingProducts.value = false;
  }
};

// Obtener pack por ID
const getPack = async (packId) => {
  try {
    const response = await axios.get(
      `/tpv/promotions/product-packs/${packId}`,
      packId
    );
    return response.data;
  } catch (error) {
    console.error("Error fetching pack:", error);
    throw error;
  }
};

// Inicializar productos del pack basado en products_count
const initializePackProducts = () => {
  formData.value.pack_products = [];
  for (let i = 0; i < formData.value.products_count; i++) {
    formData.value.pack_products.push({
      product: null,
      quantity: 1,
      discount_percentage: 0,
      calculated_price: 0,
    });
  }
};

// Calcular precio con descuento para un producto
const calculateProductPrice = (product) => {
  if (!product.product) return 0;

  const discount = product.discount_percentage || 0;
  const originalPrice = product.product.sale_price;
  const discountedPrice = originalPrice * (1 - discount / 100);
  product.calculated_price = discountedPrice * product.quantity;
  return product.calculated_price;
};

// Calcular precio total del pack
const calculateTotalPrice = () => {
  let total = 0;
  formData.value.pack_products.forEach((product) => {
    total += calculateProductPrice(product);
  });
  formData.value.total_price = parseFloat(total.toFixed(2));
};

// Preparar datos para enviar a la API
const preparePackData = () => {
  const packConfig = {};

  formData.value.pack_products.forEach((packProduct) => {
    if (packProduct.product) {
      const unitPrice =
        packProduct.product.sale_price *
        (1 - (packProduct.discount_percentage || 0) / 100);

      packConfig[packProduct.product.id] = {
        quantity: packProduct.quantity,
        discount_percentage: parseFloat(packProduct.discount_percentage),
        sale_price: parseFloat(unitPrice.toFixed(2)),
      };
    }
  });

  return {
    id: formData.value.id || null,
    name: formData.value.name,
    pack_config: packConfig,
    total_price: formData.value.total_price,
    max_quantity: formData.value.max_quantity || null,
    max_sale_date: formatDateForInput(formData.value.max_sale_date),
    is_active: formData.value.is_active,
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

  if (!formData.value.name) {
    formErrors.value.name = "El nombre del pack es requerido";
    return false;
  }

  if (!formData.value.products_count || formData.value.products_count < 1) {
    formErrors.value.products_count = "Debe tener al menos 1 producto";
    return false;
  }

  if (formData.value.products_count > 10) {
    formErrors.value.products_count = "Máximo 10 productos por pack";
    return false;
  }

  return true;
};

// Validar Stage 2
const validateStage2 = () => {
  formErrors.value = {};
  let isValid = true;

  formData.value.pack_products.forEach((product, index) => {
    if (!product.product) {
      formErrors.value[`product_${index}`] = "Selecciona un producto";
      isValid = false;
    }

    if (!product.quantity || product.quantity < 1) {
      formErrors.value[`quantity_${index}`] = "La cantidad debe ser al menos 1";
      isValid = false;
    }

    if (product.product && product.quantity > product.product.stock) {
      formErrors.value[
        `stock_${index}`
      ] = `Stock insuficiente. Disponible: ${product.product.stock}`;
      isValid = false;
    }
  });

  return isValid;
};

// Guardar pack
const savePack = async () => {
  loading.value = true;
  try {
    const packData = preparePackData();
    await emit("pack-saved", packData);
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
    id: null,
    name: "",
    products_count: 1,
    max_quantity: null,
    max_sale_date: null,
    is_active: true,
    pack_products: [],
    total_price: 0,
  };
  formErrors.value = {};
};

const resetProgress = () => {
  currentStageIndex.value = 0;
};
// Computed properties para el wizard

const canProceedToNext = computed(() => {
  if (currentStageIndex.value === 0) {
    return validateStage1(); // Usar la validación real
  } else if (currentStageIndex.value === 1) {
    return validateStage2(); // Usar la validación real
  }
  return true;
});

const handleCompletePurchase = async () => {
  if (currentStageIndex.value === 0) {
    if (validateStage1()) {
      const targetCount = parseInt(formData.value.products_count);
      const currentCount = formData.value.pack_products.length;

      // Add missing slots
      if (currentCount < targetCount) {
        for (let i = currentCount; i < targetCount; i++) {
          formData.value.pack_products.push({
            product: null,
            quantity: 1,
            discount_percentage: 0,
            calculated_price: 0,
          });
        }
      }
      // Remove excess slots
      else if (currentCount > targetCount) {
        formData.value.pack_products.splice(targetCount);
      }

      currentStageIndex.value++;
    }
  } else if (currentStageIndex.value === 1) {
    if (validateStage2()) {
      calculateTotalPrice();
      currentStageIndex.value++;
    }
  } else {
    await savePack();
  }
};

const goBack = () => {
  if (currentStageIndex.value > 0) {
    currentStageIndex.value--;
  }
};

// Cargar datos del pack para edición
const loadPackData = async (packId) => {
  try {
    const response = await getPack(packId);
    if (response.success) {
      const pack = response.data;

      formData.value = {
        id: pack.id,
        name: pack.name,
        products_count: pack.products_count,
        max_quantity: pack.max_quantity,
        max_sale_date: formatDateForInput(pack.max_sale_date),
        is_active: pack.is_active,
        pack_products: [],
        total_price: pack.total_price,
      };

      // Reconstruir pack_products desde pack_config
      if (pack.pack_config) {
        Object.entries(pack.pack_config).forEach(
          ([productId, config], index) => {
            const product = availableProducts.value.find(
              (p) => p.id == productId
            );
            if (product) {
              formData.value.pack_products[index] = {
                product: product,
                quantity: config.quantity,
                discount_percentage: config.discount_percentage,
                calculated_price: config.sale_price * config.quantity,
              };
            }
          }
        );

        // Completar con productos vacíos si es necesario
        while (
          formData.value.pack_products.length < formData.value.products_count
        ) {
          formData.value.pack_products.push({
            product: null,
            quantity: 1,
            discount_percentage: 0,
            calculated_price: 0,
          });
        }
      }
    }
  } catch (error) {
    console.error("Error loading pack data:", error);
  }
};

// Watchers
watch(
  () => props.isDialogVisible,
  async (newVal) => {
    if (newVal) {
      resetProgress();
      await loadAvailableProducts();
      if (props.packData && props.packData.id) {
        await loadPackData(props.packData.id);
      } else {
        await nextTick();
        initializePackProducts();
      }
    }
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
          {{ isEditing ? "Editar Pack" : "Crear Pack" }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal" :disabled="loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <!-- Progreso de los Stage -->
      <VCardText class="pa-4">
        <VStepper :model-value="currentStageIndex + 1" alt-labels>
          <VStepperHeader>
            <VStepperItem
              :value="1"
              title="Información Básica"
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

      <!-- Stage 1: Información básica del pack -->
      <VCardText v-if="currentStageIndex === 0" class="flex-grow-1 pa-6">
        <p class="text-h6 font-weight-medium mb-4">
          Información Básica del Pack
        </p>

        <VRow>
          <VCol cols="12" md="6">
            <VTextField
              v-model="formData.name"
              label="Nombre del Pack *"
              variant="outlined"
              :error-messages="formErrors.name"
              placeholder="Ej: Pack Familiar, Oferta Especial..."
              :disabled="loading"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField
              v-model="formData.products_count"
              label="Cantidad de Productos en el Pack *"
              variant="outlined"
              type="number"
              min="1"
              max="10"
              :error-messages="formErrors.products_count"
              :disabled="loading"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField
              v-model="formData.max_quantity"
              label="Cantidad Máxima de Ventas"
              variant="outlined"
              type="number"
              min="1"
              placeholder="Dejar vacío para ilimitado"
              :disabled="loading"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField
              v-model="formData.max_sale_date"
              label="Fecha Máxima de Venta"
              variant="outlined"
              type="date"
              :disabled="loading"
            />
          </VCol>

          <VCol cols="12">
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
        </VRow>
      </VCardText>

      <!-- Stage 2: Agregar productos -->
      <VCardText v-if="currentStageIndex === 1" class="flex-grow-1 pa-6">
        <div class="d-flex justify-space-between align-center mb-4">
          <p class="text-h6 font-weight-medium mb-0">
            Agregar Productos al Pack
          </p>
          <VChip variant="outlined" color="primary">
            {{ formData.products_count }} producto(s) a configurar
          </VChip>
        </div>

        <p class="text-caption text-medium-emphasis mb-4">
          Complete la información para cada producto del pack
        </p>

        <VCard
          v-for="(packProduct, index) in formData.pack_products"
          :key="index"
          variant="outlined"
          class="mb-4"
        >
          <VCardTitle
            class="text-h6 pa-4 d-flex justify-space-between align-center"
          >
            <span>Producto {{ index + 1 }}</span>
          </VCardTitle>

          <VCardText>
            <VRow>
              <!-- Selección de producto -->
              <VCol cols="12" md="6">
                <VAutocomplete
                  v-model="packProduct.product"
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
                  @update:model-value="calculateTotalPrice()"
                />
              </VCol>

              <!-- Cantidad -->
              <VCol cols="12" md="3">
                <VTextField
                  v-model="packProduct.quantity"
                  label="Unidades a Vender *"
                  variant="outlined"
                  type="number"
                  min="1"
                  :max="packProduct.product?.stock || 9999"
                  :error-messages="
                    formErrors[`quantity_${index}`] ||
                    formErrors[`stock_${index}`]
                  "
                  :disabled="loading"
                  @update:model-value="calculateTotalPrice()"
                />
              </VCol>

              <!-- Descuento -->
              <VCol cols="12" md="3">
                <VTextField
                  v-model="packProduct.discount_percentage"
                  label="Descuento %"
                  variant="outlined"
                  type="number"
                  min="0"
                  max="100"
                  suffix="%"
                  :disabled="loading"
                  @update:model-value="calculateTotalPrice()"
                />
              </VCol>
            </VRow>

            <!-- Detalles del producto seleccionado -->
            <VCard v-if="packProduct.product" variant="tonal" class="mt-4">
              <VCardText>
                <p class="text-h6 mb-2">{{ packProduct.product.name }}</p>
                <VRow>
                  <VCol cols="6" sm="3">
                    <div class="text-caption font-weight-bold">Unidades</div>
                    <div class="text-body-1">
                      {{ packProduct.product.stock }} disp.
                    </div>
                  </VCol>
                  <VCol cols="6" sm="3">
                    <div class="text-caption font-weight-bold">Expira</div>
                    <div class="text-body-1">
                      {{ packProduct.product.next_expiration }}
                    </div>
                  </VCol>
                  <VCol cols="6" sm="3">
                    <div class="text-caption font-weight-bold">Precio</div>
                    <div class="text-body-1">
                      ${{ packProduct.product.sale_price }}
                    </div>
                  </VCol>
                  <VCol cols="6" sm="3">
                    <div class="text-caption font-weight-bold">Costo</div>
                    <div class="text-body-1">
                      ${{ packProduct.product.unit_cost }}
                    </div>
                  </VCol>
                </VRow>

                <!-- Precio con descuento -->
                <VDivider class="my-2" />
                <VRow class="mt-2">
                  <VCol cols="12">
                    <div class="d-flex justify-space-between align-center">
                      <span class="font-weight-bold"
                        >Precio con descuento:</span
                      >
                      <span class="text-h6 text-primary">
                        ${{
                          (
                            calculateProductPrice(packProduct) /
                            packProduct.quantity
                          ).toFixed(2)
                        }}
                        c/u
                      </span>
                    </div>
                    <div class="d-flex justify-space-between align-center mt-1">
                      <span class="font-weight-bold">Subtotal:</span>
                      <span class="text-h6 text-success">
                        ${{ calculateProductPrice(packProduct).toFixed(2) }}
                      </span>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>

            <VAlert v-else type="info" variant="tonal" class="mt-4">
              Selecciona un producto para ver sus detalles
            </VAlert>
          </VCardText>
        </VCard>

        <!-- Precio total -->
        <VCard
          v-if="formData.pack_products.some((p) => p.product)"
          color="primary"
          variant="flat"
          class="mt-4"
        >
          <VCardText class="text-center">
            <span class="text-h5 font-weight-bold text-white">
              Precio Total del Pack: ${{ formData.total_price }}
            </span>
          </VCardText>
        </VCard>
      </VCardText>

      <!-- Stage 3: Resumen final -->
      <VCardText v-if="currentStageIndex === 2" class="flex-grow-1 pa-6">
        <p class="text-h6 font-weight-medium mb-4">Resumen del Pack</p>

        <VRow>
          <VCol cols="12" md="6">
            <VCard variant="outlined">
              <VCardTitle class="text-h6">Información del Pack</VCardTitle>
              <VCardText>
                <VList>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold"
                      >Nombre:</VListItemTitle
                    >
                    <VListItemSubtitle>{{ formData.name }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold"
                      >Precio Total:</VListItemTitle
                    >
                    <VListItemSubtitle class="text-h6 text-success"
                      >${{ formData.total_price }}</VListItemSubtitle
                    >
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold"
                      >Cantidad de Productos:</VListItemTitle
                    >
                    <VListItemSubtitle
                      >{{
                        formData.products_count
                      }}
                      productos</VListItemSubtitle
                    >
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold"
                      >Cant. Máxima de Ventas:</VListItemTitle
                    >
                    <VListItemSubtitle>{{
                      formData.max_quantity || "Ilimitado"
                    }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold"
                      >Fecha Máxima de Venta:</VListItemTitle
                    >
                    <VListItemSubtitle>{{
                      formData.max_sale_date || "Sin fecha límite"
                    }}</VListItemSubtitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle class="font-weight-bold"
                      >Estado:</VListItemTitle
                    >
                    <VListItemSubtitle>
                      <VChip
                        :color="formData.is_active ? 'success' : 'error'"
                        size="small"
                      >
                        {{ formData.is_active ? "Activo" : "Inactivo" }}
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
                  <template
                    v-for="(packProduct, index) in formData.pack_products"
                    :key="index"
                  >
                    <VListItem v-if="packProduct && packProduct.product">
                      <VListItemTitle class="font-weight-bold">
                        {{ packProduct.product.name }}
                      </VListItemTitle>
                      <VListItemSubtitle>
                        {{ packProduct.quantity }} unidades -
                        {{ packProduct.discount_percentage }}% descuento - ${{
                          (
                            calculateProductPrice(packProduct) /
                            packProduct.quantity
                          ).toFixed(2)
                        }}
                        c/u
                      </VListItemSubtitle>
                      <VListItemSubtitle class="text-success font-weight-bold">
                        Subtotal: ${{
                          calculateProductPrice(packProduct).toFixed(2)
                        }}
                      </VListItemSubtitle>
                    </VListItem>
                  </template>

                  <!-- Mostrar mensaje si no hay productos válidos -->
                  <VListItem
                    v-if="!formData.pack_products.some((p) => p && p.product)"
                  >
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
