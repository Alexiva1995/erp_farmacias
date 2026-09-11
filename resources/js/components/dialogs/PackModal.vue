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
  const trimmedSearch = String(search ?? "").trim();
  if (trimmedSearch.length > 0 && trimmedSearch.length < 2) return;

  loadingProducts.value = true;
  try {
    const params = {
      q: trimmedSearch || undefined,
      itemsPerPage: 20,
      sortBy: "name",
      orderBy: "asc",
    };

    const response = await axios.get("/products", { params });
    const items = Array.isArray(response.data?.data) ? response.data.data : [];
    if (items.length > 0) {
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
  const originalPrice = Number(product.product.sale_price) || 0;
  const discountedPrice = originalPrice * (1 - discount / 100);
  product.calculated_price = discountedPrice * (Number(product.quantity) || 1);
  return product.calculated_price;
};

// Calcular precio total del pack y ahorro
const regularTotalPrice = computed(() => {
  return formData.value.pack_products.reduce((acc, item) => {
    if (!item.product) return acc;
    const base = Number(item.product.sale_price) || 0;
    const qty = Number(item.quantity) || 1;
    return acc + (base * qty);
  }, 0);
});

const totalSavings = computed(() => {
  const savings = regularTotalPrice.value - formData.value.total_price;
  return savings > 0 ? savings : 0;
});

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
  const seenProductIds = new Set();
  formData.value.pack_products.forEach((product, index) => {
    if (!product.product) {
      formErrors.value[`product_${index}`] = "Selecciona un producto";
      isValid = false;
    } else {
      if (seenProductIds.has(product.product.id)) {
        formErrors.value[`product_${index}`] = "Este producto ya está en el pack. Modifica su cantidad en lugar de añadirlo dos veces.";
        isValid = false;
      }
      seenProductIds.add(product.product.id);
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
    calculateTotalPrice();
    
    if (formData.value.total_price <= 0) {
      toast.error("El precio total del pack debe ser mayor a 0");
      isSaving.value = false;
      return;
    }

    const packData = preparePackData();
    
    if (!packData.pack_config || Object.keys(packData.pack_config).length === 0) {
      toast.error("Debe agregar al menos un producto al pack");
      isSaving.value = false;
      return;
    }

    emit("pack-saved", packData);
    isSaving.value = false;
  } catch (error) {
    console.error("Error saving pack:", error);
    toast.error(error.message || "Error al guardar el pack");
    isSaving.value = false;
  }
};

// Cerrar modal
const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
  resetForm();
};

// Resetear formulario
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
  availableProducts.value = [];
  productSearchQuery.value = "";
  initializePackProducts();
};

// Cargar datos del pack para edición
const loadPackData = async (packId) => {
  isLoadingData.value = true;
  try {
    const response = await getPack(packId);
    const pack = response.success ? response.data : response;

    formData.value = {
      id: pack.id,
      name: pack.name,
      products_count: pack.products_count || 1,
      max_quantity: pack.max_quantity || null,
      max_sale_date: pack.max_sale_date ? formatDateForInput(pack.max_sale_date) : null,
      is_active: pack.is_active !== undefined ? pack.is_active : true,
      total_price: parseFloat(pack.total_price) || 0,
      pack_products: [],
    };

    const rawProductsList = pack.products_info || pack.products || [];
    
    if (rawProductsList && rawProductsList.length > 0) {
      formData.value.pack_products = rawProductsList.map((item) => {
        let productId = item.product_id || item.id;
        let productName = item.product_name || item.name;
        let discountPercentage = 0;
        let quantity = 1;
        let originalSalePrice = 0;
        let stock = 0;
        let laboratory = 'S/L';

        // Intentar leer desde pack_config
        const configKey = String(productId);
        const config = (pack.pack_config && typeof pack.pack_config === 'object')
          ? (pack.pack_config[configKey] || pack.pack_config[productId])
          : null;

        if (config && typeof config === 'object') {
          discountPercentage = parseFloat(config.discount_percentage) || 0;
          quantity = parseInt(config.quantity) || 1;
        } else if (item.discount_percentage !== undefined) {
          discountPercentage = parseFloat(item.discount_percentage) || 0;
          quantity = parseInt(item.quantity) || (item.pivot?.quantity ? parseInt(item.pivot.quantity) : 1);
        } else if (item.pivot && item.pivot.discount_percentage !== undefined) {
          discountPercentage = parseFloat(item.pivot.discount_percentage) || 0;
          quantity = parseInt(item.pivot.quantity) || 1;
        }

        // Obtener precio base original y datos de producto
        if (item.product_info) {
          stock = item.product_info.stock || 0;
          laboratory = item.product_info.laboratory || 'S/L';
        } else {
          stock = item.stock_calculado || item.stock || 0;
          laboratory = item.laboratory?.name || item.laboratory || 'S/L';
        }

        originalSalePrice = item.sale_price_original || item.product?.sale_price || item.sale_price || 0;
        
        // Si sale_price viene con el descuento aplicado desde pack_config o products_info, pero discount_percentage > 0, restaurar precio base original si es posible
        if (discountPercentage > 0 && originalSalePrice > 0 && item.sale_price && Math.abs(originalSalePrice - item.sale_price) < 0.001) {
          // Si originalSalePrice era ya el precio con descuento
          // originalPrice = salePrice / (1 - discount/100)
        }

        const formattedProduct = {
          id: productId,
          name: productName,
          sale_price: parseFloat(originalSalePrice) || 0,
          stock: stock,
          laboratory: laboratory,
          active_ingredient: item.product_info?.active_ingredient || item.active_ingredient || '',
        };

        const unitPrice = formattedProduct.sale_price * (1 - discountPercentage / 100);
        const calculatedPrice = unitPrice * quantity;

        return {
          product: formattedProduct,
          quantity: quantity,
          discount_percentage: discountPercentage,
          calculated_price: parseFloat(calculatedPrice.toFixed(2)),
        };
      });

      const existingProducts = formData.value.pack_products.map(item => item.product);
      availableProducts.value = existingProducts;
      calculateTotalPrice();
    } else {
      initializePackProducts();
    }
  } catch (error) {
    console.error("Error loading pack data:", error);
    toast.error("Error al cargar los datos del pack");
    initializePackProducts();
  } finally {
    isLoadingData.value = false;
  }
};

// Watchers
watch(
  () => props.isDialogVisible,
  async (isVisible) => {
    if (isVisible) {
      if (props.packData && props.packData.id) {
        await loadPackData(props.packData.id);
      } else {
        resetForm();
        await loadAvailableProducts();
      }
    } else {
      resetForm();
    }
  },
  { immediate: true }
);

watch(
  () => props.packData,
  async (newPackData) => {
    if (newPackData && newPackData.id && props.isDialogVisible) {
      await loadPackData(newPackData.id);
    }
  },
  { deep: true }
);

// Watch para recalcular precio cuando cambien los productos
watch(
  () => formData.value.pack_products,
  () => {
    nextTick(() => {
      calculateTotalPrice();
    });
  },
  { deep: true }
);
</script>

<template>
  <VDialog
    :model-value="dialogVisible"
    max-width="680px"
    width="680px"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
    :fullscreen="mobile"
    @click:outside.prevent
    @keydown.esc.prevent="closeModal"
  >
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="38"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-packages"
              size="22"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ isEditing ? "Editar Pack de Productos" : "Crear Nuevo Pack" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Configuración de Promociones y Combos
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeModal"
            :disabled="isSaving"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface position-relative">
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
              size="48"
              width="4"
            />
            <span class="mt-3 font-weight-black text-primary text-xs uppercase letter-spacing-1">Cargando detalles del pack...</span>
          </div>
        </VOverlay>

        <div :style="{ opacity: isLoadingData ? 0.3 : 1, pointerEvents: isLoadingData ? 'none' : 'auto' }">
          <!-- Bloque 1: Datos Generales -->
          <div class="mb-5">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center gap-1-5">
                <div class="header-indicator primary" />
                <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Datos Generales del Pack</span>
              </div>
              <div class="d-flex align-center gap-2">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Activo</span>
                <VSwitch
                  v-model="formData.is_active"
                  color="primary"
                  hide-details
                  density="compact"
                  inset
                />
              </div>
            </div>

            <VRow dense>
              <VCol cols="12" md="6">
                <div>
                  <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Nombre del Pack *</span>
                  <VTextField
                    v-model="formData.name"
                    placeholder="Ej: Trío de Vitaminas..."
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    class="rounded-lg font-weight-bold"
                    :error="!!formErrors.name"
                    :error-messages="formErrors.name"
                    :disabled="isSaving"
                  />
                </div>
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <div>
                  <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Límite de Ventas</span>
                  <VTextField
                    v-model.number="formData.max_quantity"
                    type="number"
                    min="1"
                    placeholder="Ilimitado"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                    prepend-inner-icon="tabler-hash"
                    class="rounded-lg font-weight-bold"
                    :disabled="isSaving"
                  />
                </div>
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <div>
                  <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha de Vencimiento</span>
                  <AppDateTimePicker
                    v-model="formData.max_sale_date"
                    placeholder="SELECCIONAR FECHA"
                    prepend-inner-icon="tabler-calendar-event"
                    density="compact"
                    hide-details="auto"
                    class="rounded-lg"
                    :disabled="isSaving"
                    :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                  />
                </div>
              </VCol>
            </VRow>
          </div>

          <!-- Bloque 2: Productos Incluidos -->
          <div class="mb-2">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-1-5">
                <div class="header-indicator primary" />
                <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Productos Incluidos</span>
                <span class="text-super-xs font-weight-bold text-disabled">({{ formData.pack_products.length }}/10)</span>
              </div>
              <VBtn
                variant="outlined"
                color="primary"
                size="small"
                class="rounded-lg font-weight-black"
                @click="addProductRow"
                :disabled="formData.pack_products.length >= 10 || isSaving"
              >
                <VIcon start size="16">tabler-plus</VIcon>
                Añadir Producto
              </VBtn>
            </div>

            <div class="d-flex flex-column gap-2">
              <div
                v-for="(item, index) in formData.pack_products"
                :key="index"
                class="pack-item-row pa-3 rounded-lg border bg-var-theme-background"
              >
                <VRow dense class="align-center">
                  <!-- Buscador de Producto -->
                  <VCol cols="12" md="6">
                    <div class="d-flex align-center justify-space-between flex-wrap gap-1 mb-1">
                      <span class="text-super-xs font-weight-black text-high-emphasis uppercase">Producto #{{ index + 1 }}</span>
                      
                      <!-- Resumen de precio y stock al lado de la etiqueta del producto -->
                      <div v-if="item.product" class="d-flex align-center flex-wrap gap-1">
                        <span class="text-super-xs font-weight-black text-primary bg-primary-lighten-5 px-1-5 py-0-5 rounded">
                          {{ formatCurrency(item.product.sale_price, 'USD') }}
                        </span>
                        <span class="text-super-xs font-weight-black text-success bg-success-lighten-5 px-1-5 py-0-5 rounded">
                          Stock: {{ item.product.stock }}
                        </span>
                        <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">
                          {{ item.product.laboratory?.name || item.product.laboratory || 'S/L' }}
                        </span>
                      </div>
                    </div>

                    <AppAutocomplete
                      v-model="item.product"
                      :items="availableProducts"
                      item-title="name"
                      item-value="id"
                      placeholder="Buscar por nombre, ID o código..."
                      variant="outlined"
                      density="compact"
                      hide-details="auto"
                      :loading="loadingProducts"
                      :no-filter="true"
                      return-object
                      clearable
                      @update:search="handleProductSearch"
                      @update:model-value="calculateTotalPrice()"
                      class="rounded font-weight-bold"
                      :error="!!formErrors[`product_${index}`]"
                      :error-messages="formErrors[`product_${index}`]"
                      :disabled="isSaving"
                    >
                      <template #item="{ props: itemProps, item: productItem }">
                        <VListItem
                          v-bind="itemProps"
                          :title="productItem.raw.name"
                          :subtitle="`ID: #${productItem.raw.id} | Lab: ${productItem.raw.laboratory?.name || productItem.raw.laboratory || 'S/L'} | Stock: ${productItem.raw.stock} | Precio: ${formatCurrency(productItem.raw.sale_price, 'USD')}`"
                        />
                      </template>
                    </AppAutocomplete>
                  </VCol>

                  <!-- Cantidad -->
                  <VCol cols="5" sm="3" md="2">
                    <span class="text-super-xs font-weight-black text-high-emphasis uppercase mb-1 d-block">Cantidad</span>
                    <VTextField
                      v-model.number="item.quantity"
                      type="number"
                      min="1"
                      variant="outlined"
                      density="compact"
                      hide-details="auto"
                      @update:model-value="calculateTotalPrice()"
                      class="rounded font-weight-black text-center"
                      :error="!!formErrors[`quantity_${index}`] || !!formErrors[`stock_${index}`]"
                      :error-messages="formErrors[`quantity_${index}`] || formErrors[`stock_${index}`]"
                      :disabled="isSaving"
                    />
                  </VCol>

                  <!-- Descuento % -->
                  <VCol cols="5" sm="3" md="2">
                    <span class="text-super-xs font-weight-black text-high-emphasis uppercase mb-1 d-block">% Desc.</span>
                    <VTextField
                      v-model.number="item.discount_percentage"
                      type="number"
                      min="0"
                      max="100"
                      step="0.01"
                      variant="outlined"
                      density="compact"
                      hide-details="auto"
                      prepend-inner-icon="tabler-percentage"
                      @update:model-value="calculateTotalPrice()"
                      class="rounded font-weight-black"
                      :disabled="isSaving"
                    />
                  </VCol>

                  <!-- Subtotal y Acción Eliminar -->
                  <VCol cols="2" sm="6" md="2" class="d-flex align-center justify-space-between ps-md-2">
                    <div class="d-flex flex-column text-end flex-grow-1 me-2">
                      <span class="text-super-xs text-disabled uppercase font-weight-bold">Subtotal</span>
                      <span class="text-sm font-weight-black text-success leading-tight">
                        {{ formatCurrency(calculateProductPrice(item), 'USD') }}
                      </span>
                    </div>

                    <IconBtn
                      v-if="formData.pack_products.length > 1"
                      color="error"
                      size="small"
                      @click="removeProductRow(index)"
                      :disabled="isSaving"
                    >
                      <VIcon icon="tabler-trash" size="18" />
                      <VTooltip activator="parent">Eliminar Producto</VTooltip>
                    </IconBtn>
                  </VCol>
                </VRow>
              </div>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Footer y Acciones -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <div class="d-flex flex-column flex-md-row align-center justify-space-between w-100 gap-3">
          <!-- Tarjeta / Bloque de Totales en una sola línea integrada -->
          <div class="d-flex align-center flex-nowrap gap-3 pa-2-5 px-3 rounded border bg-var-theme-background flex-grow-1 flex-md-grow-0">
            <div class="d-flex flex-column text-start">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Precio Base</span>
              <span class="text-xs font-weight-bold text-medium-emphasis text-decoration-line-through leading-tight">
                {{ formatCurrency(regularTotalPrice, 'USD') }}
              </span>
            </div>

            <VDivider vertical class="mx-1" style="height: 24px;" />

            <div v-if="totalSavings > 0" class="d-flex flex-column text-start">
              <span class="text-super-xs font-weight-black text-success uppercase">Ahorro</span>
              <span class="text-xs font-weight-black text-success leading-tight">
                -{{ formatCurrency(totalSavings, 'USD') }}
              </span>
            </div>

            <VDivider v-if="totalSavings > 0" vertical class="mx-1" style="height: 24px;" />

            <div class="d-flex flex-column text-start">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Total Pack</span>
              <span class="text-subtitle-1 font-weight-black text-primary leading-tight">
                {{ formatCurrency(formData.total_price, 'USD') }}
              </span>
            </div>
          </div>

          <!-- Botones de Acción -->
          <div class="d-flex gap-2 w-100 w-md-auto justify-end">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              class="font-weight-bold rounded text-button uppercase flex-grow-1 flex-md-grow-0 px-5"
              @click="closeModal"
              :disabled="isSaving"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              class="font-weight-black rounded shadow-primary text-button uppercase flex-grow-1 flex-md-grow-0 px-6"
              @click="savePack"
              :loading="isSaving || props.loading"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              {{ isEditing ? "Guardar Cambios" : "Crear Pack" }}
            </VBtn>
          </div>
        </div>
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
  border-radius: 5px !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }

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

.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }
.font-weight-950 { font-weight: 950 !important; }

.gap-1-5 { gap: 6px !important; }
.mt-1-5 { margin-top: 6px !important; }

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.bg-success-lighten-5 {
  background-color: rgba(var(--v-theme-success), 0.08) !important;
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
}

.pack-item-row {
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  border-radius: 5px !important;
}

.pack-item-row:hover {
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  background-color: rgba(var(--v-theme-primary), 0.015);
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
