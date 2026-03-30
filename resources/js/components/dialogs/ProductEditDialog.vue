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
    <VCard v-if="formData" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Compacta Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar
              color="white"
              variant="flat"
              size="32"
              class="me-3 elevation-1"
            >
              <VIcon
                :icon="isNewProduct ? 'tabler-circle-plus' : 'tabler-edit'"
                color="primary"
                size="18"
              />
            </VAvatar>
            <div>
              <h2
                class="text-subtitle-2 font-weight-black text-white leading-tight mb-0"
              >
                {{ isNewProduct ? "Añadir Nuevo Producto" : "Editar Producto" }}
              </h2>
              <span
                class="text-super-xs text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem"
              >
                {{ isNewProduct ? "Registro" : "Modificación" }} de producto
              </span>
            </div>
          </div>

          <VChip
            v-if="assignedGroupName"
            class="ml-4 font-weight-black"
            color="white"
            variant="tonal"
            size="x-small"
          >
            GRUPO: {{ assignedGroupName }}
          </VChip>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="x-small"
            @click="closeDialog"
          >
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0 bg-light">
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

        <VWindow
          v-model="activeTab"
          class="pa-4"
          style="max-block-size: 65vh; overflow-y: auto"
        >
          <!-- Pestaña General -->
          <VWindowItem :value="0">
            <VCard
              variant="flat"
              class="border pa-4 pa-sm-5 mb-3 bg-white elevation-1 rounded-lg"
            >
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator primary"></div>
                <span
                  class="text-xs font-weight-black text-primary uppercase letter-spacing-1"
                  >Información Básica</span
                >
              </div>

              <VForm @submit.prevent="submitForm">
                <VRow dense>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="formData.name"
                      label="Nombre del Producto"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="formErrors.name"
                      placeholder="Ej: Ibuprofeno 400mg"
                      class="shadow-sm"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="formData.active_ingredient"
                      label="Principio Activo"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="formErrors.active_ingredient"
                      placeholder="Ej: Ibuprofeno"
                      class="shadow-sm"
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
                      density="comfortable"
                      clearable
                      :error-messages="formErrors.laboratory_id"
                      class="shadow-sm"
                    >
                      <template #append-inner>
                        <VBtn
                          icon="tabler-plus"
                          variant="tonal"
                          color="primary"
                          size="x-small"
                          class="rounded"
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
                      density="comfortable"
                      clearable
                      :error-messages="formErrors.origin_id"
                      class="shadow-sm"
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
                      density="comfortable"
                      clearable
                      :error-messages="formErrors.category_id"
                      class="shadow-sm"
                    />
                  </VCol>

                  <VCol cols="12" md="12" class="mt-2">
                    <div class="d-flex align-center gap-2 mb-2">
                      <VIcon icon="tabler-settings" size="18" color="primary" />
                      <span class="text-xs font-weight-black text-primary uppercase"
                        >Identificación y Multimedia</span
                      >
                    </div>
                  </VCol>

                  <VCol cols="12" md="6">
                    <AppTextField
                      v-model="formData.barcode"
                      label="Código de Barras"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="formErrors.barcode"
                      prepend-inner-icon="tabler-barcode"
                      class="shadow-sm"
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
                      density="comfortable"
                      class="shadow-sm"
                    />
                  </VCol>
                  <VCol
                    v-if="imagePreviewUrl"
                    cols="12"
                    class="d-flex justify-center mt-2"
                  >
                    <VImg
                      :src="imagePreviewUrl"
                      max-width="200"
                      height="200"
                      cover
                      class="border-dashed-2 rounded-lg elevation-1 bg-white"
                    />
                  </VCol>
                </VRow>
              </VForm>
            </VCard>
          </VWindowItem>

          <!-- Pestaña Inventario -->
          <VWindowItem :value="1">
            <VCard
              variant="flat"
              class="border pa-4 pa-sm-5 mb-4 bg-white elevation-1 rounded-lg"
            >
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator primary"></div>
                <span
                  class="text-xs font-weight-black text-primary uppercase letter-spacing-1"
                  >Configuración Logística</span
                >
              </div>

              <VRow dense>
                <VCol cols="12" md="4">
                  <VCard
                    variant="flat"
                    border
                    class="pa-3 bg-light rounded-lg d-flex align-center"
                  >
                    <VSwitch
                      v-model="formData.iva"
                      label="Aplica IVA (G)"
                      :true-value="1"
                      :false-value="0"
                      color="success"
                      density="compact"
                      hide-details
                      class="font-weight-bold"
                    />
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard
                    variant="flat"
                    border
                    class="pa-3 bg-light rounded-lg d-flex align-center"
                  >
                    <VSwitch
                      v-model="formData.psychotropic"
                      label="Psicotrópico"
                      :true-value="1"
                      :false-value="0"
                      color="warning"
                      density="compact"
                      hide-details
                      class="font-weight-bold"
                    />
                  </VCard>
                </VCol>
                <VCol cols="12" md="4">
                  <VCard
                    variant="flat"
                    border
                    class="pa-3 bg-light rounded-lg d-flex align-center"
                  >
                    <VSwitch
                      v-model="formData.is_colombian_origin"
                      label="Origen Colombia"
                      :true-value="1"
                      :false-value="0"
                      color="primary"
                      density="compact"
                      hide-details
                      class="font-weight-bold"
                    />
                  </VCard>
                </VCol>

                <VCol v-if="!isNewProduct" cols="12" md="6" class="mt-4">
                  <AppTextField
                    v-model="formData.unit_cost"
                    label="Costo de Compra"
                    type="number"
                    prefix="$"
                    variant="outlined"
                    density="comfortable"
                    :readonly="!authStore.isAdmin"
                    :error-messages="formErrors.unit_cost"
                    class="shadow-sm"
                  />
                </VCol>
                <VCol v-if="!isNewProduct" cols="12" md="6" class="mt-4">
                  <AppTextField
                    v-model="formData.sale_price"
                    label="Precio de Venta"
                    type="number"
                    prefix="$"
                    variant="outlined"
                    density="comfortable"
                    :readonly="authStore.isVendedor || authStore.isSupervisor"
                    :error-messages="formErrors.sale_price"
                    class="shadow-sm"
                  />
                </VCol>
              </VRow>
            </VCard>

            <VCard
              v-if="!isNewProduct && formData.lots?.length > 0"
              variant="flat"
              class="border pa-4 bg-white elevation-1 rounded-lg"
            >
              <div class="d-flex align-center justify-space-between mb-4">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator secondary"></div>
                  <span
                    class="text-xs font-weight-black text-secondary uppercase letter-spacing-1"
                    >Lotes y Ubicación</span
                  >
                </div>
                <VChip size="x-small" color="secondary" variant="flat" class="font-weight-black">
                  {{ formData.lots.length }} LOTES ACTIVOS
                </VChip>
              </div>

              <!-- Desktop Table -->
              <div class="d-none d-sm-block">
                <VDataTable
                  :headers="lotHeaders"
                  :items="formData.lots"
                  density="compact"
                  class="border rounded shadow-sm overflow-hidden"
                  hide-default-footer
                >
                  <template #item.quantity="{ item }">
                    <VChip
                      size="x-small"
                      :color="item.quantity > 0 ? 'success' : 'error'"
                      class="font-weight-black"
                    >
                      {{ item.quantity }}
                    </VChip>
                  </template>
                  <template #item.expiration_date="{ item }">
                    <span class="text-xs font-weight-bold">{{ formatDate(item.expiration_date) }}</span>
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
                    class="lot-mobile-card border mb-1 bg-light"
                  >
                    <div class="pa-3">
                      <div
                        class="d-flex justify-space-between align-center mb-2"
                      >
                        <span class="text-xs font-weight-black text-primary uppercase"
                          >LOTE: {{ item.lot_number }}</span
                        >
                        <VChip
                          size="x-small"
                          :color="item.quantity > 0 ? 'success' : 'error'"
                          class="font-weight-black"
                        >
                          {{ item.quantity }} <small class="ml-1">UNDS</small>
                        </VChip>
                      </div>
                      <div
                        class="d-flex justify-space-between text-super-xs text-medium-emphasis"
                      >
                        <span
                          ><VIcon
                            icon="tabler-map-pin"
                            size="12"
                            class="me-1"
                          />{{ item.location || "S/U" }}</span
                        >
                        <span
                          ><VIcon
                            icon="tabler-calendar"
                            size="12"
                            class="me-1"
                          />{{ formatDate(item.expiration_date) }}</span
                        >
                      </div>
                    </div>
                  </VCard>
                </div>
              </div>
            </VCard>
          </VWindowItem>

          <!-- Pestaña Relaciones -->
          <VWindowItem :value="2">
            <VCard
              variant="flat"
              class="border pa-4 pa-sm-5 mb-4 bg-white elevation-1 rounded-lg"
            >
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator primary"></div>
                <span
                  class="text-xs font-weight-black text-primary uppercase letter-spacing-1"
                  >Jerarquía y Agrupación</span
                >
              </div>

              <VCard variant="flat" border class="pa-4 bg-light rounded-lg">
                <div class="d-flex align-center gap-2 mb-3">
                  <VIcon icon="tabler-link" size="18" color="primary" />
                  <span class="text-xs font-weight-black text-primary uppercase"
                    >Asignación de Grupo</span
                  >
                </div>
                <div class="d-flex gap-2 align-center">
                  <AppTextField
                    v-model="groupInput"
                    label="Buscar Grupo (ID o Nombre)"
                    variant="outlined"
                    density="comfortable"
                    hide-details
                    class="bg-white shadow-sm"
                    @keydown.enter.prevent="assignGroup"
                  />
                  <VBtn
                    color="primary"
                    @click="assignGroup"
                    variant="flat"
                    height="44"
                    class="font-weight-black px-6 shadow-primary rounded-lg"
                  >
                    Asignar
                  </VBtn>
                </div>

                <div
                  v-if="assignedGroupName"
                  class="mt-3 d-flex align-center"
                >
                  <VChip
                    color="primary"
                    variant="flat"
                    label
                    closable
                    class="font-weight-black px-4"
                    @click:close="removeGroup"
                  >
                    <VIcon icon="tabler-hierarchy" size="16" class="me-2" />
                    GRUPO: {{ assignedGroupName }}
                  </VChip>
                </div>
              </VCard>
            </VCard>

            <VCard
              v-if="productsInGroup.length > 0"
              variant="flat"
              class="border pa-4 bg-white elevation-1 rounded-lg"
            >
              <div class="d-flex align-center justify-space-between mb-4">
                <div class="d-flex align-center gap-2">
                  <div class="header-indicator secondary"></div>
                  <span
                    class="text-xs font-weight-black text-secondary uppercase letter-spacing-1"
                    >Productos del mismo grupo</span
                  >
                </div>
                <VChip size="x-small" color="secondary" variant="flat" class="font-weight-black">
                  {{ productsInGroup.length }} RELACIONADOS
                </VChip>
              </div>

              <!-- Desktop Table -->
              <div class="d-none d-sm-block">
                <VDataTable
                  :headers="groupProductsHeaders"
                  :items="productsInGroup"
                  density="compact"
                  class="border rounded shadow-sm overflow-hidden"
                >
                  <template #item.lots="{ item }">
                    <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black">
                      STK: {{ calculateStock(item) }}
                    </VChip>
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
                    class="border mb-1 bg-light rounded-lg shadow-sm"
                  >
                    <div class="pa-3">
                      <div
                        class="d-flex align-center justify-space-between mb-1"
                      >
                        <h4
                          class="text-xs font-weight-black text-uppercase truncate-2-lines flex-grow-1 mr-2"
                        >
                          <span class="text-primary mr-1"
                            >#{{ item.id }}</span
                          >
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
                      <div class="text-super-xs text-disabled font-weight-bold uppercase">
                        {{ item.laboratory?.name || "S/L" }}
                      </div>
                    </div>
                  </VCard>
                </div>
              </div>
            </VCard>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
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
              size="large"
              block
              height="50"
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
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #173b22 100%
  );
}

.bg-light {
  background-color: #f8fafc !important;
}

.detail-dialog-card {
  border-radius: 16px !important;
}

.lot-mobile-card {
  border-radius: 8px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1.5px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
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
