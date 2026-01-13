<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
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

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({});
const imageFile = ref(null);
const formErrors = ref({});

const groupInput = ref(null);

const isNewProduct = computed(() => !formData.value.id);

const assignedGroupName = computed(() => {
  return formData.value.group ? formData.value.group.name : null;
});

const productsInGroup = computed(() => {
  if (!formData.value.group_id) return [];
  return props.allProducts.filter(
    (p) => p.group_id === formData.value.group_id && p.id !== formData.value.id
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

const calculateStock = (product) => {
  if (!product.lots || !Array.isArray(product.lots)) return 0;
  return product.lots.reduce((sum, lot) => sum + Number(lot.quantity || 0), 0);
};

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true }
);

watch(
  () => props.product,
  (newProduct) => {
    if (newProduct && Object.keys(newProduct).length > 0) {
      const clonedProduct = JSON.parse(JSON.stringify(newProduct));
      // Normalizar valores booleanos/números para los checkboxes
      clonedProduct.iva = clonedProduct.iva ? 1 : 0;
      clonedProduct.psychotropic = clonedProduct.psychotropic ? 1 : 0;
      clonedProduct.is_colombian_origin = clonedProduct.is_colombian_origin ? 1 : 0;
      formData.value = clonedProduct;
    } else {
      formData.value = {
        name: "",
        active_ingredient: "",
        laboratory_id: null,
        unit_cost: 0,
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
  { deep: true, immediate: true }
);

const lotHeaders = [
  { title: "Nombre", key: "lot_number", sortable: false },
  { title: "Ubicación", key: "location", sortable: false },
  { title: "Stock", key: "quantity", sortable: false },
  { title: "Exp.", key: "expiration_date", sortable: false },
];

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

  payload.append("sale_price", 0);

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
      <VCardTitle class="d-flex align-center pa-4 pb-3">
        <span class="text-h5 font-weight-bold">{{
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
        <VBtn icon variant="text" @click="closeDialog">
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
              />
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
            <VCol cols="12" md="4">
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
            <VCol cols="12" md="4" class="d-flex align-center gap-2">
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
                <span class="text-body-2 font-weight-medium">Grupo Asignado:</span>
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
                <VCol cols="12" md="9">
                  <VTextField
                    v-model="groupInput"
                    label="Nombre o ID del Grupo a Asignar"
                    variant="outlined"
                    density="compact"
                    hide-details
                    @keydown.enter.prevent="assignGroup"
                  />
                </VCol>
                <VCol cols="12" md="3" class="d-flex align-end">
                  <VBtn 
                    color="primary" 
                    @click="assignGroup" 
                    block 
                    variant="flat"
                    style="height: 40px;"
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

      <VCardActions class="pa-3">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 mr-2"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
