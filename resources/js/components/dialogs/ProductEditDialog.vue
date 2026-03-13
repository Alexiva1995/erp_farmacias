<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { computed, ref, watch } from "vue";
import { formatDate, calculateStock } from "@/utils/formatters";

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
  >
    <VCard v-if="formData" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon
          :icon="isNewProduct ? 'tabler-plus' : 'tabler-edit'"
          size="24"
          color="white"
          class="me-2"
        />
        <span class="text-h5 font-weight-bold text-white">{{
          isNewProduct ? "Añadir Nuevo Producto" : "Editar Producto"
        }}</span>

        <VChip
          v-if="assignedGroupName"
          class="ml-4"
          color="primary"
          size="small"
          label
        >
          Grupo: {{ assignedGroupName }}
        </VChip>

        <VSpacer />
        <VBtn
          icon
          variant="text"
          color="white"
          size="small"
          @click="closeDialog"
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0">
        <VTabs v-model="activeTab" grow bg-color="background">
          <VTab :value="0">
            <VIcon icon="tabler-info-circle" class="me-2" />
            General
          </VTab>
          <VTab :value="1">
            <VIcon icon="tabler-database" class="me-2" />
            Inventario
          </VTab>
          <VTab :value="2">
            <VIcon icon="tabler-hierarchy-2" class="me-2" />
            Relaciones
          </VTab>
        </VTabs>

        <VWindow v-model="activeTab" class="pa-4" style="max-block-size: 65vh; overflow-y: auto;">
          <!-- Pestaña General -->
          <VWindowItem :value="0">
            <VForm @submit.prevent="submitForm">
              <VRow dense>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="formData.name"
                    label="Nombre"
                    variant="outlined"
                    density="compact"
                    :error-messages="formErrors.name"
                    placeholder="Ej: Ibuprofeno 400mg"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="formData.active_ingredient"
                    label="Principio Activo"
                    variant="outlined"
                    density="compact"
                    :error-messages="formErrors.active_ingredient"
                    placeholder="Ej: Ibuprofeno"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <VSelect
                    v-model="formData.laboratory_id"
                    label="Laboratorio"
                    :items="props.laboratories"
                    item-title="name"
                    item-value="id"
                    variant="outlined"
                    density="compact"
                    clearable
                    :error-messages="formErrors.laboratory_id"
                  >
                    <template #append-inner>
                      <VBtn
                        icon="tabler-plus"
                        variant="text"
                        color="primary"
                        size="small"
                        @click.stop="isLabDialogVisible = true"
                      />
                    </template>
                  </VSelect>
                </VCol>
                <VCol cols="12" md="4">
                  <VSelect
                    v-model="formData.origin_id"
                    label="Origen"
                    :items="props.origins"
                    item-title="name"
                    item-value="id"
                    variant="outlined"
                    density="compact"
                    clearable
                    :error-messages="formErrors.origin_id"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <VSelect
                    v-model="formData.category_id"
                    label="Categoría"
                    :items="props.categories"
                    item-title="name"
                    item-value="id"
                    variant="outlined"
                    density="compact"
                    clearable
                    :error-messages="formErrors.category_id"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField
                    v-model="formData.barcode"
                    label="Código de Barra"
                    variant="outlined"
                    density="compact"
                    :error-messages="formErrors.barcode"
                    prepend-inner-icon="tabler-barcode"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VFileInput
                    v-model="imageFile"
                    label="Imagen del Producto"
                    accept="image/*"
                    variant="outlined"
                    prepend-inner-icon="tabler-camera"
                    clearable
                    :error-messages="formErrors.photo_url"
                    density="compact"
                  />
                </VCol>
                <VCol v-if="imagePreviewUrl" cols="12" class="d-flex justify-center mt-2">
                  <VImg
                    :src="imagePreviewUrl"
                    max-width="200"
                    height="200"
                    cover
                    class="border rounded-lg"
                  />
                </VCol>
              </VRow>
            </VForm>
          </VWindowItem>

          <!-- Pestaña Inventario -->
          <VWindowItem :value="1">
            <VRow dense>
              <VCol cols="12" md="4">
                <VSwitch
                  v-model="formData.iva"
                  label="Aplica IVA (G)"
                  :true-value="1"
                  :false-value="0"
                  color="success"
                  density="compact"
                  hide-details
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSwitch
                  v-model="formData.psychotropic"
                  label="Psicotrópico"
                  :true-value="1"
                  :false-value="0"
                  color="warning"
                  density="compact"
                  hide-details
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSwitch
                  v-model="formData.is_colombian_origin"
                  label="Origen Colombia"
                  :true-value="1"
                  :false-value="0"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </VCol>

              <VCol v-if="!isNewProduct" cols="12" md="6" class="mt-4">
                <VTextField
                  v-model="formData.unit_cost"
                  label="Costo de Compra"
                  type="number"
                  prefix="$"
                  variant="outlined"
                  density="compact"
                  :readonly="!authStore.isAdmin"
                  :error-messages="formErrors.unit_cost"
                />
              </VCol>
              <VCol v-if="!isNewProduct" cols="12" md="6" class="mt-4">
                <VTextField
                  v-model="formData.sale_price"
                  label="Precio de Venta"
                  type="number"
                  prefix="$"
                  variant="outlined"
                  density="compact"
                  :readonly="authStore.isVendedor || authStore.isSupervisor"
                  :error-messages="formErrors.sale_price"
                />
              </VCol>

              <VCol v-if="!isNewProduct && formData.lots?.length > 0" cols="12" class="mt-4">
                <p class="text-subtitle-1 font-weight-bold mb-2">
                  <VIcon icon="tabler-packages" size="20" class="me-1" />
                  Lotes Registrados
                </p>
                
                <!-- Desktop Table -->
                <div class="d-none d-sm-block">
                  <VDataTable
                    :headers="lotHeaders"
                    :items="formData.lots"
                    density="compact"
                    class="border rounded"
                    hide-default-footer
                  >
                    <template #item.quantity="{ item }">
                      <VChip size="x-small" :color="item.quantity > 0 ? 'success' : 'error'">
                        {{ item.quantity }}
                      </VChip>
                    </template>
                    <template #item.expiration_date="{ item }">
                      {{ formatDate(item.expiration_date) }}
                    </template>
                  </VDataTable>
                </div>

                <!-- Mobile Cards -->
                <div class="d-block d-sm-none">
                  <div class="d-flex flex-column gap-2">
                    <VCard
                      v-for="item in formData.lots"
                      :key="item.id"
                      variant="flat"
                      class="lot-mobile-card border mb-1 bg-var-theme-background"
                    >
                      <div class="pa-3">
                        <div class="d-flex justify-space-between align-center mb-2">
                          <span class="text-xs font-weight-bold text-primary">LOTE: {{ item.lot_number }}</span>
                          <VChip size="x-small" :color="item.quantity > 0 ? 'success' : 'error'" class="font-weight-black">
                            {{ item.quantity }} <small class="ml-1">UNDS</small>
                          </VChip>
                        </div>
                        <div class="d-flex justify-space-between text-super-xs text-medium-emphasis">
                          <span><VIcon icon="tabler-map-pin" size="12" class="me-1" />{{ item.location || 'S/U' }}</span>
                          <span><VIcon icon="tabler-calendar" size="12" class="me-1" />{{ formatDate(item.expiration_date) }}</span>
                        </div>
                      </div>
                    </VCard>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Pestaña Relaciones -->
          <VWindowItem :value="2">
            <VRow dense>
              <VCol cols="12">
                <VCard variant="outlined" class="pa-4 bg-grey-lighten-4">
                  <p class="text-subtitle-2 mb-2">Asignación de Grupo</p>
                  <div class="d-flex gap-2 align-center">
                    <VTextField
                      v-model="groupInput"
                      label="Buscar Grupo (ID o Nombre)"
                      variant="outlined"
                      density="compact"
                      hide-details
                      @keydown.enter.prevent="assignGroup"
                    />
                    <VBtn color="primary" @click="assignGroup" variant="flat">
                      Asignar
                    </VBtn>
                  </div>

                  <div v-if="assignedGroupName" class="mt-3 d-flex align-center">
                    <VChip color="primary" label closable @click:close="removeGroup">
                      Grupo: {{ assignedGroupName }}
                    </VChip>
                  </div>
                </VCard>
              </VCol>

              <VCol v-if="productsInGroup.length > 0" cols="12" class="mt-4">
                <p class="text-subtitle-1 font-weight-bold mb-2">Otros productos en este grupo</p>
                
                <!-- Desktop Table -->
                <div class="d-none d-sm-block">
                  <VDataTable
                    :headers="groupProductsHeaders"
                    :items="productsInGroup"
                    density="compact"
                    class="border rounded"
                  >
                    <template #item.lots="{ item }">
                      {{ calculateStock(item) }}
                    </template>
                  </VDataTable>
                </div>

                <!-- Mobile Cards -->
                <div class="d-block d-sm-none">
                  <div class="d-flex flex-column gap-2">
                    <VCard
                      v-for="item in productsInGroup"
                      :key="item.id"
                      variant="flat"
                      class="border mb-1"
                    >
                      <div class="pa-3">
                        <div class="d-flex align-center justify-space-between mb-1">
                          <h4 class="text-xs font-weight-black text-uppercase truncate-2-lines flex-grow-1 mr-2">
                            <span class="text-primary mr-1">#{{ item.id }}</span>
                            {{ item.name }}
                          </h4>
                          <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black">
                            STK: {{ calculateStock(item) }}
                          </VChip>
                        </div>
                        <div class="text-super-xs text-disabled">
                          {{ item.laboratory?.name || 'S/L' }}
                        </div>
                      </div>
                    </VCard>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          class="px-6"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="px-8"
        >
          {{ isNewProduct ? 'Crear Producto' : 'Guardar Cambios' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Diálogo para crear Laboratorio -->
  <VDialog v-model="isLabDialogVisible" max-width="400px">
    <VCard>
      <VCardTitle class="bg-primary text-white pa-4">
        <span>Nuevo Laboratorio</span>
      </VCardTitle>
      <VCardText class="pa-4">
        <VTextField
          v-model="newLabName"
          label="Nombre del Laboratorio"
          variant="outlined"
          density="compact"
          autofocus
          @keydown.enter="createLaboratory"
          hide-details="auto"
        />
      </VCardText>
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isLabDialogVisible = false"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="createLaboratory"
          :loading="isSavingLab"
          :disabled="!newLabName.trim()"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
<style scoped>
.lot-mobile-card {
  border-radius: 8px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

/* Optimización de scroll en móvil dentro del diálogo */
@media (max-width: 600px) {
  :deep(.v-window) {
    max-block-size: 55vh !important;
  }
}
</style>
