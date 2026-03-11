<script setup>
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";

const skus = ref([]);
const loading = ref(false);
const totalItems = ref(0);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

// Filtros
const startDate = ref("");
const endDate = ref("");
const selectedLaboratory = ref(null);
const selectedGroup = ref(null);
const semaphoreFilter = ref(null);

const laboratories = ref([]);
const groups = ref([]);

const fetchFilters = async () => {
  try {
    const [labRes, grpRes] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/groups"),
    ]);
    laboratories.value = labRes.data;
    groups.value = grpRes.data;
  } catch (error) {
    console.error("Error cargando filtros:", error);
  }
};

const fetchReport = async () => {
  loading.value = true;
  
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    start_date: startDate.value,
    end_date: endDate.value,
    laboratory_id: selectedLaboratory.value,
    group_id: selectedGroup.value,
    semaphore: semaphoreFilter.value
  };

  // Limpiar nulos
  Object.keys(params).forEach(key => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const { data } = await axios.get("/bi/sku-margin", { params });
    skus.value = data.data;
    totalItems.value = data.total;
  } catch (error) {
    console.error("Error obteniendo reporte SKU:", error);
    toast.error("Hubo un error cargando el reporte SKU.");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchFilters();
  fetchReport();
});

const handleClearFilters = () => {
  startDate.value = "";
  endDate.value = "";
  selectedLaboratory.value = null;
  selectedGroup.value = null;
  semaphoreFilter.value = null;
};

// VDataTable Config
const headers = [
  { title: 'SKU/Barras', key: 'barcode', sortable: false },
  { title: 'Producto', key: 'product_name', sortable: true },
  { title: 'Laboratorio', key: 'laboratory_name', sortable: false },
  { title: 'Vendidos', key: 'total_sold', sortable: true },
  { title: 'Costo Unit.', key: 'current_cost', sortable: false },
  { title: 'Precio Lista', key: 'list_price', sortable: false },
  { title: 'M. Bruto', key: 'gross_margin_percent', sortable: true },
  { title: 'Descuento Prom.', key: 'discount_avg_percent', sortable: false },
  { title: 'M. Neto', key: 'net_margin_percent', sortable: false },
  { title: 'Mermas', key: 'loss_value', sortable: false },
  { title: 'M. Real %', key: 'real_margin_percent', sortable: true },
  { title: 'Estado', key: 'semaphore', sortable: false }
];

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if(options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = null;
    orderBy.value = null;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, startDate, endDate, selectedLaboratory, selectedGroup, semaphoreFilter],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchReport(), 500);
  },
  { deep: true }
);

const getSemaphoreColor = (status) => {
  const mapping = {
    'verde': 'success',
    'amarillo': 'warning',
    'rojo': 'error',
    'negro': 'dark'
  };
  return mapping[status] || 'default';
};

const formatPercent = (val) => {
  return Number(val).toFixed(2) + '%';
};
const formatMoney = (val) => {
  return '$' + Number(val).toFixed(2);
};
</script>

<template>
  <div>
    <h1 class="text-h4 font-weight-bold mb-4">Reporte Margen Real por SKU 💰</h1>
    <p class="text-body-1 mb-6 text-medium-emphasis">Descubre la rentabilidad real descontando mermas, vencidos y bonificaciones.</p>
    
    <VCard class="mb-6">
      <VCardTitle class="pb-0">Filtros de Análisis</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <AppDateTimePicker
              v-model="startDate"
              label="Fecha Inicio"
              placeholder="Seleccionar Fecha"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppDateTimePicker
              v-model="endDate"
              label="Fecha Fin"
              placeholder="Seleccionar Fecha"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppAutocomplete
              v-model="selectedLaboratory"
              :items="laboratories"
              item-title="name"
              item-value="id"
              label="Laboratorio / Proveedor"
              placeholder="Todos"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppSelect
              v-model="semaphoreFilter"
              :items="[{title: '✅ Rentable (>25%)', value: 'verde'}, {title: '⚠️ Medio (10-25%)', value: 'amarillo'}, {title: '🚨 Peligro (<10%)', value: 'rojo'}, {title: '🏴 Pérdidas (<0%)', value: 'negro'}]"
              label="Semáforo de Rentabilidad"
              placeholder="Ver todos"
              clearable
            />
          </VCol>
          <VCol cols="12" class="d-flex justify-end pt-0">
             <VBtn variant="tonal" color="secondary" @click="handleClearFilters">
               Limpiar Filtros
             </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VDataTableServer
        :items-per-page="itemsPerPage"
        :page="page"
        :headers="headers"
        :items="skus"
        :items-length="totalItems"
        :loading="loading"
        class="text-no-wrap"
        @update:options="updateTableOptions"
      >
        <template #item.product_name="{ item }">
          <span class="font-weight-medium text-primary">{{ item.product_name }}</span>
        </template>
        
        <template #item.current_cost="{ item }">
          {{ formatMoney(item.current_cost) }}
        </template>
        
        <template #item.list_price="{ item }">
          {{ formatMoney(item.list_price) }}
        </template>

        <template #item.gross_margin_percent="{ item }">
          <VChip size="small" variant="tonal" color="info">
            {{ formatPercent(item.gross_margin_percent) }}
          </VChip>
          <div class="text-caption text-disabled mt-1">{{ formatMoney(item.gross_margin_value) }}</div>
        </template>

        <template #item.discount_avg_percent="{ item }">
          <span class="text-error font-weight-bold">-{{ formatPercent(item.discount_avg_percent) }}</span>
        </template>

        <template #item.net_margin_percent="{ item }">
          <VChip size="small" variant="tonal" color="primary">
            {{ formatPercent(item.net_margin_percent) }}
          </VChip>
          <div class="text-caption text-disabled mt-1">{{ formatMoney(item.net_margin_value) }}</div>
        </template>
        
        <template #item.loss_value="{ item }">
          <span v-if="item.loss_value > 0" class="text-error">-${{ Number(item.loss_value).toFixed(2) }}</span>
          <span v-else class="text-disabled">$0.00</span>
        </template>

        <template #item.real_margin_percent="{ item }">
          <strong class="text-h6" :class="`text-${getSemaphoreColor(item.semaphore)}`">
             {{ formatPercent(item.real_margin_percent) }}
          </strong>
          <div class="text-caption mt-1">{{ formatMoney(item.real_margin_value) }} Total</div>
        </template>

        <template #item.semaphore="{ item }">
          <VChip
            :color="getSemaphoreColor(item.semaphore)"
            size="small"
            class="text-uppercase font-weight-bold"
          >
            {{ item.semaphore }}
          </VChip>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>

<style scoped>
.text-dark {
  color: #212121 !important;
}
</style>
