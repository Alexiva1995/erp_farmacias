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

// --- NUEVO ESTADO PARA PRODUCTOS DEL GRUPO ---
const associatedProducts = ref([]);
const isLoadingProducts = ref(false);
const isUnassigningProduct = ref(null); // Para el spinner de carga

const isNewGroup = computed(() => !formData.value.id);

// --- NUEVA LÓGICA ---
// Obtiene los productos asociados a este grupo desde la API
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

// Desvincula un producto de este grupo
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
    // Si el modal se hace visible Y estamos editando un grupo existente...
    if (isVisible && !isNewGroup.value) {
      // ...cargamos los productos asociados.
      fetchAssociatedProducts(formData.value.id);
    } else if (!isVisible) {
      // Limpiamos la lista cuando el modal se cierra
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
  // Solo enviamos los datos del grupo, no la lista de productos
  const payload = {
    id: formData.value.id,
    name: formData.value.name,
  };
  emit("save", payload);
};

const productHeaders = [
  { title: "Producto", key: "name", sortable: false },
  { title: "Acción", key: "actions", sortable: false, align: "end" },
];
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">
          {{ isNewGroup ? "Añadir Nuevo Grupo" : "Editar Grupo" }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VForm @submit.prevent="submitForm">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="formData.name"
                label="Nombre del Grupo"
                variant="outlined"
                :error-messages="formErrors.name"
              />
            </VCol>
          </VRow>
        </VForm>

        <!-- ✅ NUEVA SECCIÓN: LISTA DE PRODUCTOS EN EL GRUPO -->
        <template v-if="!isNewGroup">
          <VDivider class="my-6" />

          <p class="text-h6 font-weight-medium mb-4">Productos en este Grupo</p>

          <VDataTable
            :headers="productHeaders"
            :items="associatedProducts"
            :loading="isLoadingProducts"
            density="compact"
            no-data-text="No hay productos asignados a este grupo."
            class="rounded-lg"
          >
            <template #item.actions="{ item }">
              <VBtn
                size="small"
                color="error"
                variant="text"
                :disabled="isUnassigningProduct === item.id"
                @click="unassignProduct(item)"
              >
                <VProgressCircular
                  v-if="isUnassigningProduct === item.id"
                  indeterminate
                  size="20"
                  class="me-2"
                />
                Quitar
              </VBtn>
            </template>
          </VDataTable>
        </template>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
