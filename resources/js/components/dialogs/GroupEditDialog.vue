<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { formatDate } from "@/utils/formatters";

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
  { 
    title: "id", 
    key: "id", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true, width: "40%" },
  { 
    title: "Laboratorio", 
    key: "laboratory.name", 
    sortable: true,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  {
    title: "Stock",
    key: "valid_stock",
    visible: true,
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell",
    value: (item) => {
      return item.stock_calculado;
    },
  },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Acción", key: "actions", sortable: false, align: "end" },
];

const nextExpirationDate = (product) => {
  if (!product.lots || !Array.isArray(product.lots) || product.lots.length === 0) return "N/A";
  
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  
  if (validLots.length === 0) return product.ultima_fecha_vencimiento || "N/A";
  
  validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
  return validLots[0].expiration_date;
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="isNewGroup || (associatedProducts.length === 0 && !isLoadingProducts) ? '600px' : '1000px'"
    persistent
    @update:model-value="closeDialog"
    content-class="d-flex"
  >
    <VCard v-if="formData" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon :icon="isNewGroup ? 'tabler-folder-plus' : 'tabler-folders'" color="primary" size="26" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ isNewGroup ? "Añadir Nuevo Grupo" : "Editar Grupo" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                Agrupación lógica de productos para gestión masiva
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg ms-3"
            @click="closeDialog"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText 
        class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6 overflow-y-auto" 
        style="max-block-size: calc(100vh - 200px);"
      >
        <VForm @submit.prevent="submitForm" class="d-flex flex-column gap-6">
          
          <!-- Seccion: Información del Grupo -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información del Grupo</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="formData.name"
                    label="Nombre del Grupo"
                    placeholder="Ej: Antibióticos, Analgésicos..."
                    variant="outlined"
                    density="comfortable"
                    :error-messages="formErrors.name"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
              </VRow>
            </VCard>
          </section>

          <!-- Seccion: Productos Vinculados -->
          <template v-if="!isNewGroup">
            <section>
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator secondary shadow-sm"></div>
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Productos Vinculados</span>
              </div>

              <VCard variant="flat" class="overflow-hidden bg-white rounded-lg elevation-1 border">
                <VDataTable
                  :headers="productHeaders"
                  :items="associatedProducts"
                  :loading="isLoadingProducts"
                  density="compact"
                  no-data-text="No hay productos asignados a este grupo."
                  class="premium-table"
                >
                  <template #item.id="{ item }">
                    <span class="text-xs font-weight-black text-primary">#{{ item.id }}</span>
                  </template>

                  <template #item.name="{ item }">
                    <div class="d-flex align-center gap-x-3 py-2">
                      <VAvatar
                        v-if="item.photo_url"
                        size="34"
                        variant="tonal"
                        rounded
                        :image="item.photo_url"
                        class="elevation-1"
                      />
                      <VAvatar
                        v-else
                        size="34"
                        variant="tonal"
                        color="primary"
                        rounded
                        class="elevation-1"
                      >
                        <VIcon icon="tabler-package" size="18" />
                      </VAvatar>
                      <div class="d-flex flex-column">
                        <span
                          class="text-body-2 font-weight-black text-high-emphasis leading-tight"
                          :class="{ 
                            'text-warning': item.psychotropic == 1 || item.psychotropic === true
                          }"
                        >
                          {{ item.name.toUpperCase() }}
                          <VBadge
                            v-if="item.iva == 1"
                            content="G"
                            color="success"
                            inline
                            class="ms-1"
                          />
                        </span>
                        <span class="text-super-xs text-disabled font-weight-bold uppercase">{{ item.active_ingredient }}</span>
                      </div>
                    </div>
                  </template>

                  <template #item["laboratory.name"]="{ item }">
                    <span class="text-xs font-weight-medium uppercase">{{ item.laboratory?.name || "—" }}</span>
                  </template>

                  <template #item.valid_stock="{ item }">
                    <VChip size="x-small" color="info" variant="tonal" class="font-weight-black">
                      {{ item.stock_calculado || 0 }} UNID
                    </VChip>
                  </template>

                  <template #item.next_expiration="{ item }">
                    <VChip 
                      size="x-small" 
                      variant="flat" 
                      :color="new Date(nextExpirationDate(item)) < new Date() ? 'error' : 'secondary'"
                      class="font-weight-bold"
                    >
                      {{ formatDate(nextExpirationDate(item)) }}
                    </VChip>
                  </template>

                  <template #item.actions="{ item }">
                    <IconBtn
                      color="error"
                      variant="tonal"
                      size="small"
                      :disabled="isUnassigningProduct === item.id"
                      @click="unassignProduct(item)"
                      class="rounded-lg"
                    >
                      <VProgressCircular
                        v-if="isUnassigningProduct === item.id"
                        indeterminate
                        size="16"
                      />
                      <VIcon v-else icon="tabler-trash-x" />
                    </IconBtn>
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

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.premium-table :deep(th) {
  background-color: #f8fafc !important;
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
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
