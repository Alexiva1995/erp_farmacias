<script setup>
import SupplierIaOrderAssistantReportFilter from '@/components/SupplierIaOrderAssistantReportFilter.vue';
import axios from "@/plugins/axios";
import { formatCurrency } from "@/utils/currencyFormatter";
import { onMounted, reactive, ref, watch } from 'vue';

const laboratories = ref([]);
const productosSelect = ref([]);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'saving_percentage', order: 'desc' }]);
const totalItems = ref(0);
const items = ref([]);

const selectedLaboratory = ref([]);
const selectProducts = ref([]);
const searchQuery = ref("");

const headers = [
  { title: "Producto", key: "product_name_inventory", sortable: true },
  { title: "Laboratorio", key: "laboratory_name", sortable: true },
  { title: "Principio Activo", key: "active_ingredient_inventory", sortable: false },
  { title: "Costo Actual", key: "unit_cost_usd", align: "end", sortable: true },
  { title: "Mín. Histórico", key: "min_historic_cost", align: "end", sortable: true },
  { title: "Ahorro", key: "saving_amount", align: "end", sortable: true, color: 'success' },
  { title: "% Ahorro", key: "saving_percentage", align: "end", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

async function fetchOpportunities() {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value[0]?.key,
      orderBy: sortBy.value[0]?.order,
      q: searchQuery.value,
      laboratoryId: selectedLaboratory.value,
      productId: selectProducts.value,
    };

    const response = await axios.get('/market-opportunities', { params });
    items.value = response.data.data;
    totalItems.value = response.data.total;
  } catch (error) {
    console.error("Error al cargar oportunidades:", error);
  } finally {
    loading.value = false;
  }
}

async function fetchInitialData() {
  try {
    const [labsRes, productsRes] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("suppliers-ia-assistant-report/consult-products")
    ]);
    
    laboratories.value = labsRes.data;
    productosSelect.value = productsRes.data.data.map(p => ({
      name: `${p.id} - ${p.name}`,
      id: p.id,
    }));
  } catch (error) {
    console.error("Error al cargar catálogos:", error);
  }
}

const handleClearFilters = () => {
  selectedLaboratory.value = [];
  selectProducts.value = [];
  searchQuery.value = "";
};

const handleAddUnits = (item) => {
  // Lógica para añadir unidades (se puede integrar con el carrito o pedido directo)
  console.log("Añadir unidades para:", item.product_name_inventory);
};

watch([page, itemsPerPage, sortBy, selectedLaboratory, selectProducts, searchQuery], () => {
  fetchOpportunities();
}, { deep: true });

onMounted(() => {
  fetchInitialData();
  fetchOpportunities();
});
</script>

<template>
  <VContainer fluid>
    <VRow class="mb-4">
      <VCol cols="12">
        <h1 class="text-h4 font-weight-bold text-primary">
          <VIcon icon="tabler-trending-down" class="me-2" />
          Oportunidades de Mercado
        </h1>
      </VCol>
    </VRow>

    <!-- Filtros Estandarizados -->
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <AppAutocomplete
              v-model="selectProducts"
              :items="productosSelect"
              placeholder="Seleccionar Productos"
              item-title="name"
              item-value="id"
              multiple
              chips
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol cols="12" md="4">
            <AppAutocomplete
              v-model="selectedLaboratory"
              :items="laboratories"
              placeholder="Seleccionar Laboratorio"
              item-title="name"
              item-value="id"
              multiple
              chips
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol cols="12" md="4">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar por nombre o barcode"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details="auto"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6">
        <VBtn color="secondary" variant="outlined" @click="handleClearFilters">
          Limpiar Filtros
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Tabla de Resultados -->
    <VCard elevation="0" border>
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        v-model:sort-by="sortBy"
        :headers="headers"
        :items="items"
        :items-length="totalItems"
        :loading="loading"
        hover
        class="text-no-wrap"
      >
        <template #item.unit_cost_usd="{ item }">
          <span class="font-weight-medium">{{ formatCurrency(item.unit_cost_usd, 'USD') }}</span>
        </template>

        <template #item.min_historic_cost="{ item }">
          <span class="text-medium-emphasis">{{ formatCurrency(item.min_historic_cost, 'USD') }}</span>
        </template>

        <template #item.saving_amount="{ item }">
          <VChip color="success" variant="tonal" size="small" class="font-weight-bold">
            - {{ formatCurrency(item.saving_amount, 'USD') }}
          </VChip>
        </template>

        <template #item.saving_percentage="{ item }">
          <span class="text-success font-weight-bold">{{ item.saving_percentage }}%</span>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            color="primary"
            variant="tonal"
            size="small"
            @click="handleAddUnits(item)"
          >
            <VIcon icon="tabler-plus" class="me-1" />
            Añadir
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>
  </VContainer>
</template>

<style scoped>
.v-data-table :deep(thead th) {
  font-weight: bold !important;
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  letter-spacing: 0.05em !important;
}
</style>
