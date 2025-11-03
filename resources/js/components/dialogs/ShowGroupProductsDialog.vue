<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedGroup: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue"]);

const associatedProducts = ref([]);
const totalProducts = ref(0);
const isLoadingProducts = ref(false);

async function fetchAssociatedProducts(groupId) {
  if (!groupId) return;
  isLoadingProducts.value = true;
  try {
    const response = await axios.get("/products", {
      params: { groupId: groupId, itemsPerPage: -1 },
    });
    associatedProducts.value = response.data.data;
    totalProducts.value = response.data.total;
  } catch (error) {
    console.error("Error al cargar los productos del grupo:", error);
    toast.error("No se pudieron cargar los productos del grupo.");
  } finally {
    isLoadingProducts.value = false;
  }
}

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      fetchAssociatedProducts(props.selectedGroup.id);
    } else if (!isVisible) {
      associatedProducts.value = [];
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
};

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
  if (validLots.length === 0) return "Todos expiraron";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split("T")[0];
};

const productHeaders = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true, width: "40%" },
  { title: "Laboratorio", key: "laboratory.name", sortable: true },
  { title: "Stock", key: "stock", sortable: true },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Precio Venta", key: "sale_price", sortable: true },
];
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="900px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">
          {{ `Productos del grupo ${props.selectedGroup.name}` }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VDataTable
          :headers="productHeaders"
          :items="associatedProducts"
          :loading="isLoadingProducts"
          density="compact"
          no-data-text="No hay productos asignados a este grupo."
          class="rounded-lg"
        >
          <template #item.next_expiration="{ item }">
            <span>{{ nextExpirationDate(item) }}</span>
          </template>
        </VDataTable>
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
          @click="closeDialog"
          class="flex-grow-1 w-0"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
