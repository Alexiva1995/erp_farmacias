<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
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

// No eliminar, se mantiene para compatibilidad con la API
// products_count se actualiza automáticamente al agregar/quitar filas

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
    :max-inline-size="mobile ? '100%' : '1000px'"
    :fullscreen="mobile"
    persistent
    scrollable
    :retain-focus="false"
    transition="dialog-bottom-transition"
    @click:outside.prevent
    @keydown.esc.prevent="closeModal"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium con Degradado -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center justify-space-between text-white">
          <div class="d-flex align-center gap-3">
            <VAvatar size="42" color="rgba(255,255,255,0.2)" class="backdrop-blur">
              <VIcon icon="tabler-package" size="24" color="white" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-h6 font-weight-black leading-tight">
                {{ isEditing ? "EDITAR PACK" : "NUEVO PACK" }}
              </span>
              <span class="text-super-xs font-weight-medium opacity-90 uppercase letter-spacing-1">
                Configuración de Oferta
              </span>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeModal"
            :disabled="loading"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light">
        <!-- Información del Pack -->
        <div class="pa-5 pb-2">
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <div class="d-flex flex-column gap-1">
                <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 ms-1">Nombre del Pack</span>
                <AppTextField
                  v-model="formData.name"
                  variant="outlined"
                  density="compact"
                  :error-messages="formErrors.name"
                  placeholder="Ej: Pack Especial Invierno..."
                  :disabled="loading"
                  class="premium-input shadow-sm"
                />
              </div>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <div class="d-flex flex-column gap-1">
                <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 ms-1">Estado</span>
                <AppSelect
                  v-model="formData.is_active"
                  density="compact"
                  variant="outlined"
                  :items="[
                    { title: 'Activo', value: true },
                    { title: 'Inactivo', value: false },
                  ]"
                  item-title="title"
                  item-value="value"
                  :disabled="loading"
                  class="premium-input shadow-sm"
                />
              </div>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <div class="d-flex flex-column gap-1">
                <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 ms-1">Máx. Ventas</span>
                <AppTextField
                  v-model.number="formData.max_quantity"
                  variant="outlined"
                  density="compact"
                  type="number"
                  min="1"
                  placeholder="Ilimitado"
                  :disabled="loading"
                  class="premium-input shadow-sm"
                />
              </div>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <div class="d-flex flex-column gap-1">
                <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 ms-1">Fecha Máx</span>
                <AppTextField
                  v-model="formData.max_sale_date"
                  variant="outlined"
                  type="date"
                  density="compact"
                  hide-details
                  :disabled="loading"
                  class="premium-input shadow-sm date-compact"
                />
              </div>
            </VCol>
          </VRow>
        </div>

        <VDivider class="border-dashed my-2" />

        <!-- Productos del Pack -->
        <div class="pa-5 pt-2">
          <div class="d-flex align-center justify-space-between mb-4">
            <h3 class="text-subtitle-1 font-weight-950 text-high-emphasis flex-grow-1">PRODUCTOS INCLUIDOS</h3>
            <div class="d-flex gap-2">
              <VBtn
                variant="tonal"
                color="primary"
                prepend-icon="tabler-plus"
                size="small"
                class="rounded-lg font-weight-black"
                :disabled="loading || formData.pack_products.length >= 10"
                @click="addProductRow"
              >
                Añadir Producto
              </VBtn>
              <VChip
                variant="tonal"
                color="primary"
                size="small"
                class="font-weight-black px-4"
              >
                {{ formData.pack_products.length }} ITEMS
              </VChip>
            </div>
          </div>

          <!-- Tabla en Desktop -->
          <VDataTable
            v-if="!mobile"
            :headers="[
              { title: 'PRODUCTO', key: 'product', sortable: false, width: '35%' },
              { title: 'CANT.', key: 'quantity', sortable: false, width: '12%', align: 'center' },
              { title: 'DESC. %', key: 'discount', sortable: false, width: '12%', align: 'center' },
              { title: 'UNITARIO', key: 'unit_price', sortable: false, width: '15%', align: 'end' },
              { title: 'SUBTOTAL', key: 'subtotal', sortable: false, width: '18%', align: 'end' },
              { title: '', key: 'actions', sortable: false, width: '8%', align: 'center' },
            ]"
            :items="formData.pack_products"
            density="comfortable"
            class="rounded-lg border-0 bg-transparent internal-table"
            no-data-text="No hay productos agregados"
            hide-default-footer
          >
            <template #item.product="{ item, index }">
              <VAutocomplete
                v-model="item.product"
                :items="availableProducts"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                hide-details
                :loading="loadingProducts"
                :error-messages="formErrors[`product_${index}`]"
                return-object
                clearable
                :disabled="loading"
                placeholder="Buscar..."
                class="compact-autocomplete"
                no-filter
                @update:search="handleProductSearch"
                @update:model-value="calculateTotalPrice()"
              >
                <template #item="{ props: itemProps, item: productItem }">
                  <VListItem
                    v-bind="{ ...itemProps, title: '' }"
                    density="compact"
                  >
                    <template
                      v-if="productItem.raw.photo_url"
                      #prepend
                    >
                      <VAvatar
                        size="32"
                        :image="productItem.raw.photo_url"
                        variant="tonal"
                        class="me-2"
                      />
                    </template>
                    <VListItemTitle class="text-caption font-weight-bold">
                      {{ productItem.raw.name }}
                    </VListItemTitle>
                    <VListItemSubtitle class="text-super-xs">
                      ID: {{ productItem.raw.id }} | Stock: {{ productItem.raw.stock }} | ${{ productItem.raw.sale_price }}
                    </VListItemSubtitle>
                  </VListItem>
                </template>
              </VAutocomplete>
            </template>

            <template #item.quantity="{ item, index }">
              <AppTextField
                v-model.number="item.quantity"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                min="1"
                :max="item.product?.stock || 9999"
                :error-messages="formErrors[`quantity_${index}`]"
                :disabled="loading || !item.product"
                class="mx-auto compact-input-field text-center"
                @update:model-value="calculateTotalPrice()"
              />
            </template>

            <template #item.discount="{ item, index }">
              <AppTextField
                v-model.number="item.discount_percentage"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                min="0"
                max="100"
                suffix="%"
                :disabled="loading || !item.product"
                class="mx-auto compact-input-field text-center"
                @update:model-value="calculateTotalPrice()"
              />
            </template>

            <template #item.unit_price="{ item }">
              <span
                v-if="item.product"
                class="text-caption font-weight-bold"
              >
                ${{ (item.product.sale_price * (1 - (item.discount_percentage || 0) / 100)).toFixed(2) }}
              </span>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>

            <template #item.subtotal="{ item }">
              <span
                v-if="item.product"
                class="text-caption font-weight-black text-success"
              >
                ${{ calculateProductPrice(item).toFixed(2) }}
              </span>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </template>

            <template #item.actions="{ index }">
              <VBtn
                icon="tabler-trash"
                variant="tonal"
                color="error"
                size="x-small"
                class="rounded-lg"
                @click="formData.pack_products.splice(index, 1); calculateTotalPrice()"
              />
            </template>
          </VDataTable>

          <!-- Lista de Tarjetas en Móvil -->
          <div
            v-else
            class="mobile-products-list"
          >
            <div
              v-if="formData.pack_products.length === 0"
              class="text-center pa-8 rounded-lg border-dashed border-2 text-disabled mb-4"
            >
              <VIcon
                icon="tabler-package-off"
                size="48"
                class="mb-2 opacity-25"
              />
              <p>No hay productos agregados</p>
            </div>

            <div
              v-else
              class="d-flex flex-column gap-4"
            >
              <VCard
                v-for="(item, index) in formData.pack_products"
                :key="index"
                variant="flat"
                class="product-card-item rounded-lg border shadow-xs"
              >
                <VCardText class="pa-4">
                  <!-- Cabecera de Tarjeta: # y Eliminar -->
                  <div class="d-flex align-center justify-space-between mb-3">
                    <div class="d-flex align-center gap-2">
                      <VAvatar
                        size="24"
                        color="primary"
                        variant="tonal"
                        class="text-super-xs font-weight-black"
                      >
                        {{ index + 1 }}
                      </VAvatar>
                      <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1">Producto</span>
                    </div>
                    <VBtn
                      icon="tabler-trash"
                      variant="tonal"
                      color="error"
                      size="x-small"
                      density="comfortable"
                      class="rounded-lg"
                      @click="formData.pack_products.splice(index, 1); calculateTotalPrice()"
                    />
                  </div>

                  <!-- Selector de Producto -->
                  <div class="mb-3">
                    <VAutocomplete
                      v-model="item.product"
                      :items="availableProducts"
                      item-title="name"
                      item-value="id"
                      variant="outlined"
                      density="compact"
                      hide-details
                      :loading="loadingProducts"
                      :error-messages="formErrors[`product_${index}`]"
                      return-object
                      clearable
                      :disabled="loading"
                      placeholder="Seleccionar producto..."
                      class="compact-autocomplete"
                      no-filter
                      @update:search="handleProductSearch"
                      @update:model-value="calculateTotalPrice()"
                    >
                      <template #item="{ props: itemProps, item: productItem }">
                        <VListItem
                          v-bind="{ ...itemProps, title: '' }"
                          density="compact"
                        >
                          <template
                            v-if="productItem.raw.photo_url"
                            #prepend
                          >
                            <VAvatar
                              size="32"
                              :image="productItem.raw.photo_url"
                              variant="tonal"
                              class="me-2"
                            />
                          </template>
                          <VListItemTitle class="text-caption font-weight-bold">
                            {{ productItem.raw.name }}
                          </VListItemTitle>
                          <VListItemSubtitle class="text-super-xs">
                            ID: {{ productItem.raw.id }} | Stock: {{ productItem.raw.stock }} | ${{ productItem.raw.sale_price }}
                          </VListItemSubtitle>
                        </VListItem>
                      </template>
                    </VAutocomplete>
                  </div>

                  <!-- Grid de Inputs para Móvil -->
                  <VRow dense>
                    <VCol cols="6">
                      <div class="d-flex flex-column gap-1">
                        <span class="text-super-xs font-weight-bold text-medium-emphasis ms-1 uppercase">Cantidad</span>
                        <AppTextField
                          v-model.number="item.quantity"
                          type="number"
                          variant="outlined"
                          density="compact"
                          hide-details
                          min="1"
                          :max="item.product?.stock || 9999"
                          :error-messages="formErrors[`quantity_${index}`]"
                          :disabled="loading || !item.product"
                          class="premium-input-small"
                          @update:model-value="calculateTotalPrice()"
                        />
                      </div>
                    </VCol>
                    <VCol cols="6">
                      <div class="d-flex flex-column gap-1">
                        <span class="text-super-xs font-weight-bold text-medium-emphasis ms-1 uppercase">Desc. %</span>
                        <AppTextField
                          v-model.number="item.discount_percentage"
                          type="number"
                          variant="outlined"
                          density="compact"
                          hide-details
                          min="0"
                          max="100"
                          suffix="%"
                          :disabled="loading || !item.product"
                          class="premium-input-small"
                          @update:model-value="calculateTotalPrice()"
                        />
                      </div>
                    </VCol>
                  </VRow>

                  <VDivider class="my-3 border-dashed" />

                  <!-- Resumen Financiero de la Tarjeta -->
                  <div class="d-flex justify-space-between align-center">
                    <div class="d-flex flex-column">
                      <span class="text-super-xs font-weight-black text-low-emphasis uppercase">Unitario</span>
                      <span
                        v-if="item.product"
                        class="text-caption font-weight-bold text-high-emphasis"
                      >
                        ${{ (item.product.sale_price * (1 - (item.discount_percentage || 0) / 100)).toFixed(2) }}
                      </span>
                      <span
                        v-else
                        class="text-disabled"
                      >—</span>
                    </div>
                    <div class="d-flex flex-column align-end">
                      <span class="text-super-xs font-weight-black text-primary uppercase">Subtotal</span>
                      <span
                        v-if="item.product"
                        class="text-subtitle-2 font-weight-950 text-success"
                      >
                        ${{ calculateProductPrice(item).toFixed(2) }}
                      </span>
                      <span
                        v-else
                        class="text-disabled"
                      >—</span>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider class="border-dashed" />

      <VCardActions class="pa-5 bg-white overflow-visible">
        <div class="d-flex align-center flex-grow-1">
          <div v-if="formData.pack_products.some((p) => p.product)" class="d-flex flex-column">
            <span class="text-super-xs text-medium-emphasis uppercase font-weight-bold leading-none mb-1">Total a Pagar</span>
            <span class="text-h4 font-weight-950 text-primary leading-none">
              ${{ formData.total_price.toFixed(2) }}
            </span>
          </div>
          <VSpacer />
          <div class="d-flex gap-3">
            <VBtn
              color="secondary"
              variant="tonal"
              class="rounded-lg font-weight-bold px-6"
              @click="closeModal"
              :disabled="loading"
            >
              CANCELAR
            </VBtn>
            <VBtn
              color="primary"
              variant="flat"
              class="rounded-lg font-weight-black shadow-primary-lg px-8"
              @click="savePack"
              :loading="loading"
            >
              <VIcon start>tabler-check</VIcon>
              {{ isEditing ? "ACTUALIZAR" : "GUARDAR PACK" }}
            </VBtn>
          </div>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #2f3349 100%);
}

.backdrop-blur {
  backdrop-filter: blur(8px);
}

.bg-light {
  background-color: #f8fafc !important;
}

.border-dashed {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.premium-input :deep(.v-field__outline__start),
.premium-input :deep(.v-field__outline__end),
.premium-input :deep(.v-field__outline__notch) {
  border-color: rgba(var(--v-border-color), 0.5) !important;
}

.premium-input :deep(.v-field--focused .v-field__outline__start),
.premium-input :deep(.v-field--focused .v-field__outline__end),
.premium-input :deep(.v-field--focused .v-field__outline__notch) {
  border-width: 2px !important;
  border-color: rgb(var(--v-theme-primary)) !important;
}

.internal-table :deep(th) {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 0.65rem !important;
  font-weight: 950 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase !important;
}

.internal-table :deep(td) {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  padding-block: 8px !important;
}

.compact-autocomplete :deep(.v-field__input) {
  font-size: 0.75rem !important;
  padding-inline: 4px !important;
}

.compact-input-field :deep(input) {
  padding: 4px !important;
  font-size: 0.75rem !important;
  text-align: center !important;
}

.date-compact :deep(input) {
  font-size: 0.75rem !important;
}

.text-super-xs {
  font-size: 0.68rem !important;
  line-height: normal;
}

.font-weight-950 { font-weight: 950 !important; }
.leading-tight { line-height: 1.25 !important; }
.leading-none { line-height: 1 !important; }
.letter-spacing-1 { letter-spacing: 1.5px !important; }

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 5%) !important;
}

.shadow-primary-lg {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 25%) !important;
}

@media (max-width: 600px) {
  .v-btn {
    padding-inline: 12px !important;
  }
}
</style>
