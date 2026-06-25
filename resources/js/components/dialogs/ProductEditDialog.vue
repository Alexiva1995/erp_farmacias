<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { calculateStock, formatDateSimple } from "@/utils/formatters";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";
import { useBrandingStore } from "@/stores/useBrandingStore";

const authStore = useAuthStore();
const { xs } = useDisplay();
const brandingStore = useBrandingStore();

const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');
const isMinimarket = computed(() => brandingStore.settings.business_type === 'minimarket');

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  allProducts: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
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

const isLabDialogVisible = ref(false);
const newLabName = ref("");
const isSavingLab = ref(false);

const activeTab = ref(0);
const groupInput = ref(null);

const createLaboratory = async () => {
  if (!newLabName.value.trim()) return;

  isSavingLab.value = true;
  try {
    const response = await axios.post("/laboratories", {
      name: newLabName.value,
    });

    toast.success(isRestaurant.value ? "Marca creada con éxito" : "Laboratorio creado con éxito");
    emit("laboratory-created", response.data.laboratory);
    formData.value.laboratory_id = response.data.laboratory.id;
    isLabDialogVisible.value = false;
    newLabName.value = "";
  } catch (error) {
    if (error.response && error.response.status === 422) {
      toast.error(
        error.response.data.errors?.name?.[0] || "Error de validación",
      );
    } else {
      toast.error(isRestaurant.value ? "Error al crear la marca" : "Error al crear el laboratorio");
    }
  } finally {
    isSavingLab.value = false;
  }
};

const isNewProduct = computed(() => !formData.value.id);

const assignedGroupName = computed(() => {
  return formData.value.group ? formData.value.group.name : null;
});

const productsInGroup = computed(() => {
  if (!formData.value.group_id) return [];
  return props.allProducts.filter(
    (p) => p.group_id === formData.value.group_id && p.id !== formData.value.id,
  );
});
async function assignGroup() {
  const input = groupInput.value;
  if (!input) {
    toast.warning("Por favor, introduce un nombre o ID de grupo.");
    return;
  }

  try {
    const response = await axios.get("/groups/search", {
      params: { q: input },
    });

    const foundGroup = response.data;
    formData.value.group_id = foundGroup.id;
    formData.value.group = foundGroup;
    groupInput.value = null;
    toast.success(`Producto asignado al grupo "${foundGroup.name}".`);
  } catch (error) {
    if (error.response && error.response.status === 404) {
      toast.error("Grupo no encontrado. Verifica el nombre o ID.");
    } else {
      console.error("Error al buscar el grupo:", error);
      toast.error("Ocurrió un error al buscar el grupo.");
    }
  }
}

function removeGroup() {
  formData.value.group_id = null;
  formData.value.group = null;
  groupInput.value = null;
  selectedGroup.value = null;
  toast.success("Producto removido del grupo.");
}

const imagePreviewUrl = computed(() => {
  if (imageFile.value) {
    return URL.createObjectURL(imageFile.value);
  }
  if (formData.value.photo_url) {
    return formData.value.photo_url;
  }
  return null;
});

const groupProductsHeaders = computed(() => [
  { title: "Nombre", key: "name", sortable: false },
  { title: isRestaurant.value ? "Marca" : "Laboratorio", key: "laboratory.name", sortable: false },
  { title: "Stock", key: "lots", sortable: false },
]);

// calculateStock eliminado (ahora se importa)

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true },
);

watch(
  () => props.product,
  (newProduct) => {
    if (newProduct && Object.keys(newProduct).length > 0) {
      const clonedProduct = JSON.parse(JSON.stringify(newProduct));
      // Normalizar valores booleanos/números para los checkboxes
      clonedProduct.iva = clonedProduct.iva ? 1 : 0;
      clonedProduct.psychotropic = clonedProduct.psychotropic ? 1 : 0;
      clonedProduct.is_colombian_origin = clonedProduct.is_colombian_origin
        ? 1
        : 0;
      clonedProduct.is_novaventa = clonedProduct.is_novaventa ? 1 : 0;
      clonedProduct.is_scarce = clonedProduct.is_scarce ? 1 : 0;
      clonedProduct.is_unified_group = clonedProduct.is_unified_group ? 1 : 0;
      clonedProduct.no_pvp = clonedProduct.no_pvp ? 1 : 0;
       if (clonedProduct.product_suppliers) {
        clonedProduct.supplier_ids = clonedProduct.product_suppliers.map(ps => ps.supplier_id);
      } else {
        clonedProduct.supplier_ids = [];
      }
      if (clonedProduct.variants && Array.isArray(clonedProduct.variants)) {
        productVariants.value = clonedProduct.variants.map(v => {
          let name = v.attribute_value;
          let color = "#000000";
          try {
            if (v.attribute_value.startsWith('{')) {
              const parsed = JSON.parse(v.attribute_value);
              name = parsed.name || "";
              color = parsed.color || "#000000";
            }
          } catch (e) {}
          return {
            id: v.id,
            name: name,
            color: color,
            price_modifier: v.price_modifier || 0,
            stock: v.stock || 0,
          };
        });
      } else {
        productVariants.value = [];
      }
      formData.value = clonedProduct;
    } else {
      productVariants.value = [];
      formData.value = {
        name: "",
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
        unit_of_measure: null,
        supplier_ids: [],
        supplier_id: null,
      };
    }
    imageFile.value = null;
    formErrors.value = {};
  },
  { deep: true, immediate: true },
);

const groups = ref([]);
const loadingGroups = ref(false);
const selectedGroup = ref(null);

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

const lotHeaders = [
  { title: "Nombre", key: "lot_number", sortable: false },
  { title: "Ubicación", key: "location", sortable: false },
  { title: "Stock", key: "quantity", sortable: false },
  { title: "Exp.", key: "expiration_date", sortable: false },
];

// formatDate eliminado (ahora se importa)

const productVariants = ref([]);

const addVariantRow = () => {
  productVariants.value.push({
    name: "",
    color: "#000000",
    price_modifier: 0,
    stock: 0,
  });
};

const removeVariantRow = (idx) => {
  productVariants.value.splice(idx, 1);
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

  // Excluir unit_cost y sale_price del loop, los manejaremos después
  Object.keys(formData.value).forEach((key) => {
    // Saltar unit_cost y sale_price, los manejaremos después
    if (key === "unit_cost" || key === "sale_price") {
      return;
    }

    const value = formData.value[key];
    if (
      value !== null &&
      value !== undefined &&
      !Array.isArray(value) &&
      typeof value !== "object"
    ) {
      payload.append(key, value);
    }
  });
  if (imageFile.value) {
    payload.append("photo_url", imageFile.value);
  }

  // Solo enviar unit_cost si tiene un valor válido
  if (
    formData.value.unit_cost !== null &&
    formData.value.unit_cost !== undefined &&
    formData.value.unit_cost !== "" &&
    !isNaN(formData.value.unit_cost)
  ) {
    payload.append("unit_cost", formData.value.unit_cost);
  }

  // Para vendedores y supervisores, el precio debe ser 0
  // Para otros usuarios, solo enviar si tiene valor válido
  if (authStore.isVendedor || authStore.isSupervisor) {
    payload.append("sale_price", 0);
  } else if (
    formData.value.sale_price !== null &&
    formData.value.sale_price !== undefined &&
    formData.value.sale_price !== "" &&
    !isNaN(formData.value.sale_price)
  ) {
    payload.append("sale_price", formData.value.sale_price);
  }

  if (Array.isArray(formData.value.supplier_ids)) {
    formData.value.supplier_ids.forEach((id) => {
      payload.append("supplier_ids[]", id);
    });
  }

  // Adjuntar variantes de tonos si es minimarket
  if (isMinimarket.value && productVariants.value.length > 0) {
    productVariants.value.forEach((v, idx) => {
      if (v.id) {
        payload.append(`variants[${idx}][id]`, v.id);
      }
      payload.append(`variants[${idx}][name]`, v.name);
      payload.append(`variants[${idx}][color]`, v.color);
      payload.append(`variants[${idx}][price_modifier]`, v.price_modifier || 0);
      payload.append(`variants[${idx}][stock]`, v.stock || 0);
    });
  }

  emit("save", payload);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
    :fullscreen="$vuetify.display.xs"
  >
    <VCard v-if="formData" :class="[xs ? 'rounded-0 d-flex flex-column justify-start' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface']">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div :class="[xs ? 'pa-2' : 'pa-4', 'header-gradient d-flex align-center shadow-sm']">
          <VAvatar
            color="white"
            variant="flat"
            :size="xs ? 32 : 40"
            class="me-3 elevation-1"
          >
            <VIcon
              :icon="isNewProduct ? 'tabler-circle-plus' : 'tabler-edit'"
              size="24"
              color="primary"
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
                {{ isNewProduct ? "Registro Maestro" : "Gestión de Ficha" }} de Producto • Barrio Sucre
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
        <!-- Pestañas Premium -->
        <VTabs
          v-slot:default
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
          <VTab v-slot:default v-if="isMinimarket" :value="3" class="text-button font-weight-black">
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
          <!-- Pestaña General -->
          <VWindowItem :value="0" class="pa-2 pt-0">
            <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
              <!-- Información Básica -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información Básica</span>
                </div>

                <VCard
                  variant="flat"
                  :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
                >
                    <VRow dense>
                      <VCol cols="12" :md="isRestaurant || isMinimarket ? 12 : 6">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre del Producto</span>
                        <AppTextField
                          v-model="formData.name"
                          placeholder="Ej: Labial Matte Rouge"
                          variant="outlined"
                          density="comfortable"
                          :error-messages="formErrors.name"
                          class="rounded-lg font-weight-black"
                        />
                      </VCol>
                      <VCol v-if="!isRestaurant && !isMinimarket" cols="12" md="6">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Principio Activo</span>
                        <AppTextField
                          v-model="formData.active_ingredient"
                          placeholder="Ej: Ibuprofeno"
                          variant="outlined"
                          density="comfortable"
                          :error-messages="formErrors.active_ingredient"
                          class="rounded-lg font-weight-black"
                        />
                      </VCol>
                      <VCol cols="12" :md="isRestaurant || isMinimarket ? 6 : 4">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">{{ isRestaurant || isMinimarket ? 'Marca' : 'Laboratorio' }}</span>
                        <AppSelect
                          v-model="formData.laboratory_id"
                          placeholder="SELECCIONAR..."
                          :items="props.laboratories"
                          item-title="name"
                          item-value="id"
                          variant="outlined"
                          density="comfortable"
                          clearable
                          :error-messages="formErrors.laboratory_id"
                          class="rounded-lg font-weight-black"
                          hide-details="auto"
                        >
                          <template #append-inner>
                            <VBtn
                              icon="tabler-plus"
                              variant="tonal"
                              color="primary"
                              size="x-small"
                              class="rounded-lg"
                              @click.stop="isLabDialogVisible = true"
                            />
                          </template>
                        </AppSelect>
                      </VCol>
                      <VCol v-if="!isRestaurant && !isMinimarket" cols="12" md="4">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Origen</span>
                        <AppSelect
                          v-model="formData.origin_id"
                          placeholder="SELECCIONAR..."
                          :items="props.origins"
                          item-title="name"
                          item-value="id"
                          variant="outlined"
                          density="comfortable"
                          clearable
                          :error-messages="formErrors.origin_id"
                          class="rounded-lg font-weight-black"
                          hide-details="auto"
                        />
                      </VCol>
                      <VCol cols="12" :md="isRestaurant || isMinimarket ? 6 : 4">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Categoría</span>
                        <AppSelect
                          v-model="formData.category_id"
                          placeholder="SELECCIONAR..."
                          :items="props.categories"
                          item-title="name"
                          item-value="id"
                          variant="outlined"
                          density="comfortable"
                          clearable
                          :error-messages="formErrors.category_id"
                          class="rounded-lg font-weight-black"
                          hide-details="auto"
                        />
                      </VCol>
                      <VCol v-if="isRestaurant || isMinimarket" cols="12" md="6">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Proveedor</span>
                        <AppSelect
                          v-model="formData.supplier_id"
                          placeholder="SELECCIONAR..."
                          :items="props.suppliers"
                          item-title="name"
                          item-value="id"
                          variant="outlined"
                          density="comfortable"
                          clearable
                          :error-messages="formErrors.supplier_id"
                          class="rounded-lg font-weight-black"
                          hide-details="auto"
                        />
                      </VCol>
                    </VRow>
                  </VForm>
                </VCard>
              </div>

              <!-- Identificación y Multimedia -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator secondary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Identificación y Multimedia</span>
                </div>

                <VCard
                  variant="flat"
                  :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
                >
                  <VRow dense class="align-center">
                    <VCol cols="12" md="6">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Código de Barras</span>
                      <AppTextField
                        v-model="formData.barcode"
                        placeholder="SCAN O MANUAL..."
                        variant="outlined"
                        density="comfortable"
                        :error-messages="formErrors.barcode"
                        prepend-inner-icon="tabler-barcode"
                        class="rounded-lg font-weight-black"
                        hide-details="auto"
                      />
                    </VCol>
                    <VCol cols="12" md="6">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Imagen del Producto</span>
                      <VFileInput
                        v-model="imageFile"
                        accept="image/*"
                        variant="outlined"
                        placeholder="ELEGIR ARCHIVO"
                        prepend-inner-icon="tabler-camera"
                        clearable
                        :error-messages="formErrors.photo_url"
                        density="comfortable"
                        class="rounded-lg"
                        hide-details="auto"
                      />
                    </VCol>
                    <VCol
                      v-if="imagePreviewUrl"
                      cols="12"
                      class="d-flex justify-center mt-4"
                    >
                      <div class="pa-1 bg-surface border rounded-xl shadow-sm elevation-1">
                        <VImg
                          :src="imagePreviewUrl"
                          max-width="240"
                          height="240"
                          cover
                          class="rounded-lg"
                        />
                      </div>
                    </VCol>
                  </VRow>
                </VCard>
              </div>
            </div>
          </VWindowItem>

          <!-- Pestaña Variaciones (Solo para Minimarket) -->
          <VWindowItem v-if="isMinimarket" :value="3" class="pa-2 pt-0">
            <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center justify-space-between">
                  <div class="d-flex align-center gap-2">
                    <div class="header-indicator primary shadow-sm" />
                    <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Variaciones de Tono y Lote</span>
                  </div>
                  <VBtn
                    color="primary"
                    prepend-icon="tabler-plus"
                    size="small"
                    variant="flat"
                    class="font-weight-black rounded-lg"
                    @click="addVariantRow"
                  >
                    Añadir Tono
                  </VBtn>
                </div>

                <VCard variant="flat" :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']">
                  <div v-if="!productVariants || productVariants.length === 0" class="text-center py-6 text-disabled">
                    No hay tonos configurados para este producto. Agrega uno usando el botón superior.
                  </div>
                  <div v-else class="d-flex flex-column gap-4">
                    <VRow v-for="(v, idx) in productVariants" :key="idx" dense class="align-center border-b pb-4">
                      <!-- Nombre del Tono -->
                      <VCol cols="12" md="4">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Nombre del Tono</span>
                        <AppTextField
                          v-model="v.name"
                          placeholder="Ej: Creamy Cocoa"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="rounded-lg font-weight-black"
                        />
                      </VCol>
                      <!-- Selector Cromático Hexadecimal -->
                      <VCol cols="12" md="3">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Selector de Color</span>
                        <div class="d-flex align-center gap-2">
                          <input
                            type="color"
                            v-model="v.color"
                            style="width: 40px; height: 40px; border: 1px solid #ccc; border-radius: 8px; cursor: pointer; padding: 0; background: none;"
                          />
                          <AppTextField
                            v-model="v.color"
                            placeholder="#FFFFFF"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            class="rounded-lg font-weight-black flex-grow-1"
                            maxlength="7"
                          />
                        </div>
                      </VCol>
                      <!-- Ajuste de Precio -->
                      <VCol cols="12" md="2">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Modif. Precio</span>
                        <AppTextField
                          v-model="v.price_modifier"
                          type="number"
                          step="0.01"
                          placeholder="0.00"
                          prefix="$"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="rounded-lg font-weight-black"
                        />
                      </VCol>
                      <!-- Stock inicial / Lote -->
                      <VCol cols="12" md="2">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Lote Inicial</span>
                        <AppTextField
                          v-model="v.stock"
                          type="number"
                          placeholder="Cantidad"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                          class="rounded-lg font-weight-black"
                          :disabled="!isNewProduct"
                        />
                      </VCol>
                      <!-- Acción Eliminar -->
                      <VCol cols="12" md="1" class="text-right mt-4 mt-md-0">
                        <VBtn
                          icon="tabler-trash"
                          variant="tonal"
                          color="error"
                          size="small"
                          class="rounded-lg"
                          @click="removeVariantRow(idx)"
                        />
                      </VCol>
                    </VRow>
                  </div>
                </VCard>
              </div>
            </div>
          </VWindowItem>

          <!-- Pestaña Inventario -->
          <VWindowItem :value="1" class="pa-2 pt-0">
            <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
              <!-- Configuración Logística -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración Logística</span>
                </div>

                <VCard
                  variant="flat"
                  :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
                >
                  <VRow dense>
                    <VCol cols="12">
                      <div class="d-flex flex-wrap gap-3 w-100 mb-2">
                        <VCard
                          variant="flat"
                          class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                          style="min-width: 140px; max-width: 220px;"
                        >
                          <VSwitch
                            v-model="formData.iva"
                            label="IVA"
                            :true-value="1"
                            :false-value="0"
                            color="success"
                            density="compact"
                            hide-details
                            class="font-weight-black scale-90"
                          />
                                <VCard
                          v-if="!isRestaurant && !isMinimarket"
                          variant="flat"
                          class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                          style="min-width: 140px; max-width: 220px;"
                        >
                          <VSwitch
                            v-model="formData.is_novaventa"
                            label="Novaventa"
                            :true-value="1"
                            :false-value="0"
                            color="secondary"
                            density="compact"
                            hide-details
                            class="font-weight-black scale-90"
                          />
                        </VCard>

                        <VCard
                          v-if="!isRestaurant && !isMinimarket"
                          variant="flat"
                          class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                          style="min-width: 140px; max-width: 220px;"
                        >
                          <VSwitch
                            v-model="formData.psychotropic"
                            label="Psicotrópico"
                            :true-value="1"
                            :false-value="0"
                            color="warning"
                            density="compact"
                            hide-details
                            class="font-weight-black scale-90"
                          />
                        </VCard>

                        <VCard
                          v-if="!isRestaurant && !isMinimarket"
                          variant="flat"
                          class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                          style="min-width: 140px; max-width: 220px;"
                        >
                          <VSwitch
                            v-model="formData.is_colombian_origin"
                            label="COL"
                            :true-value="1"
                            :false-value="0"
                            color="primary"
                            density="compact"
                            hide-details
                            class="font-weight-black scale-90"
                          />
                        </VCard>

                        <VCard
                          v-if="!isMinimarket"
                          variant="flat"
                          class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                          style="min-width: 140px; max-width: 220px;"
                        >
                          <VSwitch
                            v-model="formData.is_scarce"
                            label="Redundante"
                            :true-value="1"
                            :false-value="0"
                            color="error"
                            density="compact"
                            hide-details
                            class="font-weight-black scale-90"
                          />
                        </VCard>

                        <VCard
                          v-if="!isMinimarket"
                          variant="flat"
                          class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                          style="min-width: 140px; max-width: 220px;"
                        >
                          <VSwitch
                            v-model="formData.is_unified_group"
                            label="Unificado"
                            :true-value="1"
                            :false-value="0"
                            color="info"
                            density="compact"
                            hide-details
                            class="font-weight-black scale-90"
                          />
                        </VCard>

                        <VCard
                          v-if="isRestaurant"
                          variant="flat"
                          class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                          style="min-width: 140px; max-width: 220px;"
                        >
                          <VSwitch
                            v-model="formData.no_pvp"
                            label="NO PVP"
                            :true-value="1"
                            :false-value="0"
                            color="error"
                            density="compact"
                            hide-details
                            class="font-weight-black scale-90"
                          />
                        </VCard>
                      </div>
                    </VCol>

                    <VCol v-if="!isNewProduct" cols="12" md="6" class="mt-6">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block ms-1">Costo de Compra</span>
                      <AppTextField
                        v-model="formData.unit_cost"
                        placeholder="0.00"
                        type="number"
                        prefix="$"
                        variant="outlined"
                        density="comfortable"
                        :readonly="!authStore.isAdmin"
                        :error-messages="formErrors.unit_cost"
                        class="rounded-lg font-weight-black"
                      />
                    </VCol>
                    <VCol v-if="!isNewProduct" cols="12" md="6" class="mt-6">
                      <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block ms-1">Precio de Venta</span>
                      <AppTextField
                        v-model="formData.sale_price"
                        placeholder="0.00"
                        type="number"
                        prefix="$"
                        variant="outlined"
                        density="comfortable"
                        :readonly="authStore.isVendedor || authStore.isSupervisor"
                        :error-messages="formErrors.sale_price"
                        class="rounded-lg font-weight-black"
                      />
                    </VCol>
                  </VRow>
                </VCard>
              </div>

              <!-- Lotes y Ubicación -->
              <div
                v-if="!isNewProduct && formData.lots?.length > 0"
                class="d-flex flex-column gap-3"
              >
                <div class="d-flex align-center justify-space-between mb-0">
                  <div class="d-flex align-center gap-2">
                    <div class="header-indicator secondary shadow-sm" />
                    <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Lotes y Ubicación</span>
                  </div>
                  <VChip
                    size="x-small"
                    color="secondary"
                    variant="flat"
                    class="font-weight-black rounded-lg px-3 shadow-sm"
                  >
                    {{ formData.lots.length }} LOTES ACTIVOS
                  </VChip>
                </div>

                <VCard
                  variant="flat"
                  class="bg-surface rounded-xl border shadow-sm overflow-hidden"
                >
                  <!-- Desktop Table -->
                  <div class="d-none d-sm-block">
                    <VDataTable
                      :headers="lotHeaders"
                      :items="formData.lots"
                      density="comfortable"
                      class="table-standard"
                      hide-default-footer
                    >
                      <template #item.quantity="{ item }">
                        <VChip
                          size="x-small"
                          :color="item.quantity > 0 ? 'success' : 'error'"
                          variant="tonal"
                          class="font-weight-black px-2 rounded-lg"
                        >
                          {{ item.quantity }} UNID.
                        </VChip>
                      </template>
                      <template #item.expiration_date="{ item }">
                        <span class="text-caption font-weight-black text-high-emphasis">{{ formatDate(item.expiration_date) }}</span>
                      </template>
                    </VDataTable>
                  </div>

                  <!-- Mobile Cards -->
                  <div class="d-block d-sm-none pa-3">
                    <div class="d-flex flex-column gap-2">
                      <VCard
                        v-for="item in formData.lots"
                        :key="item.id"
                        variant="flat"
                        class="pa-3 bg-light rounded-xl border"
                      >
                        <div class="d-flex justify-space-between align-center mb-2">
                          <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">LOTE: {{ item.lot_number }}</span>
                          <VChip
                            size="x-small"
                            :color="item.quantity > 0 ? 'success' : 'error'"
                            variant="flat"
                            class="font-weight-black"
                          >
                            {{ item.quantity }} <small class="ml-1 font-weight-bold">UNDS</small>
                          </VChip>
                        </div>
                        <div class="d-flex justify-space-between text-super-xs text-medium-emphasis mt-2 border-t pt-2 opacity-80">
                          <span class="d-flex align-center gap-1">
                            <VIcon icon="tabler-map-pin" size="12" color="primary" />
                            {{ item.location || "SIN UBICACIÓN" }}
                          </span>
                          <span class="d-flex align-center gap-1">
                            <VIcon icon="tabler-calendar" size="12" color="primary" />
                            {{ formatDate(item.expiration_date) }}
                          </span>
                        </div>
                      </VCard>
                    </div>
                  </div>
                </VCard>
              </div>
            </div>
          </VWindowItem>

          <!-- Pestaña Relaciones -->
          <VWindowItem :value="2" class="pa-2 pt-0">
            <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
              <!-- Jerarquía y Agrupación -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Jerarquía y Agrupación</span>
                </div>

                <VCard
                  variant="flat"
                  :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
                >
                  <div class="pa-4 bg-light rounded-xl border-dashed-2">
                    <div class="d-flex align-center gap-2 mb-4 leading-none">
                      <VIcon
                        icon="tabler-link"
                        size="18"
                        color="primary"
                      />
                      <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Asignación de Grupo Maestro</span>
                    </div>
                    <div 
                      v-if="!assignedGroupName"
                      class="d-flex gap-2 align-center w-100"
                    >
                      <VAutocomplete
                        v-model="selectedGroup"
                        :items="groups"
                        item-title="name"
                        item-value="id"
                        placeholder="BUSCAR GRUPO POR NOMBRE..."
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        class="bg-surface rounded-lg font-weight-black flex-grow-1"
                        :loading="loadingGroups"
                        return-object
                        @update:model-value="onGroupSelect"
                      >
                        <template #item="{ props, item }">
                          <VListItem v-bind="props">
                            <template #title>
                              <div class="font-weight-black text-uppercase text-xs d-flex align-center gap-1">
                                <span class="text-primary">#{{ item.raw.id }}</span>
                                <span class="text-disabled">|</span>
                                <span>{{ item.raw.name }}</span>
                              </div>
                            </template>
                          </VListItem>
                        </template>
                      </VAutocomplete>
                    </div>

                    <div
                      v-if="assignedGroupName"
                      :class="!assignedGroupName ? 'mt-4' : ''"
                    >
                      <VChip
                        color="primary"
                        variant="flat"
                        label
                        closable
                        class="font-weight-black px-4 rounded-lg shadow-sm"
                        height="32"
                        @click:close="removeGroup"
                      >
                        <VIcon
                          icon="tabler-hierarchy"
                          size="16"
                          class="me-2"
                        />
                        GRUPO ACTUAL: {{ assignedGroupName }}
                      </VChip>
                    </div>
                  </div>
                </VCard>
              </div>

              <!-- Proveedores Asociados (Solo Restaurante) -->
              <div v-if="isRestaurant" class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Proveedores del Producto</span>
                </div>

                <VCard
                  variant="flat"
                  :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
                >
                  <div class="pa-4 bg-light rounded-xl border-dashed-2">
                    <div class="d-flex align-center gap-2 mb-4 leading-none">
                      <VIcon
                        icon="tabler-truck"
                        size="18"
                        color="primary"
                      />
                      <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Vincular Proveedores (Seleccionar uno o más)</span>
                    </div>
                    <VAutocomplete
                      v-model="formData.supplier_ids"
                      :items="props.suppliers"
                      item-title="name"
                      item-value="id"
                      placeholder="SELECCIONAR PROVEEDORES..."
                      variant="outlined"
                      density="comfortable"
                      multiple
                      chips
                      closable-chips
                      class="bg-surface rounded-lg font-weight-black"
                      hide-details
                    />
                  </div>
                </VCard>
              </div>

              <!-- Productos del mismo grupo -->
              <div
                v-if="productsInGroup.length > 0"
                class="d-flex flex-column gap-3"
              >
                <div class="d-flex align-center justify-space-between mb-0">
                  <div class="d-flex align-center gap-2">
                    <div class="header-indicator secondary shadow-sm" />
                    <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Productos Relacionados</span>
                  </div>
                  <VChip
                    size="x-small"
                    color="secondary"
                    variant="flat"
                    class="font-weight-black rounded-lg px-3 shadow-sm"
                  >
                    {{ productsInGroup.length }} REGISTROS
                  </VChip>
                </div>

                <VCard
                  variant="flat"
                  class="bg-surface rounded-xl border shadow-sm overflow-hidden"
                >
                  <!-- Desktop Table -->
                  <div class="d-none d-sm-block">
                    <VDataTable
                      :headers="groupProductsHeaders"
                      :items="productsInGroup"
                      density="comfortable"
                      class="table-standard"
                    >
                      <template #item.lots="{ item }">
                        <VChip
                          size="x-small"
                          color="primary"
                          variant="tonal"
                          class="font-weight-black rounded-lg"
                        >
                          STK: {{ calculateStock(item) }} UNID.
                        </VChip>
                      </template>
                      <template #item.name="{ item }">
                        <span class="text-caption font-weight-black text-medium-emphasis uppercase">{{ item.name }}</span>
                      </template>
                    </VDataTable>
                  </div>

                  <!-- Mobile Cards -->
                  <div class="d-block d-sm-none pa-3">
                    <div class="d-flex flex-column gap-2">
                      <VCard
                        v-for="item in productsInGroup"
                        :key="item.id"
                        variant="flat"
                        class="pa-3 bg-light rounded-xl border"
                      >
                        <div class="d-flex align-center justify-space-between mb-2">
                          <h4 class="text-xs font-weight-black truncate-2-lines flex-grow-1 mr-2 leading-tight uppercase">
                            <span class="text-primary mr-1">#{{ item.id }}</span>
                            {{ item.name }}
                          </h4>
                          <VChip
                            size="x-small"
                            color="primary"
                            variant="flat"
                            class="font-weight-black"
                          >
                            {{ calculateStock(item) }}
                          </VChip>
                        </div>
                        <div class="text-super-xs text-disabled font-weight-black uppercase mt-1 opacity-80 letter-spacing-1">
                          {{ item.laboratory?.name || "SIN LABORATORIO" }}
                        </div>
                      </VCard>
                    </div>
                  </div>
                </VCard>
              </div>
            </div>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions :class="[xs ? 'pa-2' : 'pa-4 pa-sm-6', 'bg-surface border-t']">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="6"
            class="pa-1"
          >
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
          <VCol
            cols="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              :height="xs ? 44 : 50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon
                :icon="isNewProduct ? 'tabler-circle-check' : 'tabler-device-floppy'"
                size="18"
                class="me-2"
              />
              {{ isNewProduct ? "Crear" : "Guardar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Diálogo Premium para crear Laboratorio -->
  <VDialog
    v-model="isLabDialogVisible"
    max-width="450px"
    transition="dialog-bottom-transition"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="32"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-flask-2"
              size="18"
              color="primary"
            />
          </VAvatar>
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
        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm"
        >
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">{{ isRestaurant ? 'Nombre Oficial de la Marca' : 'Nombre Oficial del Laboratorio' }}</span>
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
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol cols="6">
            <VBtn
              variant="tonal"
              color="secondary"
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
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-secondary)) 0%, rgb(var(--v-theme-primary)) 100%) !important;
}

.header-gradient h2,
.header-gradient span {
  color: #ffffff !important;
}



.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  border-radius: 8px !important;
  block-size: 16px;
  inline-size: 4px;
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

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.table-standard :deep(.v-data-table-header) {
  background-color: rgba(var(--v-border-color), 0.05);
}

.table-standard :deep(.v-data-table-header th) {
  border-block-end: 2px solid rgba(var(--v-border-color), 0.1) !important;
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.table-standard :deep(td) {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  padding-block: 12px !important;
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
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
