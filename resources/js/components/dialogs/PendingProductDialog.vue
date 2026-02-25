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
  { deep: true },
);

watch(
  () => props.product,
  (newProduct) => {
    if (newProduct && Object.keys(newProduct).length > 0) {
      const clonedProduct = JSON.parse(JSON.stringify(newProduct));
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
  { deep: true, immediate: true },
);

const lotHeaders = [
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

  // Para vendedores y supervisores, el precio debe ser 0
  // Para otros usuarios, el backend calculará el precio automáticamente
  if (authStore.isVendedor || authStore.isSupervisor) {
    payload.append("sale_price", 0);
  }

  emit("save", payload);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
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

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <p class="text-h6 font-weight-medium mb-4">Datos Generales</p>
          <VRow>
            <VCol cols="12" md="8">
              <VFileInput
                v-model="imageFile"
                label="Imagen del Producto"
                accept="image/*"
                variant="outlined"
                prepend-icon="tabler-camera"
                clearable
                :error-messages="formErrors.photo_url"
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
                :width="150"
                aspect-ratio="1"
                class="border rounded"
              />
            </VCol>
          </VRow>
          <VDivider class="my-4" />
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.name"
                label="Nombre"
                variant="outlined"
                :error-messages="formErrors.name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.active_ingredient"
                label="Principio Activo"
                variant="outlined"
                :error-messages="formErrors.active_ingredient"
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.laboratory_id"
                label="Laboratorio"
                :items="props.laboratories"
                item-title="name"
                item-value="id"
                variant="outlined"
                clearable
                :error-messages="formErrors.laboratory_id"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.unit_cost"
                label="Costo de Compra"
                type="number"
                prefix="$"
                variant="outlined"
                :readonly="!authStore.isAdmin"
                :error-messages="formErrors.unit_cost"
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.origin_id"
                label="Origen"
                :items="props.origins"
                item-title="name"
                item-value="id"
                variant="outlined"
                clearable
                :error-messages="formErrors.origin_id"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="formData.category_id"
                label="Categoría"
                :items="props.categories"
                item-title="name"
                item-value="id"
                variant="outlined"
                clearable
                :error-messages="formErrors.category_id"
              />
            </VCol>
          </VRow>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="formData.barcode"
                label="Código de Barra"
                variant="outlined"
                :error-messages="formErrors.barcode"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center flex-wrap gap-x-4"
            >
              <VCheckbox
                v-model="formData.iva"
                label="Aplica IVA"
                :true-value="1"
                :false-value="0"
              />
              <VCheckbox
                v-model="formData.psychotropic"
                label="Psicotrópico"
                :true-value="1"
                :false-value="0"
              />
              <VCheckbox
                v-model="formData.is_colombian_origin"
                label="P.Colombia"
                :true-value="1"
                :false-value="0"
              />
            </VCol>
          </VRow>

          <template v-if="!isNewProduct">
            <VDivider class="my-6" />
            <!-- se removio el color => #f5f5f5 Grupo de Productos por que en modo oscuro el fondo de componente tiene fondo blanco y desentona con el modo oscuro se ve igual de todas maneras si usas el modo blanco-->
            <VSheet color="" variant="tonal" rounded="lg" class="pa-4">
              <p class="text-h6 font-weight-medium mb-4">Grupo de Productos</p>

              <div
                v-if="assignedGroupName"
                class="d-flex align-center gap-4 mb-4"
              >
                <span class="font-weight-medium">Grupo Asignado:</span>
                <VChip color="primary" label>{{ assignedGroupName }}</VChip>
                <VSpacer />
              </div>

              <VRow align="center">
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
                class="mt-4 rounded-lg"
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
            <VDivider class="my-6" />

            <!-- 1. Contenedor flexible para el título y el botón -->
            <!-- 2. Botón de "Editar Lotes" que navega a la nueva ruta -->
            <!-- <div class="d-flex align-center mb-4">
              <p class="text-h6 font-weight-medium">Lotes del Producto</p>
              <VSpacer />
                <VBtn to="/lot/list" color="primary" prepend-icon="tabler-edit">
                Editar Lotes
              </VBtn>
            </div> -->

            <VDataTable
              :headers="lotHeaders"
              :items="formData.lots || []"
              density="compact"
              no-data-text="Este producto no tiene lotes registrados."
            >
              <template #item.quantity="{ item }">
                <span>{{ Number(item.quantity) || 0 }}</span>
              </template>
              <template #item.expiration_date="{ item }">
                <span>{{ formatDate(item.expiration_date) }}</span>
              </template>

              <!-- 3. Slot de acciones eliminado -->
              <!-- <template #item.actions> ... </template> -->
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
</template>
