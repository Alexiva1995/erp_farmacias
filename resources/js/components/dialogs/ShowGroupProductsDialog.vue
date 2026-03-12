<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";
import { formatDate } from "@/utils/formatters";

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
];
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
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon 
          icon="tabler-package" 
          size="24" 
          color="white" 
          class="me-2" 
        />
        <span class="text-h5 font-weight-bold text-white">
          Productos del grupo: {{ props.selectedGroup.name }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto;">
        <VDataTable
          :headers="productHeaders"
          :items="associatedProducts"
          :loading="isLoadingProducts"
          density="compact"
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
                  <span class="d-inline d-sm-none text-primary font-weight-bold">[{{ item.id }}] </span>
                  {{ item.name.toUpperCase() }}
                  <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
                  <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
                  <div v-if="item.laboratory" class="d-block d-md-none text-xs text-secondary italic">
                    {{ item.laboratory.name }}
                  </div>
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
            <span>{{ formatDate(nextExpirationDate(item)) }}</span>
          </template>
        </VDataTable>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
          style=" flex: 1 1 100%;max-inline-size: 100%;"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
