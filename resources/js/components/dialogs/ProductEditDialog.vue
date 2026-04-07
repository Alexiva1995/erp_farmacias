<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { calculateStock, formatDate } from "@/utils/formatters";
import { computed, ref, watch } from "vue";

const authStore = useAuthStore();

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

    toast.success("Laboratorio creado con éxito");
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
      toast.error("Error al crear el laboratorio");
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

const groupProductsHeaders = [
  { title: "Nombre", key: "name", sortable: false },
  { title: "Laboratorio", key: "laboratory.name", sortable: false },
  { title: "Stock", key: "lots", sortable: false },
];

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
      formData.value = clonedProduct;
    } else {
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
        lots: [],
        photo_url: null,
      };
    }
    imageFile.value = null;
    formErrors.value = {};
  },
  { deep: true, immediate: true },
);

const lotHeaders = [
  { title: "Nombre", key: "lot_number", sortable: false },
  { title: "Ubicación", key: "location", sortable: false },
  { title: "Stock", key: "quantity", sortable: false },
  { title: "Exp.", key: "expiration_date", sortable: false },
];

// formatDate eliminado (ahora se importa)

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
    <VCard v-if="formData" :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
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
            v-if="assignedGroupName"
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

      <VCardText class="pa-0 bg-light d-flex flex-column">
        <!-- Pestañas Premium -->
        <VTabs
          v-model="activeTab"
          grow
          bg-color="white"
          color="primary"
          class="border-b"
          height="54"
        >
          <VTab :value="0" class="text-button font-weight-black">
            <VIcon icon="tabler-info-circle" class="me-2" size="18" />
            General
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
          class="pa-4 pa-sm-6"
          style="max-block-size: 60vh; overflow-y: auto"
        >
          <!-- Pestaña General -->
          <VWindowItem :value="0">
            <div class="d-flex flex-column gap-6">
              <!-- Información Básica -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información Básica</span>
                </div>

                <VCard
                  variant="flat"
                  class="pa-5 bg-white rounded-xl border shadow-sm"
                >
                  <VForm @submit.prevent="submitForm">
                    <VRow dense>
                      <VCol cols="12" md="6">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre del Producto</span>
                        <AppTextField
                          v-model="formData.name"
                          placeholder="Ej: Ibuprofeno 400mg"
                          variant="outlined"
                          density="comfortable"
                          :error-messages="formErrors.name"
                          class="rounded-lg font-weight-black"
                        />
                      </VCol>
                      <VCol cols="12" md="6">
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
                      <VCol cols="12" md="4">
                        <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Laboratorio</span>
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
                      <VCol cols="12" md="4">
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
                      <VCol cols="12" md="4">
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
                  class="pa-5 bg-white rounded-xl border shadow-sm"
                >
                  <VRow dense>
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
                      <div class="d-flex flex-column h-100">
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
                      </div>
                    </VCol>
                    <VCol
                      v-if="imagePreviewUrl"
                      cols="12"
                      class="d-flex justify-center mt-4"
                    >
                      <div class="pa-1 bg-white border rounded-xl shadow-sm elevation-1">
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

          <!-- Pestaña Inventario -->
          <VWindowItem :value="1">
            <div class="d-flex flex-column gap-6">
              <!-- Configuración Logística -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración Logística</span>
                </div>

                <VCard
                  variant="flat"
                  class="pa-5 bg-white rounded-xl border shadow-sm"
                >
                  <VRow dense>
                    <VCol cols="12" md="4">
                      <VCard
                        variant="flat"
                        class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center"
                      >
                        <VSwitch
                          v-model="formData.iva"
                          label="Aplica IVA (G)"
                          :true-value="1"
                          :false-value="0"
                          color="success"
                          density="compact"
                          hide-details
                          class="font-weight-black scale-90"
                        />
                      </VCard>
                    </VCol>
                    <VCol cols="12" md="4">
                      <VCard
                        variant="flat"
                        class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center"
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
                    </VCol>
                    <VCol cols="12" md="4">
                      <VCard
                        variant="flat"
                        class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center"
                      >
                        <VSwitch
                          v-model="formData.is_colombian_origin"
                          label="Origen Colombia"
                          :true-value="1"
                          :false-value="0"
                          color="primary"
                          density="compact"
                          hide-details
                          class="font-weight-black scale-90"
                        />
                      </VCard>
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
                  class="bg-white rounded-xl border shadow-sm overflow-hidden"
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
          <VWindowItem :value="2">
            <div class="d-flex flex-column gap-6">
              <!-- Jerarquía y Agrupación -->
              <div class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator primary shadow-sm" />
                  <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Jerarquía y Agrupación</span>
                </div>

                <VCard
                  variant="flat"
                  class="pa-5 bg-white rounded-xl border shadow-sm"
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
                      class="d-flex gap-2 align-center"
                    >
                      <AppTextField
                        v-model="groupInput"
                        placeholder="BUSCAR GRUPO (ID O NOMBRE)..."
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        class="bg-white rounded-lg font-weight-black flex-grow-1"
                        @keydown.enter.prevent="assignGroup"
                      />
                      <VBtn
                        color="primary"
                        @click="assignGroup"
                        variant="flat"
                        height="44"
                        class="font-weight-black px-6 shadow-primary rounded-lg text-button uppercase"
                      >
                        Asignar
                      </VBtn>
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
                  class="bg-white rounded-xl border shadow-sm overflow-hidden"
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
      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
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
              height="50"
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
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon
                :icon="isNewProduct ? 'tabler-circle-check' : 'tabler-device-floppy'"
                size="18"
                class="me-2"
              />
              {{ isNewProduct ? "Crear Producto" : "Guardar Cambios" }}
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
              Nuevo Laboratorio
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
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre Oficial del Laboratorio</span>
          <VTextField
            v-model="newLabName"
            placeholder="EJ: LABORATORIOS GOVIMAR"
            variant="outlined"
            density="comfortable"
            autofocus
            hide-details="auto"
            class="rounded-lg font-weight-black"
            @keydown.enter="createLaboratory"
          />
        </VCard>
      </VCardText>

      <VCardActions class="pa-4 bg-white border-t px-6">
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
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
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

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.table-standard :deep(.v-data-table-header) {
  background-color: #f1f5f9;
}

.table-standard :deep(.v-data-table-header th) {
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0 !important;
}

.table-standard :deep(td) {
  padding-block: 12px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.05) !important;
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
</style>
