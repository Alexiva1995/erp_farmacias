<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
  employees: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clear-errors"]);

const isEditMode = computed(() => !!props.employee?.employee_id);
const dialogTitle = computed(() =>
  isEditMode.value ? "Editar Productos Asignados" : "Asignar Productos",
);

const formData = ref({
  employee_id: null,
  products: [],
  new_product_id: null,
});

const editingProduct = ref(null);
const tempProductId = ref(null);

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      if (isEditMode.value) {
        // Modo edición: cargar datos del empleado
        formData.value = {
          employee_id: props.employee.employee_id,
          products: props.employee.products
            ? JSON.parse(JSON.stringify(props.employee.products))
            : [],
          new_product_id: null,
        };
      } else {
        // Modo creación: limpiar formulario
        formData.value = {
          employee_id: null,
          products: [],
          new_product_id: null,
        };
      }
      editingProduct.value = null;
      tempProductId.value = null;
    }
  },
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
  editingProduct.value = null;
  tempProductId.value = null;
};

const handleAddProduct = () => {
  if (!formData.value.new_product_id) return;

  const product = props.products.find(
    (prod) => prod.id === formData.value.new_product_id,
  );

  if (product) {
    const exists = formData.value.products.some(
      (prod) => prod.id === product.id,
    );

    if (!exists) {
      formData.value.products.push({
        id: product.id,
        name: product.name,
      });
      formData.value.new_product_id = null;
    }
  }
};

const handleRemoveProduct = (productId) => {
  formData.value.products = formData.value.products.filter(
    (prod) => prod.id !== productId,
  );
};

const handleEditProduct = (product) => {
  editingProduct.value = product.id;
  tempProductId.value = product.id;
};

const handleSaveEdit = (oldProductId) => {
  if (!tempProductId.value) return;

  const newProd = props.products.find(
    (prod) => prod.id === tempProductId.value,
  );

  if (newProd) {
    const index = formData.value.products.findIndex(
      (prod) => prod.id === oldProductId,
    );

    if (index !== -1) {
      // Crear nuevo array con el producto reemplazado
      const updatedProds = [...formData.value.products];
      updatedProds[index] = {
        id: newProd.id,
        name: newProd.name,
      };
      formData.value.products = updatedProds;
    }
  }

  editingProduct.value = null;
  tempProductId.value = null;
};

const handleCancelEdit = () => {
  editingProduct.value = null;
  tempProductId.value = null;
};

const handleSubmit = () => {
  if (!formData.value.employee_id) return;

  const dataToSend = {
    employee_id: formData.value.employee_id,
    product_ids: formData.value.products.map((prod) => prod.id),
  };

  console.log("Datos preparados para enviar:", dataToSend);
  emit("save", dataToSend);
};

const availableProducts = computed(() => {
  return props.products.filter(
    (prod) => !formData.value.products.some((p) => p.id === prod.id),
  );
});

// Computed para formatear productos con ID
const productsWithIdLabel = computed(() => {
  return props.products.map((product) => ({
    ...product,
    displayLabel: `${product.id} - ${product.name}`,
  }));
});

const availableProductsWithId = computed(() => {
  return availableProducts.value.map((product) => ({
    ...product,
    displayLabel: `${product.id} - ${product.name}`,
  }));
});

const getProductColor = (index) => {
  const colors = [
    "success",
    "info",
    "warning",
    "secondary",
    "primary",
    "error",
  ];
  return colors[index % colors.length];
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="700"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard v-if="formData" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon
          :icon="isEditMode ? 'tabler-edit' : 'tabler-plus'"
          size="24"
          color="white"
          class="me-2"
        />
        <span class="text-h5 font-weight-bold text-white">{{
          dialogTitle
        }}</span>

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
        <VForm @submit.prevent="handleSubmit">
          <!-- Select de Empleado -->
          <VRow dense class="mb-2">
            <VCol cols="12">
              <VSelect
                v-model="formData.employee_id"
                :items="props.employees"
                :disabled="isEditMode"
                label="Empleado *"
                variant="outlined"
                density="compact"
                placeholder="Selecciona un empleado"
                :error-messages="props.errors.employee_id"
                clearable
                @update:model-value="emit('clear-errors')"
              >
                <template #selection="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VAvatar size="24" color="primary" variant="tonal">
                      <span class="text-xs">
                        {{
                          item.title
                            .split(" ")
                            .map((n) => n[0])
                            .join("")
                            .substring(0, 2)
                        }}
                      </span>
                    </VAvatar>
                    <span>{{ item.title }}</span>
                  </div>
                </template>
              </VSelect>
            </VCol>
          </VRow>

          <!-- Agregar Nuevo Producto -->
          <VRow dense class="mb-2">
            <VCol cols="12">
              <div class="d-flex gap-2">
                <VAutocomplete
                  v-model="formData.new_product_id"
                  :items="availableProductsWithId"
                  item-title="displayLabel"
                  item-value="id"
                  label="Agregar Producto"
                  variant="outlined"
                  density="compact"
                  placeholder="Busca un producto"
                  :disabled="!formData.employee_id"
                  clearable
                  class="flex-grow-1"
                >
                  <template #item="{ props: itemProps, item }">
                    <VListItem v-bind="itemProps">
                      <VListItemTitle>
                        {{ item.raw.name }}
                      </VListItemTitle>
                    </VListItem>
                  </template>
                </VAutocomplete>
                <VBtn
                  color="success"
                  variant="flat"
                  size="default"
                  :disabled="!formData.new_product_id || !formData.employee_id"
                  @click="handleAddProduct"
                  style="height: 40px"
                >
                  <VIcon icon="tabler-plus" />
                </VBtn>
              </div>
            </VCol>
          </VRow>

          <!-- Lista de Productos Asignados -->
          <VRow dense class="mt-2">
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-3">
                <span class="text-subtitle-1 font-weight-medium">
                  Productos Asignados
                </span>
                <VChip
                  :color="formData.products.length > 0 ? 'success' : 'default'"
                  size="small"
                  variant="tonal"
                  label
                >
                  {{ formData.products.length }}
                </VChip>
              </div>

              <!-- Mensaje cuando no hay productos -->
              <VAlert
                v-if="formData.products.length === 0"
                type="info"
                variant="tonal"
                rounded="lg"
                class="mb-0"
              >
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-info-circle" />
                  <span>No hay productos asignados</span>
                </div>
              </VAlert>

              <!-- Tabla de Productos -->
              <VCard v-else variant="outlined" class="rounded-lg border">
                <VList class="pa-0">
                  <template
                    v-for="(product, index) in formData.products"
                    :key="`prod-${product.id}-${index}`"
                  >
                    <VListItem class="px-4 py-2">
                      <template #prepend>
                        <VAvatar
                          :color="getProductColor(index)"
                          variant="tonal"
                          size="32"
                        >
                          <VIcon icon="tabler-pill" size="18" />
                        </VAvatar>
                      </template>

                      <VListItemTitle>
                        <!-- Modo normal: mostrar ID y nombre -->
                        <div
                          v-if="editingProduct !== product.id"
                          class="d-flex align-center gap-2"
                        >
                          <VChip
                            size="small"
                            color="primary"
                            variant="tonal"
                            label
                          >
                            {{ product.id }}
                          </VChip>
                          <span class="text-body-2 font-weight-medium">
                            {{ product.name }}
                          </span>
                        </div>

                        <!-- Modo edición: mostrar select con ID -->
                        <VAutocomplete
                          v-else
                          v-model="tempProductId"
                          :items="productsWithIdLabel"
                          item-title="displayLabel"
                          item-value="id"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="my-1"
                        >
                          <template #item="{ props: itemProps, item }">
                            <VListItem v-bind="itemProps">
                              <VListItemTitle>
                                {{ item.raw.name }}
                              </VListItemTitle>
                            </VListItem>
                          </template>
                        </VAutocomplete>
                      </VListItemTitle>

                      <template #append>
                        <div class="d-flex gap-1">
                          <!-- Botones en modo normal -->
                          <template v-if="editingProduct !== product.id">
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="warning"
                              @click="handleEditProduct(product)"
                            >
                              <VIcon icon="tabler-edit" size="18" />
                              <VTooltip activator="parent" location="top">
                                Cambiar
                              </VTooltip>
                            </VBtn>
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="error"
                              @click="handleRemoveProduct(product.id)"
                            >
                              <VIcon icon="tabler-trash" size="18" />
                              <VTooltip activator="parent" location="top">
                                Eliminar
                              </VTooltip>
                            </VBtn>
                          </template>

                          <!-- Botones en modo edición -->
                          <template v-else>
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="success"
                              @click="handleSaveEdit(product.id)"
                            >
                              <VIcon icon="tabler-check" size="18" />
                              <VTooltip activator="parent" location="top">
                                Guardar
                              </VTooltip>
                            </VBtn>
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="error"
                              @click="handleCancelEdit"
                            >
                              <VIcon icon="tabler-x" size="18" />
                              <VTooltip activator="parent" location="top">
                                Cancelar
                              </VTooltip>
                            </VBtn>
                          </template>
                        </div>
                      </template>
                    </VListItem>
                    <VDivider v-if="index < formData.products.length - 1" />
                  </template>
                </VList>
              </VCard>
            </VCol>
          </VRow>
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
          :disabled="!formData.employee_id || formData.products.length === 0"
          @click="handleSubmit"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%"
        >
          {{ isEditMode ? "Actualizar" : "Guardar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
