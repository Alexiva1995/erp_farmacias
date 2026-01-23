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
    isModalVisible.value = false;
    isProductModalVisible.value = true;
  } catch (error) {
    console.error("Error al buscar producto:", error);
    toast.error("Error al buscar el producto");
  } finally {
    loadingProduct.value = false;
  }
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

  try {
    const response = await axios.post("/products/merge", {
      product_id_1: selectedProduct.value.id,
      product_id_2: productToMerge.value.id,
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
    console.error("IDs enviados:", {
      product_id_1: selectedProduct.value?.id,
      product_id_2: productToMerge.value?.id
    });
    
    let errorMessage = "Error al fusionar productos";
    
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.response?.data?.errors) {
      // Si hay errores de validación, mostrar el primero
      const firstError = Object.values(error.response.data.errors)[0];
      errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
    }
    
    // Agregar información de los IDs en el mensaje de error
    const idsInfo = ` (IDs: ${selectedProduct.value?.id} y ${productToMerge.value?.id})`;
    errorMessage += idsInfo;
    
    toast.error(errorMessage);
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
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="closeModal">
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="flat" @click="handleSubmit" :loading="loadingProduct">
          Buscar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Modal para mostrar producto encontrado -->
  <VDialog
    :model-value="isProductModalVisible"
    max-width="1000px"
    persistent
    @update:model-value="(val) => !val && closeProductModal()"
    :scrollable="true"
  >
    <VCard v-if="productToMerge" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3">
        <span class="text-h5 font-weight-bold">Información del Producto</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeProductModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto">
        <VRow dense class="mb-2">
          <VCol cols="12" md="6">
            <VTextField
              :model-value="productToMerge.name"
              label="Nombre"
              variant="outlined"
              density="compact"
              readonly
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              :model-value="productToMerge.active_ingredient"
              label="Principio Activo"
              variant="outlined"
              density="compact"
              readonly
            />
          </VCol>
        </VRow>
        <VRow dense class="mb-2">
          <VCol cols="12" md="4">
            <VTextField
              :model-value="laboratories.find(l => l.id === productToMerge.laboratory_id)?.name || 'N/A'"
              label="Laboratorio"
              variant="outlined"
              density="compact"
              readonly
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              :model-value="origins.find(o => o.id === productToMerge.origin_id)?.name || 'N/A'"
              label="Origen"
              variant="outlined"
              density="compact"
              readonly
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              :model-value="categories.find(c => c.id === productToMerge.category_id)?.name || 'N/A'"
              label="Categoría"
              variant="outlined"
              density="compact"
              readonly
            />
          </VCol>
        </VRow>
        <VRow dense class="mb-2">
          <VCol cols="12" md="4">
            <VTextField
              :model-value="productToMerge.barcode || 'N/A'"
              label="Código de Barra"
              variant="outlined"
              density="compact"
              readonly
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField
              :model-value="productToMerge.unit_cost || 0"
              label="Costo de Compra"
              type="number"
              prefix="$"
              variant="outlined"
              density="compact"
              readonly
            />
          </VCol>
          <VCol cols="12" md="4" class="d-flex align-center gap-2">
            <VCheckbox
              :model-value="productToMerge.iva == 1 || productToMerge.iva === true"
              label="IVA"
              readonly
              density="compact"
              hide-details
            />
            <VCheckbox
              :model-value="productToMerge.psychotropic == 1 || productToMerge.psychotropic === true"
              label="Psicotrópico"
              readonly
              density="compact"
              hide-details
            />
            <VCheckbox
              :model-value="productToMerge.is_colombian_origin == 1 || productToMerge.is_colombian_origin === true"
              label="Colombia"
              readonly
              density="compact"
              hide-details
            />
          </VCol>
        </VRow>
        <VRow dense class="mb-3">
          <VCol
            v-if="productToMerge.photo_url"
            cols="12"
            md="4"
            class="d-flex align-center justify-center"
          >
            <VImg
              :src="productToMerge.photo_url"
              :width="120"
              aspect-ratio="1"
              class="border rounded"
            />
          </VCol>
        </VRow>

        <template v-if="productToMerge.lots && productToMerge.lots.length > 0">
          <VDivider class="my-3" />
          <div class="mb-2">
            <p class="text-h6 font-weight-medium mb-1">Lotes del Producto</p>
          </div>
          <VDataTable
            :headers="lotHeaders"
            :items="productToMerge.lots || []"
            density="compact"
            class="rounded-lg"
            no-data-text="Este producto no tiene lotes registrados."
          >
            <template #item.lot_number="{ item }">
              <span>{{ item.lot_number || "N/A" }}</span>
            </template>
            <template #item.location="{ item }">
              <span>{{ item.location || "N/A" }}</span>
            </template>
            <template #item.quantity="{ item }">
              <span>{{ Number(item.quantity) || 0 }}</span>
            </template>
            <template #item.expiration_date="{ item }">
              <span>{{ formatDate(item.expiration_date) }}</span>
            </template>
          </VDataTable>
        </template>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-3">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeProductModal"
          class="flex-grow-1 mr-2"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleMerge"
          class="flex-grow-1"
        >
          Fusionar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
