<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
});

const emit = defineEmits(["update:isDialogVisible", "activated"]);

const disabledSuppliers = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const activatingId = ref(null);
let searchDebounce = null;

const fetchDisabledSuppliers = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/suppliers/disabled", {
      params: { search: searchQuery.value },
    });
    disabledSuppliers.value = response.data.data || [];
  } catch (error) {
    console.error("Error al obtener proveedores desactivados:", error);
    toast.error("Error al cargar la lista de proveedores desactivados.");
  } finally {
    loading.value = false;
  }
};

const handleActivate = async (supplier) => {
  activatingId.value = supplier.id;
  try {
    const response = await axios.patch(`/suppliers/${supplier.id}/toggle-status`, {
      is_active: true,
    });
    toast.success(response.data.message || `Proveedor ${supplier.name} activado con éxito.`);
    await fetchDisabledSuppliers();
    emit("activated", supplier);
  } catch (error) {
    console.error("Error al activar proveedor:", error);
    toast.error(error.response?.data?.message || "No se pudo activar el proveedor.");
  } finally {
    activatingId.value = null;
  }
};

watch(
  () => props.isDialogVisible,
  (val) => {
    if (val) {
      searchQuery.value = "";
      fetchDisabledSuppliers();
    }
  },
);

watch(searchQuery, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    fetchDisabledSuppliers();
  }, 300);
});

const closeDialog = () => {
  emit("update:isDialogVisible", false);
};
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="650"
    persistent
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard class="rounded-lg">
      <VCardItem class="pb-3 border-b">
        <div class="d-flex align-center justify-space-between w-100">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-power" color="success" size="24" />
            <VCardTitle class="text-h6 font-weight-bold">
              Proveedores Desactivados
            </VCardTitle>
          </div>
          <DialogCloseBtn @click="closeDialog" />
        </div>
      </VCardItem>

      <VCardText class="pa-4">
        <!-- Buscador -->
        <VTextField
          v-model="searchQuery"
          placeholder="Buscar proveedor desactivado por nombre o ID..."
          density="compact"
          prepend-inner-icon="tabler-search"
          clearable
          hide-details
          class="mb-4"
        />

        <!-- Loading -->
        <div v-if="loading" class="d-flex justify-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <!-- Estado vacío -->
        <div
          v-else-if="disabledSuppliers.length === 0"
          class="text-center py-8 text-disabled"
        >
          <VIcon icon="tabler-circle-check" size="48" class="mb-2 text-success opacity-50" />
          <p class="text-body-1 mb-0">No hay proveedores desactivados actualmente.</p>
        </div>

        <!-- Lista de Proveedores -->
        <VList v-else density="compact" class="py-0 rounded border">
          <template v-for="(supplier, index) in disabledSuppliers" :key="supplier.id">
            <VListItem class="py-2">
              <template #prepend>
                <VAvatar color="secondary" variant="tonal" size="36" class="me-3">
                  <VIcon icon="tabler-building-warehouse" size="20" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-bold text-uppercase">
                {{ supplier.name }}
                <span class="text-caption text-disabled ms-1">#{{ supplier.id }}</span>
              </VListItemTitle>

              <VListItemSubtitle class="text-caption">
                Tipo: <span class="font-weight-medium">{{ supplier.type || 'N/A' }}</span>
                <span v-if="supplier.rif" class="ms-2">| RIF: {{ supplier.rif }}</span>
              </VListItemSubtitle>

              <template #append>
                <VBtn
                  color="success"
                  variant="flat"
                  size="small"
                  class="rounded-pill px-3 font-weight-bold"
                  prepend-icon="tabler-check"
                  :loading="activatingId === supplier.id"
                  @click="handleActivate(supplier)"
                >
                  Activar
                </VBtn>
              </template>
            </VListItem>
            <VDivider v-if="index < disabledSuppliers.length - 1" :key="'div-' + supplier.id" />
          </template>
        </VList>
      </VCardText>

      <VCardActions class="pa-4 border-t justify-end">
        <VBtn variant="tonal" color="secondary" @click="closeDialog">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.gap-2 {
  gap: 8px !important;
}
</style>
