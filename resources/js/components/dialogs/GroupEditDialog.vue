<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  group: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({});
const formErrors = ref({});

const associatedProducts = ref([]);
const isLoadingProducts = ref(false);
const isUnassigningProduct = ref(null);

const isNewGroup = computed(() => !formData.value.id);

async function fetchAssociatedProducts(groupId) {
  if (!groupId) return;
  isLoadingProducts.value = true;
  try {
    const response = await axios.get("/products", {
      params: { groupId: groupId, itemsPerPage: -1 },
    });
    associatedProducts.value = response.data.data;
  } catch (error) {
    console.error("Error al cargar los productos del grupo:", error);
    toast.error("No se pudieron cargar los productos del grupo.");
  } finally {
    isLoadingProducts.value = false;
  }
}

async function unassignProduct(product) {
  isUnassigningProduct.value = product.id;
  try {
    await axios.delete(`/products/${product.id}/unassign-group`);

    associatedProducts.value = associatedProducts.value.filter(
      (p) => p.id !== product.id
    );

    toast.success(`"${product.name}" ha sido quitado del grupo.`);
  } catch (error) {
    console.error("Error al desvincular el producto:", error);
    toast.error("No se pudo quitar el producto del grupo.");
  } finally {
    isUnassigningProduct.value = null;
  }
}

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible && !isNewGroup.value) {
      fetchAssociatedProducts(formData.value.id);
    } else if (!isVisible) {
      associatedProducts.value = [];
    }
  }
);

watch(
  () => props.group,
  (newGroup) => {
    formData.value =
      newGroup && Object.keys(newGroup).length > 0
        ? { ...newGroup }
        : { name: "" };
    formErrors.value = {};
  },
  { deep: true, immediate: true }
);

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clearErrors");
};

const submitForm = () => {
  emit("clearErrors");
  const payload = {
    id: formData.value.id,
    name: formData.value.name,
  };
  emit("save", payload);
};

const productHeaders = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true, width: "40%" },
  { title: "Laboratorio", key: "laboratory.name", sortable: true },
  {
    title: "Stock",
    key: "valid_stock",
    visible: true,
    sortable: true,
    value: (item) => {
      return item.stock_calculado;
    },
  },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Acción", key: "actions", sortable: false, align: "end" },
];

const nextExpirationDate = (product) => {
  if (
    !product.lots ||
    !Array.isArray(product.lots) ||
    product.lots.length === 0
  )
    return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return product.ultima_fecha_vencimiento || "N/A";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split("T")[0];
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
      <VCardTitle class="d-flex align-center pa-6">
        <span class="text-h5 font-weight-bold">
          {{ isNewGroup ? "Añadir Nuevo Grupo" : "Editar Grupo" }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-6" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <div class="mb-6">
            <p class="text-h6 font-weight-medium mb-1">Información del Grupo</p>
            <p class="text-body-2 text-medium-emphasis">Nombre del grupo de productos</p>
          </div>
          <VRow class="mb-4">
            <VCol cols="12">
              <VTextField
                v-model="formData.name"
                label="Nombre del Grupo"
                variant="outlined"
                density="comfortable"
                :error-messages="formErrors.name"
              />
            </VCol>
          </VRow>
        </VForm>

        <!-- ✅ NUEVA SECCIÓN: LISTA DE PRODUCTOS EN EL GRUPO -->
        <template v-if="!isNewGroup">
          <VDivider class="my-8" />

          <div class="mb-4">
            <p class="text-h6 font-weight-medium mb-1">Productos en este Grupo</p>
            <p class="text-body-2 text-medium-emphasis">Lista de productos asociados al grupo</p>
          </div>

          <VDataTable
            :headers="productHeaders"
            :items="associatedProducts"
            :loading="isLoadingProducts"
            density="comfortable"
            no-data-text="No hay productos asignados a este grupo."
            class="rounded-lg"
          >
            <template #item.id="{ item }">
              <span class="font-weight-medium">{{ item.id }}</span>
            </template>

            <template #item.name="{ item }">
              <div class="d-flex align-center gap-x-4">
                <VAvatar
                  v-if="item.photo_url"
                  size="38"
                  variant="tonal"
                  rounded
                  :image="item.photo_url"
                />
                <div class="d-flex flex-column">
                  <span
                    class="text-body-1 font-weight-medium text-high-emphasis"
                    :class="{ 
                      'text-warning font-weight-bold': item.psychotropic == 1 || item.psychotropic === true
                    }"
                  >
                    {{ item.name.toUpperCase() }}
                    <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
                    <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
                  </span>
                  <span class="text-sm text-disabled">{{
                    item.active_ingredient
                  }}</span>
                </div>
              </div>
            </template>

            <template #item["laboratory.name"]="{ item }">
              <span>{{ item.laboratory?.name || "—" }}</span>
            </template>

            <template #item.valid_stock="{ item }">
              <span class="font-weight-medium">{{ item.stock_calculado || 0 }}</span>
            </template>

            <template #item.next_expiration="{ item }">
              <span>{{ nextExpirationDate(item) }}</span>
            </template>

            <template #item.actions="{ item }">
              <IconBtn
                color="error"
                :disabled="isUnassigningProduct === item.id"
                @click="unassignProduct(item)"
              >
                <VProgressCircular
                  v-if="isUnassigningProduct === item.id"
                  indeterminate
                  size="20"
                />
                <VIcon v-else icon="tabler-trash" />
              </IconBtn>
            </template>
          </VDataTable>
        </template>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
          size="large"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
          size="large"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
