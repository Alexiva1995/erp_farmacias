<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import Swal from "sweetalert2";
import { computed, onMounted, ref, watch } from "vue";

const laboratories = ref([]);
const productosSelect = ref([]);
const suppliers = ref([]);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: "saving_percentage", order: "desc" }]);
const totalItems = ref(0);
const items = ref([]);

const selectedLaboratory = ref([]);
const selectProducts = ref([]);
const excludeSupplierIds = ref([]);
const withDiscount = ref(false);
const hideRedundant = ref(true);
const hideDuplicates = ref(true);
const isColombia = ref(null);
const tipoFiltracion = ref("combinado");
const lapsoTiempo = ref("3 month");
const stockFilter = ref("all");
const searchQuery = ref("");

const hasActiveAdvancedFilters = computed(
  () =>
    selectedLaboratory.value?.length > 0 || 
    selectProducts.value?.length > 0 || 
    excludeSupplierIds.value?.length > 0 ||
    isColombia.value !== null ||
    tipoFiltracion.value !== 'combinado' ||
    lapsoTiempo.value !== '3 month' ||
    stockFilter.value !== 'all',
);

const headers = [
  { title: "ID", key: "product_id", sortable: true, width: "80px" },
  { title: "Producto", key: "product_name_inventory", sortable: true, minWidth: "350px" },
  { title: "Histórico", key: "historic_costs", align: "end", sortable: false },
  { title: "OFERTA", key: "unit_cost_usd", align: "end", sortable: true },
  { title: "% Ahorro", key: "saving_percentage", align: "end", sortable: true },
  { title: "vent.", key: "total_sold_completed", align: "end", sortable: true },
  { title: "stock", key: "lote_quantity", align: "end", sortable: true },
  { title: "AO", key: "totalQuantityInAutoOrder", align: "end", sortable: true },
  { title: "PRomedio", key: "promedio_calculado", align: "end", sortable: true },
  {
    title: "Añadir",
    key: "actions",
    sortable: false,
    align: "center",
    width: "150px",
  },
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
      excludeSupplierIds: excludeSupplierIds.value,
      withDiscount: withDiscount.value,
      hideRedundant: hideRedundant.value,
      hideDuplicates: hideDuplicates.value,
      is_colombia: isColombia.value,
      tipo_filtracion: tipoFiltracion.value,
      lapso_de_tiempo: lapsoTiempo.value,
      stock: stockFilter.value,
    };

    const response = await axios.get("/market-opportunities", { params });
    items.value = response.data.data;
    totalItems.value = response.data.meta.total;
  } catch (error) {
    console.error("Error al cargar oportunidades:", error);
  } finally {
    loading.value = false;
  }
}

async function fetchInitialData() {
  // Carga de laboratorios
  axios.get("/laboratories")
    .then(res => { laboratories.value = res.data; })
    .catch(err => console.error("Error labs:", err));

  // Carga de productos para el selector
  axios.get("/suppliers-ia-assistant-report/consult-products")
    .then(res => {
      productosSelect.value = (res.data.data || []).map((p) => ({
        name: `${p.id} - ${p.name}`,
        id: p.id,
      }));
    })
    .catch(err => console.error("Error products select:", err));

  // Carga de proveedores para exclusion
  axios.get("/suppliers", { params: { itemsPerPage: 500 } })
    .then(res => {
      suppliers.value = res.data?.data || res.data || [];
    })
    .catch(err => console.error("Error suppliers select:", err));
}

const handleClearFilters = () => {
  selectedLaboratory.value = [];
  selectProducts.value = [];
  excludeSupplierIds.value = [];
  searchQuery.value = "";
  hideRedundant.value = true;
  hideDuplicates.value = true;
  isColombia.value = null;
  tipoFiltracion.value = "combinado";
  lapsoTiempo.value = "3 month";
  stockFilter.value = "all";
};

const handleAddUnits = async (item) => {
  if (!item.quantity_to_add || item.quantity_to_add <= 0) {
    toast.error("Ingresa una cantidad válida.");
    return;
  }

  loading.value = true;
  try {
    const data = {
      product_id: item.product_id,
      quantity: item.quantity_to_add,
      supplier_id: item.supplier_id,
      product_supplier_id: item.id,
      unit_cost: item.unit_cost_usd
    };

    const response = await axios.post("/suppliers-ia-order-assistant/add-to-order", data);
    
    toast.success(response.data.message || "Producto añadido a la orden.");
    
    // Opcional: remover el item de la lista ya que fue "procesado" (el repo lo oculta tras 7 días al recargar)
    items.value = items.value.filter(i => i.id !== item.id);
    totalItems.value -= 1;
  } catch (error) {
    console.error("Error al añadir a la orden:", error);
    toast.error(error.response?.data?.message || "Error al procesar el pedido.");
  } finally {
    loading.value = false;
  }
};

const exportExcel = () => {
  console.log("Exportando a excel...");
};

watch(
  [page, itemsPerPage, sortBy, selectedLaboratory, selectProducts, excludeSupplierIds, searchQuery, withDiscount, hideRedundant, hideDuplicates, isColombia, tipoFiltracion, lapsoTiempo, stockFilter],
  () => {
    fetchOpportunities();
  },
  { deep: true },
);

onMounted(() => {
  fetchInitialData();
  fetchOpportunities();
});
</script>

<template>
  <div class="market-opportunities-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Filtros Estandarizados (AppFilterBase) -->
      <AppFilterBase
        v-model:search="searchQuery"
        :has-advanced-filters="hasActiveAdvancedFilters"
        search-placeholder="Buscar por nombre o barcode..."
        show-export
        class="py-1 mb-4"
        @clear="handleClearFilters"
        @export="exportExcel"
      >
        <template #advanced-filters>
          <!-- Selección de Producto(s) -->
          <VCol cols="12" sm="6" md="3">
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
          <VCol cols="12" sm="6" md="3">
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

          <!-- Excluir Proveedor(es) -->
          <VCol cols="12" sm="6" md="3">
            <VAutocomplete
              v-model="excludeSupplierIds"
              :items="suppliers"
              placeholder="Excluir Proveedor(es)"
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
              prepend-inner-icon="tabler-user-minus"
            />
          </VCol>

          <!-- Filtro Calcular Por -->
          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="tipoFiltracion"
              label="Calcular por"
              :items="[
                { title: 'Combinado', value: 'combinado' },
                { title: 'Solo Promedio', value: 'average' },
                { title: 'Solo Ventas', value: 'sales' },
              ]"
              hide-details
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-calculator"
            />
          </VCol>

          <!-- Lapso de Tiempo -->
          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="lapsoTiempo"
              label="Lapso de tiempo"
              :items="[
                { title: '15 Dias', value: '15 days' },
                { title: '1 Mes', value: '1 month' },
                { title: '3 Meses', value: '3 month' },
                { title: '6 Meses', value: '6 month' },
                { title: '12 Meses', value: '12 month' },
                { title: '18 Meses', value: '18 month' },
                { title: '24 Meses', value: '24 month' },
              ]"
              hide-details
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-calendar-time"
            />
          </VCol>

          <!-- Estado Stock -->
          <VCol cols="12" sm="6" md="3">
            <VSelect
              v-model="stockFilter"
              label="Estado Stock"
              :items="[
                { title: 'Todo', value: 'all' },
                { title: 'Fallas (Necesitan)', value: 'fallas' },
                { title: 'Exceso', value: 'exceso' },
              ]"
              hide-details
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-box"
            />
          </VCol>

          <!-- Toggles -->
          <VCol cols="12" md="6" class="d-flex align-center flex-wrap ga-4 py-0">
            <VSwitch
              v-model="withDiscount"
              label="Desc."
              hide-details
              density="compact"
              color="primary"
              inset
            />
            <VSwitch
              v-model="hideRedundant"
              label="Redundantes"
              hide-details
              density="compact"
              color="primary"
              inset
            />
            <VSwitch
              v-model="hideDuplicates"
              label="Mejor Oferta"
              hide-details
              density="compact"
              color="primary"
              inset
            />
          </VCol>
        </template>
      </AppFilterBase>

      <!-- Tabla de Resultados (Unified VCard) -->
      <VCard
        class="rounded-lg border shadow-sm overflow-hidden bg-surface mt-2"
      >
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
              <div class="d-flex align-center py-1" style="max-inline-size: 380px;">
                <div class="d-flex flex-column overflow-hidden">
                  <span
                    class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                    :title="item.product_name_inventory"
                  >
                    {{ item.product_name_inventory.toUpperCase() }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span
                      class="text-disabled truncate"
                      style="max-inline-size: 200px"
                      >{{
                        item.active_ingredient_inventory || "SIN INGREDIENTE"
                      }}</span
                    >
                    <span class="text-disabled mx-1">|</span>
                    <span
                      class="text-primary font-weight-black text-uppercase truncate"
                      style="max-inline-size: 250px"
                    >
                      {{ item.laboratory_name || "S/L" }} - {{ item.supplier_name }}
                    </span>
                  </div>
                </div>
              </div>
            </template>

            <template #item.historic_costs="{ item }">
              <div class="d-flex flex-column align-end text-right">
                <span class="text-xs text-error font-weight-bold leading-none mb-1" title="Costo Máximo Histórico">
                  {{ formatCurrency(item.effective_max_cost, "USD") }}
                </span>
                <span class="text-sm text-high-emphasis font-weight-black leading-none mb-1" title="Precio Actual">
                  {{ formatCurrency(item.inventory_unit_cost, "USD") }}
                </span>
                <span class="text-xs text-success font-weight-bold leading-none" title="Costo Mínimo Histórico">
                  {{ formatCurrency(item.effective_min_cost, "USD") }}
                </span>
              </div>
            </template>


            <template #item.unit_cost_usd="{ item }">
              <div class="d-flex flex-column align-end">
                <span class="text-sm font-weight-bold text-success">{{
                  formatCurrency(item.unit_cost_usd, "USD")
                }}</span>
              </div>
            </template>

            <template #item.total_sold_completed="{ item }">
              <span class="text-sm font-weight-bold">{{ item.total_sold_completed || 0 }}</span>
            </template>

            <template #item.lote_quantity="{ item }">
              <VChip
                :color="item.lote_quantity > 0 ? 'secondary' : 'error'"
                variant="tonal"
                size="small"
                class="font-weight-bold"
              >
                {{ item.lote_quantity || 0 }}
              </VChip>
            </template>

            <template #item.totalQuantityInAutoOrder="{ item }">
              <VChip
                :color="item.totalQuantityInAutoOrder > 0 ? 'warning' : 'grey'"
                variant="tonal"
                size="small"
                class="font-weight-bold"
              >
                {{ item.totalQuantityInAutoOrder || 0 }}
              </VChip>
            </template>

            <template #item.promedio_calculado="{ item }">
              <span class="text-sm font-weight-bold">{{ item.promedio_calculado || 0 }}</span>
            </template>


            <template #item.saving_percentage="{ item }">
              <VChip
                color="success"
                size="small"
                label
                class="font-weight-bold"
              >
                {{ item.saving_percentage }}%
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex align-center ga-2 justify-center">
                <VTextField
                  v-model="item.quantity_to_add"
                  type="number"
                  placeholder="Can."
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="quantity-input"
                  style="min-inline-size: 80px;"
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
          <div
            v-else-if="items.length === 0"
            class="text-center pa-8 text-disabled"
          >
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
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex align-center gap-1 mb-1">
                      <a
                        :href="'/inventory/traceability?q=' + item.product_id"
                        target="_blank"
                        class="text-decoration-none text-xs font-weight-black text-primary"
                      >
                        #{{ item.product_id }}
                      </a>
                      <div
                        class="text-subtitle-2 font-weight-black leading-tight truncate-2-lines"
                        :title="item.product_name_inventory"
                      >
                        {{ item.product_name_inventory }}
                      </div>
                    </div>
                    <div class="d-flex flex-column ga-1 text-super-xs text-disabled">
                        <span class="truncate">{{ item.active_ingredient_inventory }}</span>
                        <span class="text-primary font-weight-bold">{{ item.laboratory_name }}</span>
                    </div>
                  </div>
                  
                  <div class="text-right d-flex flex-column align-end ms-2">
                    <VChip 
                        color="success" 
                        variant="flat" 
                        size="small" 
                        class="font-weight-black mb-1"
                    >
                        {{ item.saving_percentage }}% AHORRO
                    </VChip>
                  </div>
                </div>

                <VDivider class="my-2 border-opacity-10" />

                <!-- Grid de Métricas de Análisis -->
                <div class="grid-mobile-info mb-3">
                  <div class="info-item">
                    <span class="label">Stock</span>
                    <span class="text-sm font-weight-bold" :class="item.lote_quantity > 0 ? 'text-secondary' : 'text-error'">
                        {{ item.lote_quantity || 0 }}
                    </span>
                  </div>
                  <div class="info-item">
                    <span class="label">AO</span>
                    <span class="text-sm font-weight-bold text-warning">
                        {{ item.totalQuantityInAutoOrder || 0 }}
                    </span>
                  </div>
                  <div class="info-item">
                    <span class="label">Ventas</span>
                    <span class="text-sm font-weight-bold">{{ item.total_sold_completed || 0 }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Prom.</span>
                    <span class="text-sm font-weight-bold">{{ item.promedio_calculado || 0 }}</span>
                  </div>
                </div>

                <!-- Detalles de Precios -->
                <VRow dense class="mb-3 bg-var-theme-background rounded pa-2 border-dashed-thin mx-0">
                  <VCol cols="4">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Histórico</div>
                    <div class="d-flex flex-column">
                      <span class="text-super-xs font-weight-bold text-error">
                        MAX: {{ formatCurrency(item.effective_max_cost, "USD") }}
                      </span>
                      <span class="text-xs font-weight-black text-high-emphasis my-0.5">
                        ACT: {{ formatCurrency(item.inventory_unit_cost, "USD") }}
                      </span>
                      <span class="text-super-xs font-weight-bold text-success">
                        MIN: {{ formatCurrency(item.effective_min_cost, "USD") }}
                      </span>
                    </div>
                  </VCol>
                  
                  <VCol cols="4" class="border-s border-dashed px-2">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Oferta</div>
                    <div class="text-sm font-weight-bold text-success">{{ formatCurrency(item.unit_cost_usd, "USD") }}</div>
                    <div class="text-super-xs text-primary truncate font-weight-black">{{ item.supplier_name }}</div>
                  </VCol>
                  
                  <VCol cols="4" class="border-s border-dashed px-2">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">% Ahorro</div>
                    <div class="text-sm font-weight-bold text-success">{{ item.saving_percentage }}%</div>
                  </VCol>
                </VRow>

                <!-- Acciones -->
                <div class="d-flex align-center justify-space-between bg-var-theme-background-soft pa-2 rounded-lg border border-dashed">
                  <span class="text-xs font-weight-black text-disabled">AÑADIR A ORDEN:</span>
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
                      size="32"
                      class="rounded-lg shadow-sm"
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
.gap-2 {
  gap: 8px !important;
}
.gap-4 {
  gap: 16px !important;
}

.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-medium-emphasis-opacity)
  ) !important;
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

.letter-spacing-tight {
  letter-spacing: -0.02em;
}
.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}

.shadow-soft {
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important;
}

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

.grid-mobile-info {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.info-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  background-color: rgba(var(--v-theme-on-surface), 0.02);
  padding: 8px;
  border-radius: 8px;
}

.info-item .label {
  font-size: 0.6rem;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 800;
  margin-bottom: 2px;
}
</style>
