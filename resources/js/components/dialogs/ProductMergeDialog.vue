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
    <VDialog
      v-model="isModalVisible"
      max-width="550px"
      :fullscreen="$vuetify.display.xs"
    >
      <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
        <!-- Cabecera Premium Step 1 -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar
              color="white"
              variant="flat"
              size="40"
              class="me-3 elevation-1"
            >
              <VIcon
                icon="tabler-search"
                size="24"
                color="primary"
              />
            </VAvatar>
            <div class="d-flex flex-column leading-none text-white">
              <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase">
                Fusionar Producto
              </h2>
              <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
                Localizar Registro para Fusión
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              @click="closeModal"
            />
          </div>
        </VCardTitle>

        <VCardText class="pa-6 bg-light">
          <div class="d-flex flex-column gap-3 mb-4">
            <div class="d-flex align-center gap-2">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Ingresar Identificador</span>
            </div>
            <VCard variant="flat" class="pa-5 bg-white rounded-xl border shadow-sm">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">ID del producto a buscar</span>
              <VTextField
                v-model="inputId"
                placeholder="EJ: 123456"
                variant="outlined"
                type="number"
                autofocus
                density="comfortable"
                class="rounded-lg font-weight-black"
                :loading="loadingProduct"
                @keyup.enter="handleSubmit"
              />
            </VCard>
          </div>
        </VCardText>

        <VCardActions class="pa-4 bg-white border-t px-6">
          <VRow dense class="w-100 ma-0">
            <VCol cols="6">
              <VBtn
                variant="tonal"
                color="secondary"
                height="44"
                block
                class="font-weight-black rounded-lg text-button uppercase"
                @click="closeModal"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="6">
              <VBtn
                color="primary"
                variant="flat"
                height="44"
                block
                class="font-weight-black rounded-lg shadow-primary text-button uppercase"
                :loading="loadingProduct"
                @click="handleSubmit"
              >
                Buscar
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal para comparar y fusionar -->
    <VDialog
      v-model="isProductModalVisible"
      max-width="1200px"
      persistent
      scrollable
      :fullscreen="$vuetify.display.xs"
    >
      <VCard v-if="props.selectedProduct && productToMerge" class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
        <!-- Cabecera Premium Step 2 -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar
              color="white"
              variant="flat"
              size="40"
              class="me-3 elevation-1"
            >
              <VIcon
                icon="tabler-package"
                size="24"
                color="primary"
              />
            </VAvatar>
            <div class="d-flex flex-column leading-none text-white">
              <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase">
                Confirmar Fusión de Productos
              </h2>
              <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
                Consolidación de Registros Maestros
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              @click="closeProductModal"
            />
          </div>
        </VCardTitle>

        <VCardText class="pa-0 bg-light">
          <VContainer fluid class="pa-6">
            <div class="d-flex flex-column gap-6">
              <!-- Selección de Registro Base -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Seleccionar Registro Base (Cual se Mantiene)</span>
                </div>
                
                <VRow>
                  <VCol cols="12" md="6">
                    <VCard
                      variant="flat"
                      :class="[
                        'rounded-xl border shadow-sm cursor-pointer transition-all',
                        selectedProductToKeep === 'product1' ? 'border-primary border-opacity-100 bg-primary-light elevation-2' : 'bg-white opacity-60'
                      ]"
                      @click="selectedProductToKeep = 'product1'; switchProductToKeep()"
                    >
                      <div class="pa-4">
                        <div class="d-flex align-center justify-space-between mb-3">
                          <VRadio
                            :model-value="selectedProductToKeep === 'product1'"
                            value="product1"
                            color="primary"
                            label="PRODUCTO ACTUAL"
                            class="font-weight-black uppercase scale-90"
                            @click.stop="selectedProductToKeep = 'product1'; switchProductToKeep()"
                          />
                          <VChip
                            :color="selectedProductToKeep === 'product1' ? 'success' : 'error'"
                            size="x-small"
                            label
                            class="font-weight-black uppercase"
                          >
                            {{ selectedProductToKeep === "product1" ? "SE MANTIENE" : "SE ELIMINA" }}
                          </VChip>
                        </div>
                        <div class="d-flex flex-column gap-1 bg-light pa-3 rounded-lg border-dashed">
                          <div class="d-flex justify-space-between text-xs">
                            <span class="text-disabled font-weight-black uppercase">ID:</span>
                            <span class="font-weight-black text-primary">{{ selectedProduct.id }}</span>
                          </div>
                          <div class="d-flex flex-column text-xs">
                            <span class="text-disabled font-weight-black uppercase">Nombre:</span>
                            <span class="font-weight-black text-high-emphasis uppercase truncate">{{ selectedProduct.name }}</span>
                          </div>
                          <div class="d-flex flex-column text-xs">
                            <span class="text-disabled font-weight-black uppercase">P. Activo:</span>
                            <span class="font-weight-black text-medium-emphasis uppercase truncate">{{ selectedProduct.active_ingredient }}</span>
                          </div>
                        </div>
                      </div>
                    </VCard>
                  </VCol>

                  <VCol cols="12" md="6">
                    <VCard
                      variant="flat"
                      :class="[
                        'rounded-xl border shadow-sm cursor-pointer transition-all',
                        selectedProductToKeep === 'product2' ? 'border-primary border-opacity-100 bg-primary-light elevation-2' : 'bg-white opacity-60'
                      ]"
                      @click="selectedProductToKeep = 'product2'; switchProductToKeep()"
                    >
                      <div class="pa-4">
                        <div class="d-flex align-center justify-space-between mb-3">
                          <VRadio
                            :model-value="selectedProductToKeep === 'product2'"
                            value="product2"
                            color="primary"
                            label="PRODUCTO ENCONTRADO"
                            class="font-weight-black uppercase scale-90"
                            @click.stop="selectedProductToKeep = 'product2'; switchProductToKeep()"
                          />
                          <VChip
                            :color="selectedProductToKeep === 'product2' ? 'success' : 'error'"
                            size="x-small"
                            label
                            class="font-weight-black uppercase"
                          >
                            {{ selectedProductToKeep === "product2" ? "SE MANTIENE" : "SE ELIMINA" }}
                          </VChip>
                        </div>
                        <div class="d-flex flex-column gap-1 bg-light pa-3 rounded-lg border-dashed">
                          <div class="d-flex justify-space-between text-xs">
                            <span class="text-disabled font-weight-black uppercase">ID:</span>
                            <span class="font-weight-black text-primary">{{ productToMerge.id }}</span>
                          </div>
                          <div class="d-flex flex-column text-xs">
                            <span class="text-disabled font-weight-black uppercase">Nombre:</span>
                            <span class="font-weight-black text-high-emphasis uppercase truncate">{{ productToMerge.name }}</span>
                          </div>
                          <div class="d-flex flex-column text-xs">
                            <span class="text-disabled font-weight-black uppercase">P. Activo:</span>
                            <span class="font-weight-black text-medium-emphasis uppercase truncate">{{ productToMerge.active_ingredient }}</span>
                          </div>
                        </div>
                      </div>
                    </VCard>
                  </VCol>
                </VRow>
              </div>

              <!-- Datos Finales Consolidados -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator secondary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Revisión de Datos Finales</span>
                </div>
                
                <VCard variant="flat" class="pa-5 bg-white rounded-xl border shadow-sm">
                  <VRow dense>
                    <VCol cols="12" md="6">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre Maestro</span>
                      <VTextField v-model="mergeFormData.name" variant="outlined" density="comfortable" class="rounded-lg font-weight-black" hide-details="auto" />
                    </VCol>
                    <VCol cols="12" md="6">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Principio Activo</span>
                      <VTextField v-model="mergeFormData.active_ingredient" variant="outlined" density="comfortable" class="rounded-lg font-weight-black" hide-details="auto" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Laboratorio</span>
                      <VSelect v-model="mergeFormData.laboratory_id" :items="laboratories" item-title="name" item-value="id" variant="outlined" density="comfortable" class="rounded-lg font-weight-black" hide-details="auto" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Código de Barras</span>
                      <VTextField v-model="mergeFormData.barcode" variant="outlined" density="comfortable" class="rounded-lg font-weight-black" hide-details="auto" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Configuración</span>
                      <VCard variant="flat" class="pa-2 bg-light rounded-lg border-dashed d-flex align-center h-100 min-height-44">
                        <VCheckbox v-model="mergeFormData.iva" :true-value="1" :false-value="0" label="APLICA IVA" class="font-weight-black scale-90" hide-details />
                      </VCard>
                    </VCol>
                  </VRow>
                </VCard>
              </div>
              
              <VAlert
                type="info"
                variant="tonal"
                density="compact"
                icon="tabler-info-circle"
                class="rounded-xl font-weight-bold"
              >
                Al fusionar, el producto que **SE ELIMINA** transferirá todos sus lotes, historiales y movimientos al producto que **SE MANTIENE**. Esta acción no se puede deshacer.
              </VAlert>
            </div>
          </VContainer>
        </VCardText>

        <VCardActions class="pa-4 bg-white border-t px-6">
          <VRow dense class="w-100 ma-0">
            <VCol cols="6">
              <VBtn
                variant="tonal"
                color="secondary"
                height="44"
                block
                class="font-weight-black rounded-lg text-button uppercase"
                @click="closeProductModal"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="6">
              <VBtn
                color="primary"
                variant="flat"
                height="44"
                block
                class="font-weight-black rounded-lg shadow-primary text-button uppercase"
                :loading="isMerging"
                @click="handleMerge"
              >
                Confirmar Fusión
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
}

.bg-primary-light {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

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

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.scale-90 {
  transform: scale(0.9);
  transform-origin: left center;
}

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}

.uppercase {
  text-transform: uppercase;
}

.transition-all {
  transition: all 0.25s ease-in-out;
}

.min-height-44 {
  min-height: 44px;
}
</style>
