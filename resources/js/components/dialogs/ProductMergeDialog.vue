<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedProduct: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "merged"]);

const isModalVisible = ref(false);
const isProductModalVisible = ref(false);
const productToMerge = ref(null);
const inputId = ref("");
const loadingProduct = ref(false);
const isMerging = ref(false);

const laboratories = ref([]);
const origins = ref([]);
const categories = ref([]);
const mergeFormData = ref({});
const selectedProductToKeep = ref("product1");

watch(
  () => props.modelValue,
  (val) => {
    isModalVisible.value = val;
    if (val) {
      inputId.value = "";
    }
  }
);

watch(isModalVisible, (val) => {
  emit("update:modelValue", val);
});

const closeModal = () => {
  isModalVisible.value = false;
  inputId.value = "";
};

const closeProductModal = () => {
  isProductModalVisible.value = false;
  productToMerge.value = null;
  selectedProductToKeep.value = "product1";
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
    const response = await axios.get("/products", {
      params: {
        q: inputId.value,
        itemsPerPage: 100,
        isStrictSearch: false,
      },
    });
    const products = response.data.data || [];
    const foundProduct = products.find(
      (p) => p.id == inputId.value || p.id == Number(inputId.value)
    );
    if (!foundProduct) {
      toast.error("Producto no encontrado");
      return;
    }

    if (laboratories.value.length === 0) {
      await fetchSelectOptions();
    }

    productToMerge.value = foundProduct;
    selectedProductToKeep.value = "product1";
    mergeFormData.value = JSON.parse(JSON.stringify(props.selectedProduct));

    unifyFields();

    // Normalizar booleanos
    mergeFormData.value.iva = mergeFormData.value.iva ? 1 : 0;
    mergeFormData.value.psychotropic = mergeFormData.value.psychotropic ? 1 : 0;
    mergeFormData.value.is_colombian_origin = mergeFormData.value.is_colombian_origin
      ? 1
      : 0;

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
  const productToDelete =
    selectedProductToKeep.value === "product1"
      ? productToMerge.value
      : props.selectedProduct;

  const isEmpty = (value) => {
    if (value === null || value === undefined) return true;
    if (typeof value === "string") {
      const trimmed = value.trim();
      return (
        trimmed === "" ||
        trimmed.toUpperCase() === "N/A" ||
        trimmed === "null"
      );
    }
    if (typeof value === "number") return value === 0;
    return false;
  };

  const fieldsToUnify = [
    "name",
    "active_ingredient",
    "laboratory_id",
    "origin_id",
    "category_id",
    "barcode",
    "unit_cost",
    "iva",
    "psychotropic",
    "is_colombian_origin",
    "group_id",
  ];

  fieldsToUnify.forEach((field) => {
    const keepValue = mergeFormData.value[field];
    const deleteValue = productToDelete[field];

    if (isEmpty(keepValue) && !isEmpty(deleteValue)) {
      mergeFormData.value[field] = deleteValue;
    }
  });
};

const switchProductToKeep = () => {
  const productToKeep =
    selectedProductToKeep.value === "product1"
      ? props.selectedProduct
      : productToMerge.value;

  mergeFormData.value = JSON.parse(JSON.stringify(productToKeep));
  unifyFields();

  mergeFormData.value.iva = mergeFormData.value.iva ? 1 : 0;
  mergeFormData.value.psychotropic = mergeFormData.value.psychotropic ? 1 : 0;
  mergeFormData.value.is_colombian_origin = mergeFormData.value.is_colombian_origin
    ? 1
    : 0;
};

const handleMerge = async () => {
  if (!props.selectedProduct || !productToMerge.value) return;

  isMerging.value = true;
  try {
    const productToKeepId =
      selectedProductToKeep.value === "product1"
        ? props.selectedProduct.id
        : productToMerge.value.id;

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

    await axios.post(`/products/${productToKeepId}`, updatePayload, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    const response = await axios.post("/products/merge", {
      product_id_1: props.selectedProduct.id,
      product_id_2: productToMerge.value.id,
      keep_product_id: productToKeepId,
    });

    if (response.data.success) {
      toast.success(response.data.message || "Fusión exitosa");
      closeProductModal();
      emit("merged");
    }
  } catch (error) {
    console.error("Error al fusionar:", error);
    toast.error(error.response?.data?.message || "Error al fusionar");
  } finally {
    isMerging.value = false;
  }
};
</script>

<template>
  <div>
    <!-- Modal para ingresar ID -->
    <VDialog v-model="isModalVisible" max-width="500px">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <span class="text-h6">Fusinar - Ingresar ID</span>
          <VBtn icon variant="text" size="small" @click="closeModal">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VTextField
            v-model="inputId"
            label="ID del producto a buscar"
            variant="outlined"
            type="number"
            autofocus
            :loading="loadingProduct"
            @keyup.enter="handleSubmit"
          />
        </VCardText>
        <VCardActions class="pa-4 d-flex gap-2">
          <VBtn color="secondary" variant="outlined" @click="closeModal" class="flex-grow-1">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="flat" @click="handleSubmit" :loading="loadingProduct" class="flex-grow-1">
            Buscar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal para comparar y fusionar -->
    <VDialog v-model="isProductModalVisible" max-width="1200px" persistent scrollable>
      <VCard v-if="props.selectedProduct && productToMerge">
        <VCardTitle class="bg-primary text-white d-flex align-center pa-4">
          <VIcon icon="tabler-package" class="me-2" />
          <span class="text-h6">Fusionar Productos</span>
          <VSpacer />
          <VBtn icon variant="text" color="white" @click="closeProductModal">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VRow>
            <VCol cols="12" md="6">
              <VCard
                variant="outlined"
                :class="{ 'border-primary border-2': selectedProductToKeep === 'product1' }"
                @click="selectedProductToKeep = 'product1'; switchProductToKeep()"
                class="cursor-pointer"
              >
                <VCardTitle class="d-flex align-center">
                  <VRadio
                    :model-value="selectedProductToKeep === 'product1'"
                    value="product1"
                    label="Producto Actual"
                    @click.stop="selectedProductToKeep = 'product1'; switchProductToKeep()"
                  />
                  <VSpacer />
                  <VChip :color="selectedProductToKeep === 'product1' ? 'success' : 'error'" size="small">
                    {{ selectedProductToKeep === "product1" ? "SE MANTIENE" : "SE ELIMINA" }}
                  </VChip>
                </VCardTitle>
                <VCardText>
                  <p><strong>ID:</strong> {{ selectedProduct.id }}</p>
                  <p><strong>Nombre:</strong> {{ selectedProduct.name }}</p>
                  <p><strong>P. Activo:</strong> {{ selectedProduct.active_ingredient }}</p>
                </VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard
                variant="outlined"
                :class="{ 'border-primary border-2': selectedProductToKeep === 'product2' }"
                @click="selectedProductToKeep = 'product2'; switchProductToKeep()"
                class="cursor-pointer"
              >
                <VCardTitle class="d-flex align-center">
                  <VRadio
                    :model-value="selectedProductToKeep === 'product2'"
                    value="product2"
                    label="Producto a Buscar"
                    @click.stop="selectedProductToKeep = 'product2'; switchProductToKeep()"
                  />
                  <VSpacer />
                  <VChip :color="selectedProductToKeep === 'product2' ? 'success' : 'error'" size="small">
                    {{ selectedProductToKeep === "product2" ? "SE MANTIENE" : "SE ELIMINA" }}
                  </VChip>
                </VCardTitle>
                <VCardText>
                  <p><strong>ID:</strong> {{ productToMerge.id }}</p>
                  <p><strong>Nombre:</strong> {{ productToMerge.name }}</p>
                  <p><strong>P. Activo:</strong> {{ productToMerge.active_ingredient }}</p>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <VDivider class="my-4" />
          <p class="text-h6">Datos Finales (Editable)</p>
          <VRow dense>
            <VCol cols="12" md="6">
              <VTextField v-model="mergeFormData.name" label="Nombre" variant="outlined" density="compact" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="mergeFormData.active_ingredient" label="P. Activo" variant="outlined" density="compact" />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect v-model="mergeFormData.laboratory_id" :items="laboratories" item-title="name" item-value="id" label="Laboratorio" variant="outlined" density="compact" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="mergeFormData.barcode" label="Código de Barras" variant="outlined" density="compact" />
            </VCol>
            <VCol cols="12" md="4" class="d-flex align-center">
              <VCheckbox v-model="mergeFormData.iva" :true-value="1" :false-value="0" label="IVA" hide-details />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VBtn color="secondary" variant="outlined" @click="closeProductModal">Cancelar</VBtn>
          <VSpacer />
          <VBtn color="primary" variant="flat" :loading="isMerging" @click="handleMerge">Confirmar Fusión</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
