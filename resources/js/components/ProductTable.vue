<script setup>
import { useAuthStore } from "@/stores/auth";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const authStore = useAuthStore();

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  mode: { type: String, default: "products" },
  title: { type: String, default: "" },
});

const emit = defineEmits([
  "update:options",
  "edit-product",
  "delete-product",
  "count-product",
  "add-product-to-invoice",
  "product-merged",
]);

const headers = ref([
  { title: "id", key: "id", sortable: true, visible: true },
  {
    title: "Producto",
    key: "name",
    sortable: true,
    width: "40%",
    visible: true,
  },
  {
    title: "Laboratorio",
    key: "laboratory.name",
    sortable: true,
    visible: true,
  },
  { title: "Exp.", key: "next_expiration", sortable: true, visible: true },
  {
    title: "STOCK",
    key: "stock_calculado",
    sortable: true,
    align: "end",
    visible: props.mode !== "inventory",
  },
  {
    title: "Costo",
    key: "unit_cost",
    sortable: true,
    visible: props.mode !== "inventory" && authStore.isAdmin,
  },
  {
    title: "Precio Venta",
    key: "sale_price",
    sortable: true,
    visible: props.mode !== "inventory" && authStore.isAdmin,
  },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    visible: true,
  },
]);

const visibleHeaders = computed(() =>
  headers.value.filter((header) => header.visible)
);

// if(authStore.isAdmin){
//   headers.push()
// }
// TODO: hay que modificar la funcion para que muestr la fecha de vencimiento apesar de que los lotes ya esten todos vencidos (puede que se tenga que modificar la consulta en el backend)
const nextExpirationDate = (product) => {
  if (
    !product.lots ||
    !Array.isArray(product.lots) ||
    product.lots.length === 0
  )
    return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  // if (validLots.length === 0) return "Todos expiraron";
  if (validLots.length === 0) return product.ultima_fecha_vencimiento;
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split("T")[0];
};

const calculateSalePriceWithIva = (product) => {
  const basePrice = Number(product.sale_price || 0);
  if (product.iva == 1) {
    const priceWithIva = basePrice * 1.16;

    return priceWithIva;
  }
  return basePrice;
};

const formatPrice = (price) => {
  if (typeof price !== "number") return "0.00";
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
};

// Estado del modal
const isModalVisible = ref(false);
const isProductModalVisible = ref(false);
const selectedProduct = ref(null);
const productToMerge = ref(null);
const inputId = ref("");
const loadingProduct = ref(false);
const laboratories = ref([]);
const origins = ref([]);
const categories = ref([]);
const mergeFormData = ref({});
const selectedProductToKeep = ref(null); // 'product1' o 'product2'
const isMerging = ref(false);
const openModal = (product) => {
  selectedProduct.value = product;
  inputId.value = "";
  isModalVisible.value = true;
};
const closeModal = () => {
  isModalVisible.value = false;
  selectedProduct.value = null;
  inputId.value = "";
};
const closeProductModal = () => {
  isProductModalVisible.value = false;
  productToMerge.value = null;
  selectedProduct.value = null;
  selectedProductToKeep.value = null;
  mergeFormData.value = {};
};
const fetchSelectOptions = async () => {
  try {
    const [labResponse, originResponse, categoryResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/categories"),
    ]);
    laboratories.value = labResponse.data || [];
    origins.value = originResponse.data || [];
    categories.value = categoryResponse.data || [];
  } catch (error) {
    console.error("Error al cargar opciones:", error);
  }
};
const handleSubmit = async () => {
  if (!inputId.value) {
    toast.warning("Por favor ingrese un ID");
    return;
  }
  loadingProduct.value = true;
  try {
    // Buscar producto por ID (id de la tabla products en la base de datos)
    const response = await axios.get("/products", {
      params: {
        q: inputId.value,
        itemsPerPage: 100,
        isStrictSearch: false,
      },
    });
    const products = response.data.data || [];
    // Buscar por id de la base de datos
    const foundProduct = products.find((p) => p.id == inputId.value || p.id == Number(inputId.value));
    if (!foundProduct) {
      toast.error("Producto no encontrado");
      return;
    }
    // Cargar opciones si no están cargadas
    if (laboratories.value.length === 0) {
      await fetchSelectOptions();
    }
    productToMerge.value = foundProduct;
    
    // Inicializar el formulario con el producto seleccionado por defecto
    selectedProductToKeep.value = 'product1';
    
    // Iniciar con los datos del producto que se mantiene
    mergeFormData.value = JSON.parse(JSON.stringify(selectedProduct.value));
    
    // Unificar campos automáticamente
    unifyFields();
    
    // Normalizar valores booleanos
    mergeFormData.value.iva = mergeFormData.value.iva ? 1 : 0;
    mergeFormData.value.psychotropic = mergeFormData.value.psychotropic ? 1 : 0;
    mergeFormData.value.is_colombian_origin = mergeFormData.value.is_colombian_origin ? 1 : 0;
    
    isModalVisible.value = false;
    isProductModalVisible.value = true;
  } catch (error) {
    console.error("Error al buscar producto:", error);
    toast.error("Error al buscar el producto");
  } finally {
    loadingProduct.value = false;
  }
};
const unifyFields = () => {
  const productToKeep = selectedProductToKeep.value === 'product1' 
    ? selectedProduct.value 
    : productToMerge.value;
  const productToDelete = selectedProductToKeep.value === 'product1' 
    ? productToMerge.value 
    : selectedProduct.value;
  
  // Función auxiliar para verificar si un valor está vacío o es "N/A"
  const isEmpty = (value) => {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') {
      const trimmed = value.trim();
      return trimmed === '' || trimmed.toUpperCase() === 'N/A' || trimmed === 'null';
    }
    if (typeof value === 'number') {
      return value === 0;
    }
    return false;
  };
  
  // Unificar campos: si el producto que se mantiene no tiene el dato o está vacío/null
  // y el producto que se elimina sí lo tiene, copiarlo
  const fieldsToUnify = [
    'name', 'active_ingredient', 'laboratory_id', 'origin_id', 'category_id',
    'barcode', 'unit_cost', 'iva', 'psychotropic', 'is_colombian_origin', 'group_id'
  ];
  
  fieldsToUnify.forEach(field => {
    const keepValue = mergeFormData.value[field];
    const deleteValue = productToDelete[field];
    
    // Si el producto que se mantiene no tiene el dato o está vacío/null/N/A
    // y el producto que se elimina sí lo tiene, copiarlo
    if (isEmpty(keepValue) && !isEmpty(deleteValue)) {
      mergeFormData.value[field] = deleteValue;
    }
  });
};

const switchProductToKeep = () => {
  const productToKeep = selectedProductToKeep.value === 'product1' 
    ? selectedProduct.value 
    : productToMerge.value;
  
  // Iniciar con los datos del producto que se mantiene
  mergeFormData.value = JSON.parse(JSON.stringify(productToKeep));
  
  // Unificar campos automáticamente
  unifyFields();
  
  // Normalizar valores booleanos
  mergeFormData.value.iva = mergeFormData.value.iva ? 1 : 0;
  mergeFormData.value.psychotropic = mergeFormData.value.psychotropic ? 1 : 0;
  mergeFormData.value.is_colombian_origin = mergeFormData.value.is_colombian_origin ? 1 : 0;
};

const handleMerge = async () => {
  if (!selectedProduct.value || !productToMerge.value) {
    toast.error("Error: No se puede fusionar. Faltan datos de productos.");
    return;
  }
  if (!selectedProduct.value.id || !productToMerge.value.id) {
    toast.error("Error: Los IDs de los productos no son válidos.");
    return;
  }
  if (selectedProduct.value.id === productToMerge.value.id) {
    toast.error("Error: No se puede fusionar un producto consigo mismo.");
    return;
  }
  if (!selectedProductToKeep.value) {
    toast.error("Error: Debe seleccionar qué producto se mantiene.");
    return;
  }
  
  isMerging.value = true;
  try {
    // Determinar qué producto se mantiene y cuál se elimina
    const productToKeepId = selectedProductToKeep.value === 'product1' 
      ? selectedProduct.value.id 
      : productToMerge.value.id;
    const productToDeleteId = selectedProductToKeep.value === 'product1' 
      ? productToMerge.value.id 
      : selectedProduct.value.id;
    
    // Actualizar el producto que se mantiene con los datos del formulario
    const updatePayload = new FormData();
    Object.keys(mergeFormData.value).forEach((key) => {
      const value = mergeFormData.value[key];
      if (
        value !== null &&
        value !== undefined &&
        !Array.isArray(value) &&
        typeof value !== "object"
      ) {
        updatePayload.append(key, value);
      }
    });
    updatePayload.append("_method", "PUT");
    
    // Actualizar el producto que se mantiene
    await axios.post(`/products/${productToKeepId}`, updatePayload, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
    
    // Fusionar los productos - el backend actualizará todas las referencias del producto eliminado
    // al producto que se mantiene, independientemente de cuál tenga el ID mayor o menor
    const response = await axios.post("/products/merge", {
      product_id_1: selectedProduct.value.id,
      product_id_2: productToMerge.value.id,
      keep_product_id: productToKeepId,
    });
    
    if (response.data.success) {
      toast.success(response.data.message || "Productos fusionados exitosamente");
      closeProductModal();
      // Emitir evento para refrescar la lista de productos
      emit("product-merged");
    } else {
      toast.error(response.data.message || "Error al fusionar productos");
    }
  } catch (error) {
    console.error("Error al fusionar productos:", error);
    let errorMessage = "Error al fusionar productos";
    
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.response?.data?.errors) {
      const firstError = Object.values(error.response.data.errors)[0];
      errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
    }
    
    toast.error(errorMessage);
  } finally {
    isMerging.value = false;
  }
};
const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};
const calculateStock = (product) => {
  if (!product.lots || !Array.isArray(product.lots)) return 0;
  return product.lots.reduce((sum, lot) => sum + Number(lot.quantity || 0), 0);
};
const lotHeaders = [
  { title: "Nombre", key: "lot_number", sortable: false },
  { title: "Ubicación", key: "location", sortable: false },
  { title: "Stock", key: "quantity", sortable: false },
  { title: "Exp.", key: "expiration_date", sortable: false },
];

</script>

<template>
  <VCard>
    <VCardTitle v-if="props.title">{{ props.title }}</VCardTitle>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="visibleHeaders"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 
                'text-warning font-weight-bold': item.psychotropic == 1 || item.psychotropic === true
              }"
            >
              {{ item.name.toUpperCase() }}
              <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
              <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
            </span>
            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.stock_calculado="{ item }">
        <div class="text-end">
          <span class="font-weight-medium">{{ item.stock_calculado ?? 0 }}</span>
        </div>
      </template>

      <template #item.next_expiration="{ item }">
        <span>{{ nextExpirationDate(item) }}</span>
      </template>

      <template #item.unit_cost="{ item }">
        <span class="font-weight-medium">{{ item.unit_cost }}</span>
      </template>

      <template #item.sale_price="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatPrice(calculateSalePriceWithIva(item))
          }}</span>
          <span v-if="item.iva == 1" class="text-xs text-success"
            >(IVA incluido)</span
          >
        </div>
      </template>

      <template #item.actions="{ item }">
        <template v-if="mode === 'products'">
          <IconBtn @click="emit('edit-product', item)" color="warning">
            <VIcon icon="tabler-edit" />
          </IconBtn>
            <IconBtn
            v-if="authStore.isAdmin"
            color="info"
            @click="openModal(item)"
          >
            <VIcon icon="tabler-package" />
          </IconBtn>
          <IconBtn
            @click="emit('delete-product', item.id)"
            v-if="authStore.isAdmin"
            color="error"
          >
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>

        <template v-else-if="mode === 'inventory'">
          <div class="d-flex justify-center">
            <IconBtn 
              @click="emit('count-product', item)" 
              color="purple"
            >
              <VIcon icon="tabler-scan" />
              <VTooltip activator="parent" location="top"
                >Contar producto</VTooltip
              >
            </IconBtn>
          </div>
        </template>

        <template v-else-if="mode === 'add-to-invoice'">
          <VBtn
            icon
            variant="tonal"
            color="success"
            size="small"
            @click="emit('add-product-to-invoice', item)"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top"
              >Añadir a la factura</VTooltip
            >
          </VBtn>
        </template>
      </template>
    </VDataTableServer>
  </VCard>
    <!-- Modal para ingresar ID -->
  <VDialog
    :model-value="isModalVisible"
    max-width="500px"
    @update:model-value="(val) => !val && closeModal()"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <span class="text-h6">Ingresar ID de Producto</span>
        <VBtn icon variant="text" size="small" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-4">
        <VTextField
          v-model="inputId"
          label="ID"
          variant="outlined"
          type="number"
          autofocus
          :loading="loadingProduct"
          @keyup.enter="handleSubmit"
        />
      </VCardText>
      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn 
          color="secondary" 
          variant="outlined" 
          @click="closeModal"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%;"
        >
          Cancelar
        </VBtn>
        <VBtn 
          color="primary" 
          variant="flat" 
          @click="handleSubmit" 
          :loading="loadingProduct"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%;"
        >
          Buscar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <!-- Modal para fusionar productos -->
  <VDialog
    :model-value="isProductModalVisible"
    max-width="1400px"
    persistent
    @update:model-value="(val) => !val && closeProductModal()"
    :scrollable="true"
  >
    <VCard v-if="selectedProduct && productToMerge" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon icon="tabler-package" size="24" color="white" class="me-2" />
        <span class="text-h5 font-weight-bold text-white">Fusionar Productos</span>
        <VSpacer />
        <VBtn icon variant="text" color="white" size="small" @click="closeProductModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto">
        <div class="mb-4">
          <VRow class="mb-4">
            <VCol cols="12" md="6">
              <VCard 
                variant="outlined"
                :class="selectedProductToKeep === 'product1' ? 'border-primary border-2' : ''"
                class="cursor-pointer transition-all"
                @click="selectedProductToKeep = 'product1'; switchProductToKeep()"
              >
                <VCardTitle class="d-flex align-center">
                  <div class="d-flex align-center flex-grow-1">
                    <VRadio 
                      :model-value="selectedProductToKeep === 'product1'"
                      value="product1"
                      @click.stop="selectedProductToKeep = 'product1'; switchProductToKeep()"
                      :label="`Producto 1 (ID: ${selectedProduct.id})`"
                      color="primary"
                      class="flex-grow-1"
                    />
                  </div>
                  <VChip 
                    v-if="selectedProductToKeep === 'product1'" 
                    color="success" 
                    size="small" 
                    class="ms-2"
                    variant="flat"
                  >
                    SE MANTIENE
                  </VChip>
                  <VChip 
                    v-else
                    color="error" 
                    size="small" 
                    class="ms-2"
                    variant="flat"
                  >
                    ELIMINAR
                  </VChip>
                </VCardTitle>
                <VCardText>
                  <VTextField
                    :model-value="selectedProduct.name"
                    label="Nombre"
                    variant="outlined"
                    density="compact"
                    readonly
                  />
                  <VTextField
                    :model-value="selectedProduct.active_ingredient"
                    label="Principio Activo"
                    variant="outlined"
                    density="compact"
                    readonly
                    class="mt-2"
                  />
                  <VTextField
                    :model-value="laboratories.find(l => l.id === selectedProduct.laboratory_id)?.name || 'N/A'"
                    label="Laboratorio"
                    variant="outlined"
                    density="compact"
                    readonly
                    class="mt-2"
                  />
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard 
                variant="outlined"
                :class="selectedProductToKeep === 'product2' ? 'border-primary border-2' : ''"
                class="cursor-pointer transition-all"
                @click="selectedProductToKeep = 'product2'; switchProductToKeep()"
              >
                <VCardTitle class="d-flex align-center">
                  <div class="d-flex align-center flex-grow-1">
                    <VRadio 
                      :model-value="selectedProductToKeep === 'product2'"
                      value="product2"
                      @click.stop="selectedProductToKeep = 'product2'; switchProductToKeep()"
                      :label="`Producto 2 (ID: ${productToMerge.id})`"
                      color="primary"
                      class="flex-grow-1"
                    />
                  </div>
                  <VChip 
                    v-if="selectedProductToKeep === 'product2'" 
                    color="success" 
                    size="small" 
                    class="ms-2"
                    variant="flat"
                  >
                    SE MANTIENE
                  </VChip>
                  <VChip 
                    v-else
                    color="error" 
                    size="small" 
                    class="ms-2"
                    variant="flat"
                  >
                    ELIMINAR
                  </VChip>
                </VCardTitle>
                <VCardText>
                  <VTextField
                    :model-value="productToMerge.name"
                    label="Nombre"
                    variant="outlined"
                    density="compact"
                    readonly
                  />
                  <VTextField
                    :model-value="productToMerge.active_ingredient"
                    label="Principio Activo"
                    variant="outlined"
                    density="compact"
                    readonly
                    class="mt-2"
                  />
                  <VTextField
                    :model-value="laboratories.find(l => l.id === productToMerge.laboratory_id)?.name || 'N/A'"
                    label="Laboratorio"
                    variant="outlined"
                    density="compact"
                    readonly
                    class="mt-2"
                  />
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <VDivider class="my-4" />
        
        <div class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-edit" class="me-2" color="primary" />
            <p class="text-h6 font-weight-medium mb-0">Editar Producto que se Mantiene</p>
          </div>
          <VRow dense>
            <VCol cols="12" md="6">
              <VTextField
                v-model="mergeFormData.name"
                label="Nombre"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="mergeFormData.active_ingredient"
                label="Principio Activo"
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>
          <VRow dense>
            <VCol cols="12" md="4">
              <VSelect
                v-model="mergeFormData.laboratory_id"
                label="Laboratorio"
                :items="laboratories"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                clearable
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="mergeFormData.origin_id"
                label="Origen"
                :items="origins"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                clearable
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="mergeFormData.category_id"
                label="Categoría"
                :items="categories"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                clearable
              />
            </VCol>
          </VRow>
          <VRow dense>
            <VCol cols="12" md="4">
              <VTextField
                v-model="mergeFormData.barcode"
                label="Código de Barra"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model="mergeFormData.unit_cost"
                label="Costo de Compra"
                type="number"
                prefix="$"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" md="4" class="d-flex align-center gap-2">
              <VCheckbox
                v-model="mergeFormData.iva"
                label="IVA"
                :true-value="1"
                :false-value="0"
                density="compact"
                hide-details
              />
              <VCheckbox
                v-model="mergeFormData.psychotropic"
                label="Psicotrópico"
                :true-value="1"
                :false-value="0"
                density="compact"
                hide-details
              />
              <VCheckbox
                v-model="mergeFormData.is_colombian_origin"
                label="Colombia"
                :true-value="1"
                :false-value="0"
                density="compact"
                hide-details
              />
            </VCol>
          </VRow>
        </div>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeProductModal"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%;"
          :disabled="isMerging"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleMerge"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%;"
          :loading="isMerging"
        >
          Fusionar Productos
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
