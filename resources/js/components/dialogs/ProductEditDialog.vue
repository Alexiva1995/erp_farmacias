<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";
import { useBrandingStore } from "@/stores/useBrandingStore";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import ImageCropperDialog from "@/components/dialogs/ImageCropperDialog.vue";
import ProductEditGeneralTab from "./product-edit/ProductEditGeneralTab.vue";
import ProductEditInventoryTab from "./product-edit/ProductEditInventoryTab.vue";
import ProductEditVariationsTab from "./product-edit/ProductEditVariationsTab.vue";
import ProductEditRelationsTab from "./product-edit/ProductEditRelationsTab.vue";

const { xs } = useDisplay();
const brandingStore = useBrandingStore();

const isRestaurant = computed(() => brandingStore.settings?.business_type === "restaurant");

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  allProducts: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  isSaving: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:modelValue",
  "save",
  "clearErrors",
  "laboratory-created",
]);

const formData = ref({});
const imageFile = ref(null);
const formErrors = ref({});

// --- Cropper de imagen ---
const cropperOpen = ref(false);
const cropperSrc = ref("");
const imgPreviewOpen = ref(false);
let skipCropper = false;

watch(imageFile, (file) => {
  if (!file || !(file instanceof File)) return;
  if (skipCropper) {
    skipCropper = false;
    return;
  }
  const reader = new FileReader();
  reader.onload = (e) => {
    cropperSrc.value = e.target.result;
    cropperOpen.value = true;
  };
  reader.readAsDataURL(file);
});

const onCropConfirm = (croppedFile) => {
  skipCropper = true;
  imageFile.value = croppedFile;
};

const isLabDialogVisible = ref(false);
const newLabName = ref("");
const isSavingLab = ref(false);

const activeTab = ref(0);

const handleNextTab = () => {
  if (activeTab.value === 0) {
    activeTab.value = brandingStore.settings?.enable_variations ? 3 : 1;
  } else if (activeTab.value === 3) {
    activeTab.value = 1;
  } else if (activeTab.value === 1) {
    activeTab.value = 2;
  }
};

const createLaboratory = async () => {
  if (!newLabName.value.trim()) return;

  isSavingLab.value = true;
  try {
    const response = await axios.post("/laboratories", {
      name: newLabName.value,
    });

    toast.success("Laboratorio / Marca creada con éxito");
    emit("laboratory-created", response.data.laboratory);
    formData.value.laboratory_id = response.data.laboratory.id;
    isLabDialogVisible.value = false;
    newLabName.value = "";
  } catch (error) {
    if (error.response && error.response.status === 422) {
      toast.error(error.response.data.errors?.name?.[0] || "Error de validación");
    } else {
      toast.error("Error al crear el laboratorio / marca");
    }
  } finally {
    isSavingLab.value = false;
  }
};

const isNewProduct = computed(() => !formData.value.id);

const assignedGroupName = computed(() => {
  return formData.value.group ? formData.value.group.name : null;
});

// Sincronizar errores
watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true, immediate: true }
);

// Inicializar formData al cambiar el producto seleccionado
watch(
  () => props.product,
  (newProduct) => {
    if (newProduct && Object.keys(newProduct).length > 0) {
      const clonedProduct = JSON.parse(JSON.stringify(newProduct));
      clonedProduct.iva = clonedProduct.iva ? 1 : 0;
      clonedProduct.psychotropic = clonedProduct.psychotropic ? 1 : 0;
      clonedProduct.is_colombian_origin = clonedProduct.is_colombian_origin ? 1 : 0;
      clonedProduct.is_novaventa = clonedProduct.is_novaventa ? 1 : 0;
      clonedProduct.is_scarce = clonedProduct.is_scarce ? 1 : 0;
      clonedProduct.is_unified_group = clonedProduct.is_unified_group ? 1 : 0;
      clonedProduct.no_pvp = clonedProduct.no_pvp ? 1 : 0;
      if (clonedProduct.product_suppliers) {
        clonedProduct.supplier_ids = clonedProduct.product_suppliers.map((ps) => ps.supplier_id);
      } else {
        clonedProduct.supplier_ids = [];
      }
      clonedProduct.supplier_id = clonedProduct.supplier_id || null;
      formData.value = clonedProduct;
    } else {
      formData.value = {
        name: "",
        description: "",
        active_ingredient: "",
        laboratory_id: null,
        unit_cost: null,
        sale_price: null,
        origin_id: null,
        category_id: null,
        group_id: null,
        group: null,
        barcode: "",
        iva: 0,
        psychotropic: 0,
        is_colombian_origin: 0,
        is_novaventa: 0,
        is_scarce: 0,
        is_unified_group: 0,
        no_pvp: 0,
        lots: [],
        photo_url: null,
        presentation: null,
        initial_stock: null,
        unit_of_measure: null,
        supplier_ids: [],
        supplier_id: null,
        master_id: null,
      };
    }
    masterFound.value = false;
    masterFoundProduct.value = null;
    imageFile.value = null;
    formErrors.value = {};
  },
  { deep: true, immediate: true }
);

// --- Búsqueda y Auto-completado con Catálogo Maestro ---
const isMasterSearching = ref(false);
const masterFound = ref(false);
const masterFoundProduct = ref(null);
let barcodeSearchTimer = null;

const checkBarcodeMasterCatalog = async (barcode) => {
  if (!barcode || barcode.length < 4) {
    masterFound.value = false;
    masterFoundProduct.value = null;
    return;
  }

  isMasterSearching.value = true;
  try {
    const { data } = await axios.get("/master-products/search-barcode", {
      params: { barcode: barcode.trim() },
    });

    if (data.found && data.product) {
      masterFound.value = true;
      masterFoundProduct.value = data.product;

      if (!formData.value.name || formData.value.name.trim() === "") {
        formData.value.name = data.product.name;
      }
      if (!formData.value.active_ingredient && data.product.active_ingredient) {
        formData.value.active_ingredient = data.product.active_ingredient;
      }
      if (!formData.value.presentation && data.product.presentation) {
        formData.value.presentation = data.product.presentation;
      }
      if (!formData.value.unit_of_measure && data.product.unit_of_measure) {
        formData.value.unit_of_measure = data.product.unit_of_measure;
      }
      if (!formData.value.description && data.product.description) {
        formData.value.description = data.product.description;
      }

      formData.value.master_id = data.product.id;
      toast.info(`Producto homologado encontrado en el Catálogo Maestro (ID #${data.product.id})`);
    } else {
      masterFound.value = false;
      masterFoundProduct.value = null;
      formData.value.master_id = null;
    }
  } catch (error) {
    masterFound.value = false;
    masterFoundProduct.value = null;
  } finally {
    isMasterSearching.value = false;
  }
};

watch(
  () => formData.value.barcode,
  (newBarcode) => {
    if (!isNewProduct.value) return;
    if (barcodeSearchTimer) clearTimeout(barcodeSearchTimer);
    barcodeSearchTimer = setTimeout(() => {
      checkBarcodeMasterCatalog(newBarcode);
    }, 450);
  }
);

const loadingGroups = ref(false);
const selectedGroup = ref(null);
const groups = ref([]);

const fetchAllGroups = async () => {
  loadingGroups.value = true;
  try {
    const response = await axios.get("/groups/consult-all");
    groups.value = response.data.data || [];
  } catch (error) {
    console.error("Error al cargar grupos:", error);
  } finally {
    loadingGroups.value = false;
  }
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      fetchAllGroups();
    }
  },
  { immediate: true }
);

function onGroupSelect(group) {
  if (!group) return;
  formData.value.group_id = group.id;
  formData.value.group = group;
  selectedGroup.value = null;
  toast.success(`Producto asignado al grupo "${group.name}".`);
}

function removeGroup() {
  formData.value.group_id = null;
  formData.value.group = null;
  selectedGroup.value = null;
  toast.success("Producto removido del grupo.");
}

const addVariantRow = () => {
  if (!formData.value.variants) {
    formData.value.variants = [];
  }
  formData.value.variants.push({
    attribute_type: "shade",
    attribute_value: "",
    color_hex: "#E20074",
    price_modifier: 0,
  });
};

const removeVariantRow = (index) => {
  formData.value.variants.splice(index, 1);
};

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  const payload = new FormData();

  Object.keys(formData.value).forEach((key) => {
    if (key === "unit_cost" || key === "sale_price" || key === "variants" || key === "initial_stock") {
      return;
    }

    const value = formData.value[key];
    if (value !== null && value !== undefined && !Array.isArray(value) && typeof value !== "object") {
      payload.append(key, value);
    }
  });

  if (imageFile.value) {
    payload.append("photo_url", imageFile.value);
  }

  if (
    formData.value.unit_cost !== null &&
    formData.value.unit_cost !== undefined &&
    formData.value.unit_cost !== "" &&
    !isNaN(formData.value.unit_cost)
  ) {
    payload.append("unit_cost", formData.value.unit_cost);
  }

  if (
    formData.value.sale_price !== null &&
    formData.value.sale_price !== undefined &&
    formData.value.sale_price !== "" &&
    !isNaN(formData.value.sale_price)
  ) {
    payload.append("sale_price", formData.value.sale_price);
  }

  if (
    isNewProduct.value &&
    formData.value.initial_stock !== null &&
    formData.value.initial_stock !== undefined &&
    formData.value.initial_stock !== "" &&
    !isNaN(formData.value.initial_stock)
  ) {
    payload.append("initial_stock", formData.value.initial_stock);
  }

  if (Array.isArray(formData.value.supplier_ids)) {
    formData.value.supplier_ids.forEach((id) => {
      payload.append("supplier_ids[]", id);
    });
  }

  if (brandingStore.settings?.enable_variations && Array.isArray(formData.value.variants)) {
    payload.append("variants", JSON.stringify(formData.value.variants));
  }

  emit("save", payload);
};

const imagePreviewUrl = computed(() => {
  if (imageFile.value && imageFile.value instanceof File) {
    return URL.createObjectURL(imageFile.value);
  }
  return formData.value?.photo_url || null;
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
    :fullscreen="xs"
  >
    <VCard v-if="formData" :class="[xs ? 'rounded-0 d-flex flex-column justify-start' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface']">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div :class="[xs ? 'pa-2' : 'pa-4', 'header-gradient d-flex align-center shadow-sm']">
          <VAvatar
            color="white"
            variant="flat"
            :size="xs ? 34 : 40"
            class="me-3 elevation-1 rounded-lg"
          >
            <VIcon
              :icon="isNewProduct ? 'tabler-circle-plus' : 'tabler-pencil'"
              :size="xs ? 20 : 24"
              style="color: #7A0099 !important;"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ isNewProduct ? "Añadir Nuevo Producto" : "Editar Producto" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                {{ isNewProduct ? "Registro Maestro" : "Gestión de Ficha" }} de Producto
              </span>
            </div>
          </div>

          <VChip
            v-if="assignedGroupName && !xs"
            class="ml-4 font-weight-black rounded-lg"
            color="white"
            variant="tonal"
            size="small"
          >
            GRUPO: {{ assignedGroupName }}
          </VChip>

          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <VCardText :class="['pa-0 bg-light', xs ? '' : 'd-flex flex-column justify-start']">
        <!-- Pestañas -->
        <VTabs
          v-model="activeTab"
          grow
          color="primary"
          class="border-b mb-0"
          :height="xs ? 44 : 54"
        >
          <VTab :value="0" class="text-button font-weight-black">
            <VIcon icon="tabler-info-circle" class="me-2" size="18" />
            General
          </VTab>
          <VTab v-if="brandingStore.settings?.enable_variations" :value="3" class="text-button font-weight-black">
            <VIcon icon="tabler-palette" class="me-2" size="18" />
            Variaciones
          </VTab>
          <VTab :value="1" class="text-button font-weight-black">
            <VIcon icon="tabler-database" class="me-2" size="18" />
            Inventario
          </VTab>
          <VTab :value="2" class="text-button font-weight-black">
            <VIcon icon="tabler-hierarchy-2" class="me-2" size="18" />
            Relaciones
          </VTab>
        </VTabs>

        <VWindow
          v-model="activeTab"
          :class="[xs ? 'pa-0' : 'pa-4 pa-sm-6', 'product-edit-window']"
          :style="xs ? 'min-block-size: auto !important;' : 'max-block-size: 60vh; overflow-y: auto;'"
        >
          <!-- Pestaña General Desacoplada -->
          <VWindowItem :value="0" class="pa-2 pt-0">
            <ProductEditGeneralTab
              v-model:form-data="formData"
              :form-errors="formErrors"
              :laboratories="props.laboratories"
              :origins="props.origins"
              :categories="props.categories"
              :suppliers="props.suppliers"
              :image-file="imageFile"
              :image-preview-url="imagePreviewUrl"
              :is-master-searching="isMasterSearching"
              :master-found="masterFound"
              :master-found-product="masterFoundProduct"
              :xs="xs"
              @update:image-file="imageFile = $event"
              @open-lab-dialog="isLabDialogVisible = true"
              @open-img-preview="imgPreviewOpen = true"
              @submit="submitForm"
            />
          </VWindowItem>

          <!-- Pestaña Inventario Desacoplada -->
          <VWindowItem :value="1" class="pa-2 pt-0">
            <ProductEditInventoryTab
              :form-data="formData"
              :form-errors="formErrors"
              :is-new-product="isNewProduct"
              :xs="xs"
            />
          </VWindowItem>

          <!-- Pestaña Variaciones Desacoplada -->
          <VWindowItem v-if="brandingStore.settings?.enable_variations" :value="3" class="pa-2 pt-0">
            <ProductEditVariationsTab
              :form-data="formData"
              :xs="xs"
              @add-variant="addVariantRow"
              @remove-variant="removeVariantRow"
            />
          </VWindowItem>

          <!-- Pestaña Relaciones Desacoplada -->
          <VWindowItem :value="2" class="pa-2 pt-0">
            <ProductEditRelationsTab
              :form-data="formData"
              :groups="groups"
              :suppliers="props.suppliers"
              :all-products="props.allProducts"
              :loading-groups="loadingGroups"
              :selected-group="selectedGroup"
              :xs="xs"
              @group-selected="onGroupSelect"
              @remove-group="removeGroup"
            />
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions :class="[xs ? 'pa-2' : 'pa-4 pa-sm-6', 'bg-surface border-t']">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              :height="xs ? 44 : 50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              :height="xs ? 44 : 50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="props.isSaving"
              :disabled="props.isSaving"
              @click="isNewProduct && activeTab !== 2 ? handleNextTab() : submitForm()"
            >
              <VIcon
                :icon="isNewProduct ? (activeTab === 2 ? 'tabler-circle-check' : 'tabler-arrow-right') : 'tabler-device-floppy'"
                size="18"
                class="me-2"
              />
              {{ isNewProduct ? (activeTab === 2 ? "Crear" : "Siguiente") : "Guardar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Diálogo para crear Laboratorio / Marca -->
  <VDialog
    v-model="isLabDialogVisible"
    max-width="450px"
    transition="dialog-bottom-transition"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <div class="avatar-icon-box-xs d-flex align-center justify-center me-3 rounded-lg shadow-sm">
            <VIcon icon="tabler-flask-2" size="18" class="header-icon" />
          </div>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-subtitle-1 font-weight-black leading-tight mb-0 uppercase">
              {{ isRestaurant ? 'Nueva Marca' : 'Nuevo Laboratorio' }}
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">Registro Maestro</span>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="x-small"
            class="rounded-lg"
            @click="isLabDialogVisible = false"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light">
        <VCard variant="flat" class="pa-5 bg-white rounded-xl border shadow-sm">
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">
            {{ isRestaurant ? 'Nombre Oficial de la Marca' : 'Nombre Oficial del Laboratorio' }}
          </span>
          <VTextField
            v-model="newLabName"
            :placeholder="isRestaurant ? 'EJ: MARCA NESTLÉ' : 'EJ: LABORATORIOS GOVIMAR'"
            variant="outlined"
            density="comfortable"
            autofocus
            hide-details="auto"
            class="rounded-lg font-weight-black"
            @keydown.enter="createLaboratory"
          />
        </VCard>
      </VCardText>

      <VCardActions class="pa-4 bg-surface border-t px-6">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6">
            <VBtn
              color="secondary"
              variant="tonal"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="isLabDialogVisible = false"
            >
              Cerrar
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="isSavingLab"
              :disabled="!newLabName.trim()"
              @click="createLaboratory"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Preview ecommerce de imagen -->
  <VDialog v-model="imgPreviewOpen" max-width="340" scrollable>
    <VCard class="rounded-xl overflow-hidden pa-0">
      <div class="d-flex align-center justify-space-between px-4 py-3" style="background: linear-gradient(135deg, rgb(var(--v-theme-secondary)) 0%, rgb(var(--v-theme-primary)) 100%);">
        <span class="text-subtitle-2 font-weight-black text-white" style="letter-spacing:1px;">VISTA PREVIA TIENDA</span>
        <VBtn icon="tabler-x" variant="text" color="white" size="small" density="compact" @click="imgPreviewOpen = false" />
      </div>
      <div style="aspect-ratio:1; background:#F5F5F5; overflow:hidden;">
        <img
          :src="imagePreviewUrl"
          style="width:100%; height:100%; object-fit:cover; display:block;"
          alt="Vista previa"
        />
      </div>
      <div class="px-4 py-2 text-center">
        <span class="text-caption text-disabled" style="letter-spacing:1px; font-size:10px;">ASÍ SE VERÁ EN EL ECOMMERCE</span>
      </div>
    </VCard>
  </VDialog>

  <!-- Editor de recorte de imagen -->
  <ImageCropperDialog
    v-model="cropperOpen"
    :image-source="cropperSrc"
    @confirm="onCropConfirm"
  />
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-secondary)) 0%, rgb(var(--v-theme-primary)) 100%) !important;
}

.avatar-icon-box {
  width: 40px;
  height: 40px;
  background-color: #ffffff !important;
  flex-shrink: 0;
}

.avatar-icon-box-xs {
  width: 32px;
  height: 32px;
  background-color: #ffffff !important;
  flex-shrink: 0;
}

.header-icon {
  color: rgb(var(--v-theme-primary)) !important;
}

.header-gradient h2,
.header-gradient span {
  color: #ffffff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
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

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}

.uppercase {
  text-transform: uppercase;
}

.product-edit-window :deep(.v-window__container) {
  block-size: auto !important;
  min-block-size: auto !important;
}

.product-edit-window :deep(.v-window-item) {
  block-size: auto !important;
  min-block-size: auto !important;
  margin-block-start: 0 !important;
  padding-block-start: 0 !important;
}

@media (max-width: 600px) {
  .product-edit-window :deep(.v-window__container) {
    max-block-size: none !important;
    overflow-y: visible !important;
  }
}
</style>
