<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  packData: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "modal-closed",
  "pack-saved",
]);

const { mobile } = useDisplay();

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
const isSaving = ref(false);
const isLoadingData = ref(false);
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
      itemsPerPage: 500,
      sortBy: "name",
      orderBy: "asc",
    };

    const response = await axios.get("/products", { params });
    const items = Array.isArray(response.data?.data) ? response.data.data : [];
    if (items.length > 0) {
      // Mantener los productos ya seleccionados en el pack para que no desaparezcan de la lista
      const currentSelectedProducts = formData.value.pack_products
        .filter(item => item && item.product)
        .map(item => item.product);

      const allProducts = [...items, ...currentSelectedProducts];
      
      const seenIds = new Set();
      availableProducts.value = allProducts
        .filter((product) => {
          if (!product || !product.id || seenIds.has(product.id)) return false;
          seenIds.add(product.id);
          return true;
        })
        .map((product) => ({
          ...product,
          stock: product.stock_calculado || product.stock || 0,
        }));
    } else {
      // Si no hay resultados nuevos, al menos mantenemos los seleccionados
      const currentSelectedProducts = formData.value.pack_products
        .filter(item => item && item.product)
        .map(item => item.product);
      
      const seenIds = new Set();
      availableProducts.value = currentSelectedProducts.filter(p => {
        if (!p || !p.id || seenIds.has(p.id)) return false;
        seenIds.add(p.id);
        return true;
      });
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
  addProductRow();
};

const addProductRow = () => {
  if (formData.value.pack_products.length < 10) {
    formData.value.pack_products.push({
      product: null,
      quantity: 1,
      discount_percentage: 0,
      calculated_price: 0,
    });
    formData.value.products_count = formData.value.pack_products.length;
  } else {
    toast.warning("Máximo 10 productos por pack");
  }
};

const removeProductRow = (index) => {
  formData.value.pack_products.splice(index, 1);
  formData.value.products_count = formData.value.pack_products.length;
  calculateTotalPrice();
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

  isSaving.value = true;
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

    emit("pack-saved", packData);
    
    // Resetear isSaving para permitir re-intentos si el padre no cierra el modal
    isSaving.value = false;
    
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
    isSaving.value = false;
  }
};

const closeModal = () => {
  if (props.loading) {
    return; // Solo bloquear si hay una carga activa del servidor
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
  isSaving.value = false;
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
        products_count: pack.products_count || Object.keys(pack.pack_config || {}).length,
        max_quantity: pack.max_quantity,
        max_sale_date: formatDateForInput(pack.max_sale_date),
        is_active: pack.is_active,
        pack_products: [],
        total_price: pack.total_price,
      };

      // Reconstruir pack_products desde pack_config
      if (pack.pack_config) {
        const configEntries = Object.entries(pack.pack_config);
        
        formData.value.pack_products = configEntries.map(([productId, config]) => {
          // Primero buscar en availableProducts
          let product = availableProducts.value.find(p => p.id == productId);
          
          // Si no está, buscar en products_info del pack
          if (!product && pack.products_info) {
            const info = pack.products_info.find(i => i.product_id == productId);
            if (info) {
              product = {
                id: info.product_id,
                name: info.product_name,
                sale_price: info.sale_price_original || info.sale_price, // El original si existiera, sino el del pack
                stock: info.product_info?.stock || 0,
                barcode: info.product_info?.barcode || ""
              };
            }
          }
          
          return {
            product: product || null,
            quantity: config.quantity || 1,
            discount_percentage: config.discount_percentage || 0,
            calculated_price: (config.sale_price || 0) * (config.quantity || 1),
          };
        });

        // Intentar recargar la lista de todos modos para tener la info completa si faltaba
        if (formData.value.pack_products.some(p => !p.product)) {
          loadAvailableProducts().then(() => {
            configEntries.forEach(([productId, config], index) => {
              if (!formData.value.pack_products[index].product) {
                const foundProduct = availableProducts.value.find(p => p.id == productId);
                if (foundProduct) {
                  formData.value.pack_products[index].product = foundProduct;
                }
              }
            });
          });
        }

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
  } finally {
    isLoadingData.value = false;
  }
};

// Carga rápida desde props para evitar modal vacío
const quickLoadFromProps = () => {
  if (!props.packData) return;
  const pack = props.packData;
  
  formData.value = {
    id: pack.id,
    name: pack.name,
    products_count: pack.products_count || (pack.pack_config ? Object.keys(pack.pack_config).length : 0),
    max_quantity: pack.max_quantity,
    max_sale_date: formatDateForInput(pack.max_sale_date),
    is_active: pack.is_active,
    pack_products: [],
    total_price: pack.total_price,
  };

  if (pack.products_info && pack.pack_config) {
    const configEntries = Object.entries(pack.pack_config);
    formData.value.pack_products = configEntries.map(([productId, config]) => {
      const info = pack.products_info.find(i => i.product_id == productId);
      return {
        product: info ? {
          id: info.product_id,
          name: info.product_name,
          sale_price: info.sale_price_original || info.sale_price,
          stock: info.product_info?.stock || 0,
          barcode: info.product_info?.barcode || ""
        } : null,
        quantity: config.quantity || 1,
        discount_percentage: config.discount_percentage || 0,
        calculated_price: (config.sale_price || 0) * (config.quantity || 1),
      };
    });
  }
};

// Watchers
watch(
  () => props.isDialogVisible,
  async (newVal) => {
    if (newVal) {
      resetForm(); 
      isLoadingData.value = true;
      
      const promises = [];
      
      // Si no tenemos productos cargados o es una búsqueda nueva, cargamos
      promises.push(loadAvailableProducts());

      if (props.packData && props.packData.id) {
        quickLoadFromProps();
        promises.push(loadPackData(props.packData.id));
      } else {
        initializePackProducts();
        isLoadingData.value = false;
      }

      if (promises.length > 0) {
        await Promise.all(promises);
      }
      
      isLoadingData.value = false;
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="dialogVisible"
    :max-inline-size="mobile ? '100%' : '1000px'"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    @keydown.esc.prevent="closeModal"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium Standard -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
            <VIcon icon="tabler-package" color="primary" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ isEditing ? "Editar Pack de Oferta" : "Crear Nuevo Pack" }}
            </h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
              Configuración de Promociones
            </span>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="closeModal"
            class="rounded-lg"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light position-relative">
        <!-- Overlay de Carga -->
        <VOverlay
          :model-value="isLoadingData"
          contained
          persistent
          class="align-center justify-center"
          scrim="white"
          opacity="0.7"
        >
          <div class="d-flex flex-column align-center">
            <VProgressCircular
              indeterminate
              color="primary"
              size="64"
              width="6"
            />
            <span class="mt-4 font-weight-black text-primary uppercase letter-spacing-1">Cargando detalles del pack...</span>
          </div>
        </VOverlay>

        <div class="pa-3 pa-sm-4" :style="{ opacity: isLoadingData ? 0.3 : 1, pointerEvents: isLoadingData ? 'none' : 'auto' }">
          <!-- Información del Pack -->
          <div class="d-flex align-center gap-2 mb-4">
            <div class="header-indicator primary shadow-sm"></div>
            <span class="text-subtitle-2 font-weight-black text-primary uppercase letter-spacing-1">Datos Generales del Pack</span>
          </div>

          <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border mb-6">
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="formData.name"
                  label="Nombre del Pack"
                  placeholder="Ej: Trío de Vitaminas..."
                  :error-messages="formErrors.name"
                  class="shadow-sm"
                />
              </VCol>
              <VCol cols="12" md="3">
                <AppTextField
                  v-model.number="formData.max_quantity"
                  label="Límite de Ventas"
                  type="number"
                  placeholder="Ilimitado"
                  class="shadow-sm"
                />
              </VCol>
              <VCol cols="12" md="3">
                <AppTextField
                  v-model="formData.max_sale_date"
                  label="Fecha de Vencimiento"
                  type="date"
                  class="shadow-sm"
                />
              </VCol>
            </VRow>
            <VRow class="mt-2">
              <VCol cols="12">
                <div class="d-flex align-center gap-2">
                  <VCheckbox
                    v-model="formData.is_active"
                    label="Oferta Activa"
                    hide-details
                    density="compact"
                  />
                  <span class="text-xs text-disabled italic">(Si se desactiva, el pack no aparecerá en el TPV)</span>
                </div>
              </VCol>
            </VRow>
          </VCard>

          <!-- Productos -->
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center gap-2">
              <div class="header-indicator secondary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-secondary uppercase letter-spacing-1">Productos Incluidos</span>
            </div>
            <VBtn
              variant="tonal"
              color="primary"
              size="small"
              class="rounded-lg font-weight-black"
              @click="addProductRow"
              :disabled="formData.pack_products.length >= 10"
            >
              <VIcon start size="18">tabler-plus</VIcon>
              Añadir Producto
            </VBtn>
          </div>

          <div v-for="(item, index) in formData.pack_products" :key="index" class="mb-2">
            <VCard v-if="item" variant="flat" class="border pa-2 pa-sm-3 bg-white rounded-lg elevation-1 relative overflow-visible">
              <!-- Botón eliminar flotante -->
              <VBtn
                v-if="formData.pack_products.length > 1"
                icon="tabler-trash"
                color="error"
                variant="flat"
                size="x-small"
                class="position-absolute rounded-circle elevation-2 shadow-sm"
                style="inset-inline-end: -8px; inset-block-start: -8px; z-index: 2;"
                @click="removeProductRow(index)"
              />

              <VRow dense class="align-center-mobile py-1">
                <VCol cols="12" md="6">
                  <AppAutocomplete
                    v-model="item.product"
                    :items="availableProducts"
                    item-title="name"
                    item-value="id"
                    label="Producto"
                    placeholder="Buscar por nombre, ID o código..."
                    variant="outlined"
                    density="compact"
                    hide-details
                    :loading="loadingProducts"
                    :no-filter="true"
                    return-object
                    clearable
                    @update:search="handleProductSearch"
                    @update:model-value="calculateTotalPrice()"
                    class="shadow-sm"
                  >
                    <template #item="{ props: itemProps, item: productItem }">
                      <VListItem v-bind="itemProps" :title="productItem.raw.name" :subtitle="`ID: #${productItem.raw.id} | Stock: ${productItem.raw.stock} | Código: ${productItem.raw.barcode || 'N/A'}`" />
                    </template>
                  </AppAutocomplete>
                </VCol>
                <VCol cols="4" md="2">
                  <AppTextField
                    v-model.number="item.quantity"
                    label="Cant."
                    type="number"
                    min="1"
                    density="compact"
                    hide-details
                    @update:model-value="calculateTotalPrice()"
                    class="shadow-sm text-center"
                  />
                </VCol>
                <VCol cols="4" md="2">
                  <AppTextField
                    v-model.number="item.discount_percentage"
                    label="Desc. %"
                    type="number"
                    min="0"
                    max="100"
                    density="compact"
                    hide-details
                    @update:model-value="calculateTotalPrice()"
                    class="shadow-sm text-center"
                  />
                </VCol>
                <VCol cols="4" md="2" class="text-end">
                  <div class="d-flex flex-column pe-2">
                    <span class="text-super-xs text-disabled uppercase font-weight-black">Subtotal</span>
                    <span class="text-subtitle-1 font-weight-black text-success leading-none">
                      {{ formatCurrency(calculateProductPrice(item), 'USD') }}
                    </span>
                  </div>
                </VCol>
              </VRow>
            </VCard>
          </div>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-4 bg-white border-t">
        <div class="d-flex align-center flex-grow-1">
          <div class="d-flex flex-column">
            <span class="text-super-xs text-disabled font-weight-black uppercase leading-none mb-1">Inversión Final Pack</span>
            <span class="text-h4 font-weight-950 text-primary leading-none">
              {{ formatCurrency(formData.total_price, 'USD') }}
            </span>
          </div>
          <VSpacer />
          <div class="d-flex gap-3">
            <VBtn color="secondary" variant="tonal" class="rounded-lg font-weight-black px-6" @click="closeModal">
              CANCELAR
            </VBtn>
            <VBtn color="primary" variant="flat" class="rounded-lg font-weight-950 shadow-primary px-8" @click="savePack" :loading="isSaving || props.loading">
              <VIcon start>tabler-device-floppy</VIcon>
              {{ isEditing ? "ACTUALIZAR" : "CREAR PACK" }}
            </VBtn>
          </div>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.detail-dialog-card {
  border-radius: 16px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

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

.leading-none { line-height: 1 !important; }
.font-weight-950 { font-weight: 950 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
