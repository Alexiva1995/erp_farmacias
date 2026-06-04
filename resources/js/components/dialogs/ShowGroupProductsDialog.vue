<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch, onMounted } from "vue";
import { formatDateSimple } from "@/utils/formatters";

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
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true, width: "450px" },
  {
    title: "Stock",
    key: "valid_stock",
    sortable: true,
    align: 'center',
  },
  { title: "Exp.", key: "next_expiration", sortable: true, align: 'center' },
];
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000px"
    persistent
    :fullscreen="$vuetify.display.xs"
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column overflow-hidden detail-dialog-card">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-package" color="primary" size="26" />
          </VAvatar>
          <div class="flex-grow-1 overflow-hidden">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 text-uppercase truncate">
              Contenido del Grupo
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold truncate" style="max-inline-size: 200px;">
                {{ props.selectedGroup.name }} ({{ totalProducts }})
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" class="rounded-lg ms-3" @click="closeDialog">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-2 pa-sm-4 bg-light" style="overflow-y: auto;">
        
        <!-- Desktop Table -->
        <VCard variant="flat" class="d-none d-sm-block rounded-lg border overflow-hidden bg-white elevation-1">
          <VDataTable
            :headers="productHeaders"
            :items="associatedProducts"
            :loading="isLoadingProducts"
            density="compact"
            no-data-text="No hay productos asignados a este grupo."
            class="premium-table"
          >
            <template #item.id="{ item }">
              <a
                :href="'/inventory/traceability?q=' + item.id"
                target="_blank"
                class="text-decoration-none font-weight-black text-primary"
              >
                #{{ item.id }}
              </a>
            </template>

            <template #item.name="{ item }">
              <div class="d-flex align-center gap-x-3 py-2">
                <VAvatar
                  v-if="item.photo_url"
                  size="38"
                  variant="tonal"
                  rounded
                  :image="item.photo_url"
                  class="border flex-shrink-0"
                />
                <VAvatar
                  v-else
                  size="38"
                  variant="tonal"
                  color="primary"
                  rounded
                  class="flex-shrink-0 shadow-sm"
                >
                  <VIcon icon="tabler-package" size="18" />
                </VAvatar>
                <div class="d-flex flex-column truncate" style="max-inline-size: 400px;">
                  <span
                    class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase truncate"
                    :class="{ 
                      'text-warning': item.psychotropic == 1
                    }"
                  >
                    {{ item.name }}
                    <VChip v-if="item.iva == 1" size="x-super-small" color="success" variant="flat" class="ms-1 font-weight-black">G</VChip>
                  </span>
                  <div class="d-flex align-center gap-x-1 text-super-xs mt-1">
                    <span class="text-disabled truncate">{{ item.active_ingredient || "Sin componente" }}</span>
                    <span class="text-disabled">|</span>
                    <span class="text-primary font-weight-black text-uppercase truncate">
                      {{ item.laboratory?.name || 'S/L' }}
                    </span>
                  </div>
                </div>
              </div>
            </template>

            <template #item.valid_stock="{ item }">
              <VChip
                :color="(item.stock_calculado || 0) > 0 ? 'success' : 'error'"
                size="x-small"
                variant="tonal"
                class="font-weight-black rounded-lg"
              >
                {{ item.stock_calculado || 0 }} UNID
              </VChip>
            </template>

            <template #item.next_expiration="{ item }">
              <div class="d-flex flex-column align-center">
                <span 
                  class="text-xs font-weight-black"
                  :class="new Date(nextExpirationDate(item)) < new Date() ? 'text-error' : 'text-high-emphasis'"
                >
                  {{ formatDateSimple(nextExpirationDate(item)) }}
                </span>
                <span v-if="nextExpirationDate(item) !== 'N/A'" class="text-super-xs text-disabled uppercase font-weight-bold">Vencimiento</span>
              </div>
            </template>
          </VDataTable>
        </VCard>

        <!-- Mobile Cards -->
        <div class="d-block d-sm-none">
          <div v-if="isLoadingProducts" class="text-center py-10">
            <VProgressCircular indeterminate color="primary" />
          </div>
          <div v-else class="d-flex flex-column gap-3">
            <VCard
              v-for="item in associatedProducts"
              :key="item.id"
              variant="flat"
              class="rounded-lg border bg-white overflow-hidden shadow-sm"
            >
              <div class="pa-3 d-flex align-center gap-3">
                <VAvatar
                  v-if="item.photo_url"
                  size="44"
                  variant="tonal"
                  rounded
                  :image="item.photo_url"
                  class="border"
                />
                <VAvatar v-else size="44" variant="tonal" color="primary" rounded>
                    <VIcon icon="tabler-package" size="20" />
                </VAvatar>
                
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="text-xs font-weight-black text-primary">#{{ item.id }}</span>
                    <VChip size="x-super-small" color="success" variant="tonal" class="font-weight-black">{{ item.stock_calculado || 0 }} UNID</VChip>
                  </div>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase truncate leading-tight">
                    {{ item.name }}
                  </h3>
                  <div class="d-flex align-center justify-space-between mt-1">
                    <span class="text-super-xs text-disabled uppercase font-weight-bold truncate" style="max-inline-size: 150px;">{{ item.laboratory?.name || 'S/L' }}</span>
                    <span class="text-super-xs font-weight-black" :class="new Date(nextExpirationDate(item)) < new Date() ? 'text-error' : 'text-high-emphasis'">
                        {{ formatDateSimple(nextExpirationDate(item)) }}
                    </span>
                  </div>
                </div>
              </div>
            </VCard>
          </div>
          <div v-if="associatedProducts.length === 0 && !isLoadingProducts" class="text-center py-10 bg-white rounded-lg border">
            <VIcon icon="tabler-search-off" size="48" color="disabled" class="mb-2" />
            <p class="text-disabled font-weight-bold">No hay productos en este grupo</p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <VBtn
          color="secondary"
          variant="tonal"
          size="large"
          block
          height="50"
          class="font-weight-black rounded-lg text-button uppercase"
          @click="closeDialog"
        >
          Cerrar Vista
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-table :deep(th) {
  background-color: #f8fafc !important;
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}

.premium-table :deep(td) {
  padding-block: 10px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.x-super-small {
  height: 14px !important;
  font-size: 0.6rem !important;
  padding: 0 4px !important;
}
</style>
