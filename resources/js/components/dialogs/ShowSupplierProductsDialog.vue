<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modalValue"]);

const page = ref(1);
const itemsPerPage = ref(10);
const products = ref([]);
const totalProducts = ref(0);
const loading = ref(false);

const closeDialog = () => {
  emit("update:modelValue", false);
};

watch(
  () => props.selectedSupplier,
  (selectedSupplier) => {
    if (selectedSupplier?.id) {
      page.value = 1;
      fetchSupplierProducts(selectedSupplier.id);
    }
  },
  { deep: true, immediate: true },
);

watch([page, itemsPerPage], () => {
  if (props.selectedSupplier?.id) {
    fetchSupplierProducts(props.selectedSupplier.id);
  }
});

const productsHeaders = [
  { title: "ID", key: "id", sortable: false },
  { title: "Nombre", key: "name", sortable: false },
  { title: "Laboratorio", key: "laboratory", sortable: false },
  { title: "Coste (BS)", key: "unit_cost", sortable: false },
  { title: "Coste (Usd)", key: "unit_cost_usd", sortable: false },
];

const formatBs = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " Bs."
  );
};
const formatUsd = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " $"
  );
};

const fetchSupplierProducts = async (id) => {
  try {
    loading.value = true;
    const { data } = await axios.get(`/suppliers/${id}/products`, {
      params: {
        page: page.value,
        perPage: itemsPerPage.value,
      },
    });
    products.value = data.data;
    totalProducts.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener los productos del proveedor.");
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString || dateString === "No se ha establecido conexión")
    return "N/A";
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

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="860px"
    persistent
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-box-seam" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Productos del Proveedor
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Catálogo de Inventario • Importación
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">

        <!-- Info del proveedor -->
        <div class="d-flex align-center gap-2 mb-3">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información del Proveedor</span>
        </div>

        <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
          <VRow>
            <VCol cols="12" sm="6">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-building-store" size="16" color="primary" />
                <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">Proveedor</span>
              </div>
              <div class="text-subtitle-2 font-weight-black text-high-emphasis mt-1">{{ selectedSupplier.name }}</div>
            </VCol>
            <VCol cols="12" sm="6">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-calendar-time" size="16" color="primary" />
                <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">Última Actualización</span>
              </div>
              <div class="mt-1">
                <VChip
                  :color="formatDate(selectedSupplier.last_connection) === 'N/A' ? 'warning' : 'success'"
                  size="small"
                  class="font-weight-black rounded-lg"
                  variant="tonal"
                >
                  {{ formatDate(selectedSupplier.last_connection) }}
                </VChip>
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Sección tabla de productos -->
        <div class="d-flex align-center gap-2 mb-3">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Catálogo de Productos</span>
          <VSpacer />
          <VChip size="small" color="primary" variant="tonal" class="font-weight-black rounded-lg">
            {{ totalProducts }} productos
          </VChip>
        </div>

        <VCard variant="flat" class="bg-white rounded-xl border shadow-sm">
          <VDataTableServer
            :headers="productsHeaders"
            :items="products"
            :loading="loading"
            density="compact"
            class="premium-table"
            no-data-text="Este proveedor no tiene productos registrados."
            :items-per-page="itemsPerPage"
            :page="page"
            :server-items-length="totalProducts"
            :items-length="totalProducts"
            @update:options="updateTableOptions"
          >
            <template #item.id="{ item }">
              <span class="text-xs font-weight-black text-primary">#{{ item.id }}</span>
            </template>
            <template #item.name="{ item }">
              <span class="text-xs font-weight-black text-high-emphasis uppercase">{{ item.name }}</span>
            </template>
            <template #item.laboratory="{ item }">
              <span class="text-xs text-medium-emphasis">{{ item.laboratory }}</span>
            </template>
            <template #item.unit_cost="{ item }">
              <span class="text-xs font-weight-black">{{ formatBs(item.unit_cost) }}</span>
            </template>
            <template #item.unit_cost_usd="{ item }">
              <span class="text-xs font-weight-black text-success">{{ formatUsd(item.unit_cost_usd) }}</span>
            </template>
          </VDataTableServer>
        </VCard>

      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              @click="closeDialog"
            >
              Cerrar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              @click="closeDialog"
            >
              <VIcon start icon="tabler-check" size="18" />
              Aceptar
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
  background-color: #f8faff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.premium-table {
  background: transparent !important;
}

.premium-table :deep(th) {
  background-color: #f1f5f9 !important;
  block-size: 44px !important;
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.07em !important;
  text-transform: uppercase;
  border-block-end: 2px solid #e2e8f0 !important;
}

.premium-table :deep(td) {
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.06) !important;
}
</style>
