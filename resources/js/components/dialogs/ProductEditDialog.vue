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

      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <VRow dense class="mb-2">
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.name"
                label="Nombre"
                variant="outlined"
                density="compact"
                :error-messages="formErrors.name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.active_ingredient"
                label="Principio Activo"
                variant="outlined"
                density="compact"
                :error-messages="formErrors.active_ingredient"
              />
            </VCol>
          </VRow>
          <VRow dense class="mb-2">
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
                <template #append>
                  <VBtn
                    icon="tabler-plus"
                    variant="tonal"
                    color="primary"
                    size="32"
                    @click="isLabDialogVisible = true"
                    title="Crear nuevo laboratorio"
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
          </VRow>
          <VRow dense class="mb-2">
            <VCol cols="12" md="4">
              <VTextField
                v-model="formData.barcode"
                label="Código de Barra"
                variant="outlined"
                density="compact"
                :error-messages="formErrors.barcode"
              />
            </VCol>
            <VCol cols="12" md="8" class="d-flex align-center gap-2">
              <VCheckbox
                v-model="formData.iva"
                label="IVA"
                :true-value="1"
                :false-value="0"
                density="compact"
                hide-details
              />
              <VCheckbox
                v-model="formData.psychotropic"
                label="Psicotrópico"
                :true-value="1"
                :false-value="0"
                density="compact"
                hide-details
              />
              <VCheckbox
                v-model="formData.is_colombian_origin"
                label="Colombia"
                :true-value="1"
                :false-value="0"
                density="compact"
                hide-details
              />
            </VCol>
          </VRow>

          <!-- Campos de costo y precio solo para edición -->
          <VRow v-if="!isNewProduct" dense class="mb-2">
            <VCol cols="12" md="6">
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
            <VCol cols="12" md="6">
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
          </VRow>
          <VRow dense class="mb-3">
            <VCol cols="12" md="8">
              <VFileInput
                v-model="imageFile"
                label="Imagen del Producto"
                accept="image/*"
                variant="outlined"
                prepend-icon="tabler-camera"
                clearable
                :error-messages="formErrors.photo_url"
                density="compact"
              />
            </VCol>
            <VCol
              v-if="imagePreviewUrl"
              cols="12"
              md="4"
              class="d-flex align-center justify-center"
            >
              <VImg
                :src="imagePreviewUrl"
                :width="120"
                aspect-ratio="1"
                class="border rounded"
              />
            </VCol>
          </VRow>

          <template v-if="!isNewProduct">
            <VDivider class="my-3" />
            <div class="mb-2">
              <p class="text-h6 font-weight-medium mb-1">Grupo de Productos</p>
            </div>
            <VSheet color="grey-100" rounded="lg" class="pa-3">
              <div
                v-if="assignedGroupName"
                class="d-flex align-center gap-2 mb-3"
              >
                <span class="text-body-2 font-weight-medium"
                  >Grupo Asignado:</span
                >
                <VChip
                  color="primary"
                  label
                  size="small"
                  closable
                  @click:close="removeGroup"
                >
                  {{ assignedGroupName }}
                </VChip>
                <VSpacer />
              </div>

              <VRow v-if="!assignedGroupName" dense class="mb-2">
                <VCol cols="12" md="9" class="d-flex align-center">
                  <VTextField
                    v-model="groupInput"
                    label="Nombre o ID del Grupo a Asignar"
                    variant="outlined"
                    density="compact"
                    hide-details
                    @keydown.enter.prevent="assignGroup"
                    style="height: 40px"
                  />
                </VCol>
                <VCol cols="12" md="3" class="d-flex align-center">
                  <VBtn
                    color="primary"
                    @click="assignGroup"
                    block
                    variant="flat"
                    style="height: 40px"
                  >
                    Asignar
                  </VBtn>
                </VCol>
              </VRow>

              <VDataTable
                v-if="productsInGroup.length > 0"
                :headers="groupProductsHeaders"
                :items="productsInGroup"
                density="compact"
                class="rounded-lg"
                no-data-text="Ningún otro producto en este grupo."
              >
                <template #item.lots="{ item }">
                  <span>{{ calculateStock(item) }}</span>
                </template>
              </VDataTable>
            </VSheet>
          </template>

          <template
            v-if="!isNewProduct && formData.lots && formData.lots.length > 0"
          >
            <VDivider class="my-3" />

            <div class="mb-2">
              <p class="text-h6 font-weight-medium mb-1">Lotes del Producto</p>
            </div>

            <VDataTable
              :headers="lotHeaders"
              :items="formData.lots || []"
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
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%"
        >
          Guardar
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
