<script setup>
import axios from "@/plugins/axios";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
  employees: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clear-errors"]);

const isEditMode = computed(() => !!props.employee?.employee_id);
const { mobile } = useDisplay();
const dialogTitle = computed(() =>
  isEditMode.value ? "Editar Asignación" : "Asignar Productos",
);

const formData = ref({
  employee_id: null,
  products: [],
  new_product_id: null,
});

const editingProduct = ref(null);
const tempProductId = ref(null);
const searchProduct = ref("");
const remoteProducts = ref([]);
const isSearching = ref(false);

// Cargar productos remotos filtrando por ID o Nombre
const loadRemoteProducts = async (query = "") => {
  const isNumeric = /^\d+$/.test(query);
  if (query.length < (isNumeric ? 1 : 2)) {
     remoteProducts.value = [];
     return;
  }
  
  isSearching.value = true;
  try {
    const response = await axios.get("/products", {
      params: { q: query, itemsPerPage: 50 },
    });
    const products = response.data.data;
    const uniqueMap = new Map();
    products.forEach(p => {
      uniqueMap.set(p.id, {
        ...p,
        displayLabel: `${p.id} - ${p.name}`
      });
    });
    remoteProducts.value = Array.from(uniqueMap.values());
  } catch (error) {
    console.error("Error buscando productos:", error);
  } finally {
    isSearching.value = false;
  }
};

let searchDebounce;
watch(searchProduct, (val) => {
  if (!val) return;
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    loadRemoteProducts(val);
  }, 400);
});

// Añadir automáticamente al seleccionar
watch(() => formData.value.new_product_id, (newId) => {
  if (newId) {
    handleAddProduct();
  }
});

watch(
  [() => props.modelValue, () => props.employee],
  ([newVisible], [oldVisible]) => {
    if (!newVisible) return;
    if (newVisible && (oldVisible === undefined || oldVisible === false)) {
      if (isEditMode.value && props.employee?.employee_id) {
        formData.value = {
          employee_id: props.employee.employee_id,
          products: props.employee.products
            ? JSON.parse(JSON.stringify(props.employee.products))
            : [],
          new_product_id: null,
        };
      } else {
        formData.value = {
          employee_id: null,
          products: [],
          new_product_id: null,
        };
      }
      editingProduct.value = null;
      tempProductId.value = null;
      remoteProducts.value = [];
    }
  },
  { deep: true },
);

const displayEmployees = computed(() => {
  if (isEditMode.value && props.employee?.employee_id) {
    const exists = props.employees.some(e => e.value === props.employee.employee_id);
    if (!exists) {
      return [
        ...props.employees,
        {
          title: props.employee.employee_name,
          value: props.employee.employee_id
        }
      ];
    }
  }
  return props.employees;
});

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
  editingProduct.value = null;
  tempProductId.value = null;
};

const handleAddProduct = () => {
  if (!formData.value.new_product_id) return;

  const product = remoteProducts.value.find(
    (prod) => Number(prod.id) === Number(formData.value.new_product_id),
  );

  if (product) {
    const exists = formData.value.products.some(
      (prod) => Number(prod.id) === Number(product.id),
    );

    if (!exists) {
      formData.value.products.push({
        id: product.id,
        name: product.name,
      });
    }
    // Siempre limpiar después de intentar añadir (sea éxito o duplicado)
    formData.value.new_product_id = null;
    searchProduct.value = "";
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
  remoteProducts.value = [];
  remoteProducts.value.push({ 
    id: product.id, 
    name: product.name, 
    displayLabel: `${product.id} - ${product.name}` 
  });
};

const handleSaveEdit = (oldProductId) => {
  if (!tempProductId.value) return;

  const newProd = remoteProducts.value.find(
    (prod) => prod.id === tempProductId.value,
  );

  if (newProd) {
    const index = formData.value.products.findIndex(
      (prod) => prod.id === oldProductId,
    );

    if (index !== -1) {
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
  emit("save", dataToSend);
};

const availableProductsWithId = computed(() => {
  return remoteProducts.value.filter(
    (prod) => !formData.value.products.some((p) => p.id === prod.id),
  );
});

const getProductColor = (index) => {
  const colors = ["success", "info", "warning", "secondary", "primary", "error"];
  return colors[index % colors.length];
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '700px'"
    :fullscreen="mobile"
    persistent
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon :icon="isEditMode ? 'tabler-user-cog' : 'tabler-package'" size="24" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
                {{ isEditMode ? `Gestión de ID: #${props.employee.employee_id}` : 'Asignación de productos por empleado' }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto" style="max-height: 70vh;">
        <VForm @submit.prevent="handleSubmit" class="d-flex flex-column gap-6">
          
          <!-- Sección Empleado -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Selección de Empleado</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow dense>
                <VCol cols="12">
                  <AppSelect
                    v-model="formData.employee_id"
                    :items="displayEmployees"
                    :disabled="isEditMode"
                    label="Empleado responsable"
                    placeholder="Seleccionar empleado..."
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    class="shadow-sm"
                    :error-messages="props.errors.employee_id"
                    prepend-inner-icon="tabler-user"
                  >
                    <template #selection="{ item }">
                      <div class="d-flex align-center gap-2">
                        <VAvatar size="24" color="primary" variant="tonal" class="rounded">
                          <span class="text-super-xs font-weight-black">
                            {{ item.title.split(" ").map((n) => n[0]).join("").substring(0, 2).toUpperCase() }}
                          </span>
                        </VAvatar>
                        <span class="text-xs font-weight-bold">{{ item.title }}</span>
                      </div>
                    </template>
                  </AppSelect>
                </VCol>
              </VRow>
            </VCard>
          </section>

          <!-- Sección Gestión -->
          <section>
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator primary shadow-sm"></div>
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Gestión de Catálogo</span>
              </div>
              <VChip v-if="formData.products.length > 0" color="primary" size="x-small" variant="flat" class="font-weight-black rounded">
                {{ formData.products.length }} ASIGNADOS
              </VChip>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border mb-4">
              <VRow dense>
                <VCol cols="12">
                  <div class="d-flex align-end gap-3">
                    <AppAutocomplete
                      v-model="formData.new_product_id"
                      v-model:search="searchProduct"
                      :items="availableProductsWithId"
                      :loading="isSearching"
                      item-title="displayLabel"
                      item-value="id"
                      label="Añadir producto"
                      placeholder="Escribir ID o nombre..."
                      :disabled="!formData.employee_id"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                      :no-filter="true"
                      class="flex-grow-1 shadow-sm"
                      prepend-inner-icon="tabler-pill"
                    />
                    <VBtn
                      color="primary"
                      variant="flat"
                      class="rounded-lg shadow-primary"
                      height="48"
                      min-width="50"
                      :disabled="!formData.new_product_id || !formData.employee_id"
                      @click="handleAddProduct"
                    >
                      <VIcon icon="tabler-plus" size="24" />
                    </VBtn>
                  </div>
                </VCol>
              </VRow>
            </VCard>

            <!-- Lista de Productos -->
            <VCard variant="flat" class="border rounded-lg bg-white elevation-1 overflow-hidden">
              <div v-if="formData.products.length === 0" class="pa-8 d-flex flex-column align-center justify-center text-center">
                <VIcon icon="tabler-package-off" size="40" class="text-disabled opacity-20 mb-3" />
                <div class="text-xs font-weight-black text-disabled uppercase">No hay productos asignados aún</div>
              </div>

              <VList v-else class="pa-0">
                <template v-for="(product, index) in formData.products" :key="product.id">
                  <VListItem class="px-4 py-3">
                    <template #prepend>
                      <VAvatar :color="getProductColor(index)" variant="tonal" size="36" class="rounded-lg">
                        <VIcon icon="tabler-pill" size="20" />
                      </VAvatar>
                    </template>

                    <VListItemTitle>
                      <div v-if="editingProduct !== product.id" class="d-flex align-center gap-2">
                        <VChip size="x-small" color="primary" variant="flat" label class="rounded font-weight-black">
                          {{ product.id }}
                        </VChip>
                        <span class="text-sm font-weight-black uppercase text-high-emphasis">
                          {{ product.name }}
                        </span>
                      </div>
                      <AppAutocomplete
                        v-else
                        v-model="tempProductId"
                        v-model:search="searchProduct"
                        :items="remoteProducts"
                        :loading="isSearching"
                        item-title="displayLabel"
                        item-value="id"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="shadow-sm"
                        :no-filter="true"
                      />
                    </VListItemTitle>

                    <template #append>
                      <div class="d-flex gap-1">
                        <template v-if="editingProduct !== product.id">
                          <VBtn icon variant="tonal" size="x-small" color="warning" class="rounded" @click="handleEditProduct(product)">
                            <VIcon icon="tabler-edit" size="18" />
                          </VBtn>
                          <VBtn icon variant="tonal" size="x-small" color="error" class="rounded" @click="handleRemoveProduct(product.id)">
                            <VIcon icon="tabler-trash" size="18" />
                          </VBtn>
                        </template>
                        <template v-else>
                          <VBtn icon variant="flat" size="x-small" color="success" class="rounded" @click="handleSaveEdit(product.id)">
                            <VIcon icon="tabler-check" size="18" />
                          </VBtn>
                          <VBtn icon variant="flat" size="x-small" color="error" class="rounded" @click="handleCancelEdit">
                            <VIcon icon="tabler-x" size="18" />
                          </VBtn>
                        </template>
                      </div>
                    </template>
                  </VListItem>
                  <VDivider v-if="index < formData.products.length - 1" class="border-opacity-10" />
                </template>
              </VList>
            </VCard>
          </section>

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
              :disabled="!formData.employee_id || formData.products.length === 0"
              @click="handleSubmit"
            >
              <VIcon start icon="tabler-device-floppy" size="18" class="me-2" />
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
  background: var(--brand-gradient) !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
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

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
