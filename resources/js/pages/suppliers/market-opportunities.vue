<script setup>
import SupplierIaOrderAssistantReportFilter from '@/components/SupplierIaOrderAssistantReportFilter.vue';
import axios from "@/plugins/axios";
import { formatCurrency } from "@/utils/currencyFormatter";
import { onMounted, reactive, ref, watch, computed } from 'vue';

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

const hasActiveAdvancedFilters = computed(() => (
  selectedLaboratory.value?.length > 0 ||
  selectProducts.value?.length > 0
));

const headers = [
  { title: "ID", key: "product_id", sortable: true, width: "80px" },
  { title: "Producto", key: "product_name_inventory", sortable: true },
  { title: "Costo Actual", key: "unit_cost_usd", align: "end", sortable: true },
  { title: "Mín. Referencia", key: "effective_min_cost", align: "end", sortable: true },
  { title: "Ahorro", key: "saving_amount", align: "end", sortable: true },
  { title: "% Ahorro", key: "saving_percentage", align: "end", sortable: true },
  { title: "Añadir", key: "actions", sortable: false, align: "center", width: "150px" },
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
    totalItems.value = response.data.meta.total;
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

const exportExcel = () => {
  console.log("Exportando a excel...");
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
  <div class="market-opportunities-view pb-12">
    <!-- Header Premium (Tarjeta Flotante) -->
    <VCard class="mx-6 mt-6 mb-6 rounded-lg border shadow-sm overflow-hidden">
      <div class="header-bg pa-6">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-4">
            <VAvatar
              size="54"
              color="white"
              variant="flat"
              class="rounded-lg shadow-soft"
            >
              <VIcon icon="tabler-bulb" color="primary" size="28" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h1 class="text-h4 font-weight-black text-white letter-spacing-tight">
                Oportunidades de Mercado
              </h1>
              <span class="text-sm font-weight-bold text-white opacity-80 uppercase letter-spacing-widest">
                Análisis de brecha de precios y ahorros potenciales
              </span>
            </div>
          </div>
        </div>
      </div>
    </VCard>

    <div class="px-6 d-flex flex-column gap-6">
      <!-- Filtros Estandarizados (AppFilterBase) -->
      <AppFilterBase
        v-model:search="searchQuery"
        :has-advanced-filters="hasActiveAdvancedFilters"
        search-placeholder="Buscar por nombre o barcode..."
        show-export
        @clear="handleClearFilters"
        @export="exportExcel"
      >
        <template #advanced-filters>
          <!-- Selección de Producto(s) -->
          <VCol cols="12" sm="6" md="6">
            <VAutocomplete
              v-model="selectProducts"
              :items="productosSelect"
              placeholder="Seleccionar Producto(s)"
              item-title="name"
              item-value="id"
              multiple
              chips
              closable-chips
              clearable
              hide-details
              density="compact"
              variant="outlined"
              class="premium-select-compact"
              prepend-inner-icon="tabler-package"
            />
          </VCol>

          <!-- Selección de Laboratorio(s) -->
          <VCol cols="12" sm="6" md="6">
            <VAutocomplete
              v-model="selectedLaboratory"
              :items="laboratories"
              placeholder="Seleccionar Laboratorio(s)"
              item-title="name"
              item-value="id"
              multiple
              chips
              closable-chips
              clearable
              hide-details
              density="compact"
              variant="outlined"
              class="premium-select-compact"
              prepend-inner-icon="tabler-flask"
            />
          </VCol>
        </template>
      </AppFilterBase>

      <!-- Tabla de Resultados (Unified VCard) -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Vista Desktop -->
      <div class="d-none d-md-block">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          v-model:sort-by="sortBy"
          :headers="headers"
          :items="items"
          :items-length="totalItems"
          :loading="loading"
          hover
          density="compact"
          class="text-no-wrap premium-table overflow-hidden"
          @update:options="fetchOpportunities"
        >
          <template #item.product_id="{ item }">
            <a
              :href="'/inventory/traceability?q=' + item.product_id"
              target="_blank"
              class="text-decoration-none font-weight-black text-primary"
            >
              {{ item.product_id }}
            </a>
          </template>

          <!-- Producto -->
          <template #item.product_name_inventory="{ item }">
            <div class="d-flex align-center py-2">
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.product_name_inventory">
                  {{ item.product_name_inventory.toUpperCase() }}
                </span>
                <div class="d-flex align-center gap-1 text-super-xs">
                  <span class="text-disabled truncate" style="max-inline-size: 200px;">{{ item.active_ingredient_inventory || 'SIN INGREDIENTE' }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                    {{ item.laboratory_name || 'S/L' }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <template #item.unit_cost_usd="{ item }">
            <span class="font-weight-medium text-success">{{ formatCurrency(item.unit_cost_usd, 'USD') }}</span>
          </template>

          <template #item.effective_min_cost="{ item }">
            <span class="text-medium-emphasis">{{ formatCurrency(item.effective_min_cost, 'USD') }}</span>
          </template>

          <template #item.saving_amount="{ item }">
            <span class="text-success font-weight-bold">
              {{ formatCurrency(item.saving_amount, 'USD') }}
            </span>
          </template>

          <template #item.saving_percentage="{ item }">
            <VChip color="success" size="x-small" label class="font-weight-bold">
              {{ item.saving_percentage }}%
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center ga-2 justify-center">
              <VTextField
                v-model="item.quantity_to_add"
                type="number"
                density="compact"
                hide-details
                variant="outlined"
                class="quantity-input"
                @keypress.enter="handleAddUnits(item)"
              />
              <VBtn
                icon="tabler-plus"
                color="primary"
                variant="tonal"
                size="small"
                class="rounded-circle shadow-sm"
                @click="handleAddUnits(item)"
              />
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil (Cards) -->
      <div class="d-md-none pa-2 bg-light-gray">
        <div v-if="loading" class="d-flex justify-center pa-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
        <div v-else-if="items.length === 0" class="text-center pa-8 text-disabled">
          No se encontraron oportunidades
        </div>
        <div v-else class="d-flex flex-column gap-2">
          <VCard
            v-for="item in items"
            :key="item.id"
            variant="flat"
            class="mb-1 rounded-lg border shadow-sm bg-white"
          >
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="flex-grow-1">
                  <div class="d-flex align-center gap-1 mb-1">
                    <a
                      :href="'/inventory/traceability?q=' + item.product_id"
                      target="_blank"
                      class="text-decoration-none text-xs font-weight-black text-primary"
                    >
                      #{{ item.product_id }}
                    </a>
                    <div class="text-subtitle-2 font-weight-black leading-tight truncate">
                      {{ item.product_name_inventory }}
                    </div>
                  </div>
                  <div class="d-flex flex-wrap ga-2 align-center">
                    <span class="text-caption text-medium-emphasis">{{ item.active_ingredient_inventory }}</span>
                    <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                      {{ item.laboratory_name }}
                    </VChip>
                  </div>
                </div>
                <VChip color="success" size="small" label class="font-weight-bold">
                  {{ item.saving_percentage }}%
                </VChip>
              </div>

              <VDivider class="mb-3 opacity-10" />

              <VRow dense class="mb-3">
                <VCol cols="6">
                  <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Costo Actual</div>
                  <div class="text-body-2 font-weight-bold text-success">{{ formatCurrency(item.unit_cost_usd, 'USD') }}</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Ahorro Estimado</div>
                  <div class="text-body-2 font-weight-bold text-success">{{ formatCurrency(item.saving_amount, 'USD') }}</div>
                </VCol>
              </VRow>

              <div class="d-flex align-center justify-space-between bg-var-theme-background-soft pa-3 rounded-lg border border-dashed">
                <div class="text-caption font-weight-bold text-medium-emphasis">Referencia Mín: {{ formatCurrency(item.effective_min_cost, 'USD') }}</div>
                <div class="d-flex align-center ga-2">
                  <VTextField
                    v-model="item.quantity_to_add"
                    type="number"
                    density="compact"
                    hide-details
                    variant="outlined"
                    class="quantity-input-mobile"
                    bg-color="white"
                  />
                  <VBtn
                    icon="tabler-plus"
                    color="primary"
                    variant="tonal"
                    size="small"
                    class="rounded-circle shadow-sm"
                    @click="handleAddUnits(item)"
                  />
                </div>
              </div>
            </VCardText>
          </VCard>

          <VPagination
            v-model="page"
            :length="Math.ceil(totalItems / itemsPerPage)"
            density="compact"
            class="mt-4"
          />
        </div>
      </div>
    </VCard>
  </div>
</div>
</template>

<style scoped>
.gap-2 { gap: 8px !important; }
.gap-4 { gap: 16px !important; }

.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.market-opportunities-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.header-bg {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #4a90e2 100%);
}

.letter-spacing-tight { letter-spacing: -0.02em; }
.letter-spacing-widest { letter-spacing: 0.1em !important; }

.shadow-soft { box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important; }

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.premium-table :deep(tbody tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

.bg-light-gray {
  background-color: #f8f9fa;
}

.quantity-input {
  inline-size: 80px;
}

.quantity-input-mobile {
  inline-size: 70px;
}

.bg-var-theme-background-soft {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.border-dashed {
  border-style: dashed !important;
}

.leading-tight {
  line-height: 1.25;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}
</style>
