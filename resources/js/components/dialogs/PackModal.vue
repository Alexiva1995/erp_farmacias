<script setup>
import axios from "@/plugins/axios";
import { computed, nextTick, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";

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

const formData = ref({
  id: null,
  name: "",
  products_count: 1,
  max_quantity: null,
  max_sale_date: null,
  is_active: true,
  pack_products: [],
  total_price: 0,
});

const formErrors = ref({});
const loading = ref(false);
const availableProducts = ref([]);
const loadingProducts = ref(false);
const productSearchQuery = ref("");

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const isEditing = computed(() => props.packData && props.packData.id);

// Cargar productos con el mismo filtrado que /inventory/products
const loadAvailableProducts = async (search = "") => {
  loadingProducts.value = true;
  try {
    const trimmedSearch = String(search ?? "").trim();
    const params = {
      q: trimmedSearch || undefined,
      itemsPerPage: 100,
      isStrictSearch: false,
      sortBy: "name",
      orderBy: "asc",
    };
    // Si es solo número, enviar product_id (snake_case) para búsqueda directa por ID
    if (/^\d+$/.test(trimmedSearch)) {
      params.product_id = parseInt(trimmedSearch, 10);
    }
    Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);

    console.log("Enviando búsqueda:", params);

    const response = await axios.get("/products", { params });
    const items = Array.isArray(response.data?.data) ? response.data.data : [];
    if (items.length > 0) {
      const seenIds = new Set();
      availableProducts.value = items
        .filter((product) => {
          if (seenIds.has(product.id)) return false;
          seenIds.add(product.id);
          return true;
        })
        .map((product) => ({
          id: product.id,
          name: product.name,
          active_ingredient: product.active_ingredient,
          stock: product.stock_calculado || product.stock || 0,
          sale_price: product.sale_price,
          unit_cost: product.unit_cost,
          next_expiration: product.next_expiration,
          laboratory: product.laboratory?.name,
          photo_url: product.photo_url,
          barcode: product.barcode,
        }));
    } else {
      availableProducts.value = [];
    }
  } catch (error) {
    console.error("Error loading products:", error);
    toast.error("Error al cargar productos");
  } finally {
    loadingProducts.value = false;
  }
};

// Búsqueda con debounce
let searchTimeout;
const handleProductSearch = (search) => {
  const searchStr = String(search ?? "");
  productSearchQuery.value = searchStr;
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadAvailableProducts(searchStr);
  }, 300);
};

// Obtener pack por ID
const getPack = async (packId) => {
  try {
    const response = await axios.get(`/tpv/promotions/product-packs/${packId}`);
    return response.data;
  } catch (error) {
    console.error("Error fetching pack:", error);
    throw error;
  }
};

// Inicializar productos del pack
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
    if (packProduct.product && packProduct.product.id) {
      const unitPrice =
        packProduct.product.sale_price *
        (1 - (packProduct.discount_percentage || 0) / 100);

      packConfig[packProduct.product.id] = {
        quantity: parseInt(packProduct.quantity) || 1,
        discount_percentage: parseFloat(packProduct.discount_percentage || 0),
        sale_price: parseFloat(unitPrice.toFixed(2)),
      };
    }
  });

  // Validar que haya al menos un producto en el pack
  if (Object.keys(packConfig).length === 0) {
    throw new Error("Debe agregar al menos un producto al pack");
  }

  const packData = {
    name: formData.value.name.trim(),
    pack_config: packConfig,
    total_price: parseFloat(formData.value.total_price.toFixed(2)),
    max_quantity: formData.value.max_quantity ? parseInt(formData.value.max_quantity) : null,
    max_sale_date: formatDateForInput(formData.value.max_sale_date) || null,
    is_active: formData.value.is_active !== undefined ? formData.value.is_active : true,
  };

  // Si es edición, agregar el ID
  if (formData.value.id) {
    packData.id = formData.value.id;
  }

  return packData;
};

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// Validar formulario
const validateForm = () => {
  formErrors.value = {};

  if (!formData.value.name || formData.value.name.trim() === "") {
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
  if (!validateForm()) {
    toast.error("Por favor, completa todos los campos requeridos");
    return;
  }

  loading.value = true;
  try {
    // Calcular precio total antes de preparar datos
    calculateTotalPrice();
    
    // Validar que el precio total sea mayor a 0
    if (formData.value.total_price <= 0) {
      toast.error("El precio total del pack debe ser mayor a 0");
      loading.value = false;
      return;
    }

    const packData = preparePackData();
    
    // Validar que pack_config no esté vacío
    if (!packData.pack_config || Object.keys(packData.pack_config).length === 0) {
      toast.error("Debe agregar al menos un producto al pack");
      loading.value = false;
      return;
    }

    console.log("Datos a enviar:", packData);
    
    // Emitir evento - el componente padre manejará el guardado
    emit("pack-saved", packData);
    
  } catch (error) {
    console.error("Error saving pack:", error);
    const errorMessage = error.response?.data?.message || error.message || "Error al guardar el pack";
    toast.error(errorMessage);
    
    // Mostrar errores de validación si existen
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      Object.keys(errors).forEach((key) => {
        formErrors.value[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
      });
    }
    loading.value = false;
  }
};

const closeModal = () => {
  if (loading.value) {
    return; // No cerrar si está guardando
  }
  emit("update:isDialogVisible", false);
  emit("modal-closed");
  resetForm();
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
  productSearchQuery.value = "";
};

// Ajustar productos cuando cambia products_count
watch(
  () => formData.value.products_count,
  (newCount) => {
    const currentCount = formData.value.pack_products.length;
    if (newCount > currentCount) {
      for (let i = currentCount; i < newCount; i++) {
        formData.value.pack_products.push({
          product: null,
          quantity: 1,
          discount_percentage: 0,
          calculated_price: 0,
        });
      }
    } else if (newCount < currentCount) {
      formData.value.pack_products.splice(newCount);
    }
    calculateTotalPrice();
  }
);

// Cargar datos del pack para edición
const loadPackData = async (packId) => {
  try {
    const response = await getPack(packId);
    if (response.success) {
      const pack = response.data;

      formData.value = {
        id: pack.id,
        name: pack.name,
        products_count: pack.products_count || Object.keys(pack.pack_config || {}).length,
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
            } else {
              // Si no está en availableProducts, cargar el producto
              loadAvailableProducts().then(() => {
                const foundProduct = availableProducts.value.find(
                  (p) => p.id == productId
                );
                if (foundProduct) {
                  formData.value.pack_products[index] = {
                    product: foundProduct,
                    quantity: config.quantity,
                    discount_percentage: config.discount_percentage,
                    calculated_price: config.sale_price * config.quantity,
                  };
                }
              });
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
    toast.error("Error al cargar los datos del pack");
  }
};

// Watchers
watch(
  () => props.isDialogVisible,
  async (newVal) => {
    if (newVal) {
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
</script>

<template>
  <VDialog
    :model-value="dialogVisible"
    max-width="1000px"
    persistent
    scrollable
    :retain-focus="false"
    @click:outside.prevent
    @keydown.esc.prevent="closeModal"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-package" size="24" color="white" />
          <span class="text-h6 text-white">
            {{ isEditing ? "Editar Pack" : "Crear Pack" }}
          </span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="closeModal" :disabled="loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <!-- Información del Pack -->
        <div class="mb-6">
          <VRow>
            <VCol cols="12" sm="6" md="4">
              <AppTextField
                v-model="formData.name"
                label="Nombre del Pack *"
                variant="outlined"
                :error-messages="formErrors.name"
                placeholder="Ej: Pack Familiar..."
                :disabled="loading"
              />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <AppTextField
                v-model.number="formData.products_count"
                label="Cant. Productos *"
                variant="outlined"
                type="number"
                min="1"
                max="10"
                :error-messages="formErrors.products_count"
                :disabled="loading"
              />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <AppSelect
                v-model="formData.is_active"
                label="Estado"
                :items="[
                  { title: 'Activo', value: true },
                  { title: 'Inactivo', value: false },
                ]"
                item-title="title"
                item-value="value"
                :disabled="loading"
              />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <AppTextField
                v-model.number="formData.max_quantity"
                label="Cant. Máx. Ventas"
                variant="outlined"
                type="number"
                min="1"
                placeholder="Ilimitado si vacío"
                :disabled="loading"
              />
            </VCol>
            <VCol cols="12" sm="6" md="2" class="date-input-compact">
              <AppTextField
                v-model="formData.max_sale_date"
                label="Fecha Máx. Venta"
                variant="outlined"
                type="date"
                density="compact"
                hide-details
                :disabled="loading"
              />
            </VCol>
          </VRow>
        </div>

        <VDivider class="my-4" />

        <!-- Productos del Pack -->
        <div>
          <div class="d-flex align-center justify-space-between mb-4">
            <h3 class="text-h6 font-weight-medium">Productos del Pack</h3>
            <VChip variant="outlined" color="primary" size="small">
              {{ formData.products_count }} producto(s)
            </VChip>
          </div>

          <VDataTable
            :headers="[
              { title: 'Producto', key: 'product', sortable: false, width: '35%' },
              { title: 'Cantidad', key: 'quantity', sortable: false, width: '15%', align: 'center' },
              { title: 'Descuento %', key: 'discount', sortable: false, width: '15%', align: 'center' },
              { title: 'Precio Unit.', key: 'unit_price', sortable: false, width: '15%', align: 'end' },
              { title: 'Subtotal', key: 'subtotal', sortable: false, width: '20%', align: 'end' },
            ]"
            :items="formData.pack_products"
            density="comfortable"
            class="rounded-lg"
            no-data-text="No hay productos agregados"
          >
            <template #item.product="{ item, index }">
              <VAutocomplete
                v-model="item.product"
                :items="availableProducts"
                item-title="name"
                item-value="id"
                variant="outlined"
                :loading="loadingProducts"
                :error-messages="formErrors[`product_${index}`]"
                return-object
                clearable
                :disabled="loading"
                placeholder="Buscar por ID, Producto, C. Activo..."
                :custom-filter="() => true"
                @update:search="handleProductSearch"
                @update:model-value="calculateTotalPrice()"
              >
                <template #item="{ props: itemProps, item: productItem }">
                  <VListItem v-bind="{ ...itemProps, title: '' }">
                    <template v-if="productItem.raw.photo_url" #prepend>
                      <VAvatar size="40" :image="productItem.raw.photo_url" variant="tonal" />
                    </template>
                    <VListItemTitle>{{ productItem.raw.name }}</VListItemTitle>
                    <VListItemSubtitle>
                      ID: {{ productItem.raw.id }} | Stock: {{ productItem.raw.stock }} | Precio: ${{ productItem.raw.sale_price }}
                      <span v-if="productItem.raw.barcode"> | Código: {{ productItem.raw.barcode }}</span>
                    </VListItemSubtitle>
                  </VListItem>
                </template>
              </VAutocomplete>
            </template>

            <template #item.quantity="{ item, index }">
              <VTextField
                v-model.number="item.quantity"
                type="number"
                variant="outlined"
                hide-details
                min="1"
                :max="item.product?.stock || 9999"
                :error-messages="formErrors[`quantity_${index}`] || formErrors[`stock_${index}`]"
                :disabled="loading || !item.product"
                style="max-width: 100px"
                class="mx-auto"
                @update:model-value="calculateTotalPrice()"
              />
            </template>

            <template #item.discount="{ item, index }">
              <VTextField
                v-model.number="item.discount_percentage"
                type="number"
                variant="outlined"
                hide-details
                min="0"
                max="100"
                suffix="%"
                :disabled="loading || !item.product"
                style="max-width: 100px"
                class="mx-auto"
                @update:model-value="calculateTotalPrice()"
              />
            </template>

            <template #item.unit_price="{ item }">
              <span v-if="item.product" class="text-body-2">
                ${{
                  (
                    (item.product.sale_price *
                      (1 - (item.discount_percentage || 0) / 100)) /
                    1
                  ).toFixed(2)
                }}
              </span>
              <span v-else class="text-disabled">—</span>
            </template>

            <template #item.subtotal="{ item }">
              <span v-if="item.product" class="text-body-1 font-weight-medium text-success">
                ${{ calculateProductPrice(item).toFixed(2) }}
              </span>
              <span v-else class="text-disabled">—</span>
            </template>
          </VDataTable>

          <!-- Precio Total -->
          <VCard
            v-if="formData.pack_products.some((p) => p.product)"
            color="primary"
            variant="flat"
            class="mt-4"
          >
            <VCardText class="d-flex align-center justify-space-between">
              <span class="text-h6 font-weight-bold text-white">Precio Total del Pack:</span>
              <span class="text-h5 font-weight-bold text-white">
                ${{ formData.total_price.toFixed(2) }}
              </span>
            </VCardText>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="closeModal"
              :disabled="loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              @click="savePack"
              :loading="loading"
            >
              {{ isEditing ? "Actualizar" : "Guardar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.date-input-compact :deep(input) {
  font-size: 0.8rem;
}

:deep(.v-data-table) {
  border-radius: 8px;
}

:deep(.v-data-table th) {
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

:deep(.v-data-table td) {
  padding: 12px 16px;
}
</style>
