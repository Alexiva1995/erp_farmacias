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
    <VCard v-if="formData" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon :icon="isNewProduct ? 'tabler-circle-plus' : 'tabler-edit'" color="primary" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ isNewProduct ? "Añadir Nuevo Producto" : "Editar Producto" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                {{ isNewProduct ? 'Registro en cola' : `ID: #${formData.id}` }}
              </span>
              <VChip v-if="assignedGroupName" color="white" size="x-small" variant="tonal" class="text-super-xs font-weight-bold">
                Grupo: {{ assignedGroupName }}
              </VChip>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="closeDialog" class="rounded-lg">
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto" style="block-size: calc(100vh - 200px);">
        <VForm @submit.prevent="submitForm" class="d-flex flex-column gap-6">
          
          <!-- Seccion: Datos Generales -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información Básica</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow>
                <VCol cols="12" md="12">
                  <div class="d-flex flex-column h-100">
                    <VLabel class="mb-1 text-body-2 text-wrap" style="line-height: 15px;">Imagen del Producto</VLabel>
                    <VFileInput
                      v-model="imageFile"
                      accept="image/*"
                      variant="outlined"
                      prepend-inner-icon="tabler-camera"
                      clearable
                      :error-messages="formErrors.photo_url"
                      density="comfortable"
                      class="shadow-sm"
                      hide-details="auto"
                    />
                  </div>
                </VCol>
                <VCol
                  v-if="imagePreviewUrl"
                  cols="12"
                  class="d-flex align-center justify-center mt-2"
                >
                  <VImg
                    :src="imagePreviewUrl"
                    :width="200"
                    aspect-ratio="1"
                    class="border-dashed-2 rounded-lg"
                  />
                </VCol>
              </VRow>

              <VRow class="mt-2">
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="formData.name"
                    label="Nombre"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="formErrors.name"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="formData.active_ingredient"
                    label="Principio Activo"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="formErrors.active_ingredient"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
              </VRow>

              <VRow>
                <VCol cols="12" md="6">
                  <AppSelect
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
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12" md="6">
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
                    hide-details="auto"
                  />
                </VCol>
              </VRow>

              <VRow>
                <VCol cols="12" md="6">
                  <AppSelect
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
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppSelect
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
                    hide-details="auto"
                  />
                </VCol>
              </VRow>

              <VRow>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="formData.barcode"
                    label="Código de Barra"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="formErrors.barcode"
                    prepend-inner-icon="tabler-barcode"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                  class="d-flex align-center flex-wrap gap-x-2"
                >
                  <VCheckbox
                    v-model="formData.iva"
                    label="Aplica IVA"
                    :true-value="1"
                    :false-value="0"
                    hide-details
                    density="compact"
                  />
                  <VCheckbox
                    v-model="formData.psychotropic"
                    label="Psicotrópico"
                    :true-value="1"
                    :false-value="0"
                    hide-details
                    density="compact"
                  />
                  <VCheckbox
                    v-model="formData.is_colombian_origin"
                    label="P.Colombia"
                    :true-value="1"
                    :false-value="0"
                    hide-details
                    density="compact"
                  />
                </VCol>
              </VRow>
            </VCard>
          </section>

          <!-- Seccion: Grupo de Productos -->
          <template v-if="!isNewProduct">
            <section>
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator secondary shadow-sm"></div>
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Grupo de Productos</span>
              </div>

              <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
                <div
                  v-if="assignedGroupName"
                  class="d-flex align-center gap-4 mb-4"
                >
                  <span class="text-xs font-weight-bold uppercase text-disabled">Grupo Asignado:</span>
                  <VChip color="primary" variant="flat" size="small" class="font-weight-black">{{ assignedGroupName }}</VChip>
                </div>

                <VRow align="center">
                  <VCol cols="12" md="9">
                    <AppTextField
                      v-model="groupInput"
                      label="Nombre o ID del Grupo a Asignar"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                      @keydown.enter.prevent="assignGroup"
                      class="shadow-sm"
                    />
                  </VCol>
                  <VCol cols="12" md="3" class="d-flex align-center mt-md-4">
                    <VBtn
                      color="primary"
                      @click="assignGroup"
                      block
                      variant="flat"
                      height="44"
                      class="shadow-primary font-weight-black"
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
                  class="mt-6 rounded-lg border shadow-sm"
                  no-data-text="Ningún otro producto en este grupo."
                >
                  <template #item.lots="{ item }">
                    <VChip size="x-small" color="info" variant="tonal" class="font-weight-black">
                      {{ calculateStock(item) }} UNID
                    </VChip>
                  </template>
                </VDataTable>
              </VCard>
            </section>
          </template>

          <!-- Seccion: Lotes del Producto -->
          <template
            v-if="!isNewProduct && formData.lots && formData.lots.length > 0"
          >
            <section>
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator success shadow-sm"></div>
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Lotes en Stock</span>
              </div>

              <VCard variant="flat" class="overflow-hidden bg-white rounded-lg elevation-1 border">
                <VDataTable
                  :headers="lotHeaders"
                  :items="formData.lots || []"
                  density="compact"
                  class="premium-table"
                  no-data-text="Este producto no tiene lotes registrados."
                >
                  <template #item.quantity="{ item }">
                    <span class="font-weight-black text-primary">{{ Number(item.quantity) || 0 }}</span>
                  </template>
                  <template #item.expiration_date="{ item }">
                    <VChip size="x-small" variant="tonal" :color="new Date(item.expiration_date) < new Date() ? 'error' : 'secondary'" class="font-weight-bold">
                      {{ formatDate(item.expiration_date) }}
                    </VChip>
                  </template>
                </VDataTable>
              </VCard>
            </section>
          </template>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
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
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon icon="tabler-device-floppy" class="me-2" />
              Guardar Cambios
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
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

.header-indicator.success {
  background-color: rgb(var(--v-theme-success));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.border-dashed-2 {
  border: 1.5px dashed rgba(var(--v-border-color), 0.15) !important;
}

.premium-table :deep(.v-data-table-header) {
  background-color: #f8fafc !important;
}

.premium-table :deep(th) {
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
