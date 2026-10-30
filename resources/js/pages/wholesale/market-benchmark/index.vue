<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';

// ─── Estados y Pestañas ────────────────────────────────────────────────────────
const activeTab = ref('cheaper');
const loadingSummary = ref(false);
const loadingItems = ref(false);
const updatingPrice = ref(false);

const summary = ref({
  total_market_products: 0,
  our_active_evaluated_count: 0,
  cheaper_count: 0,
  cheaper_pct: 0,
  more_expensive_count: 0,
  more_expensive_pct: 0,
  avg_overprice_gap_pct: 0,
  uncovered_count: 0,
  uncovered_not_created_count: 0,
  uncovered_zero_stock_count: 0,
  competitiveness_index: 0,
});

const items = ref([]);
const totalItems = ref(0);
const page = ref(1);
const perPage = ref(15);
const search = ref('');
const coverageType = ref('all');

// Modal de Ajuste Rápido de Precio
const priceDialog = ref(false);
const selectedProduct = ref(null);
const newPriceUsd = ref(0);

// ─── Headers de Tabla según la Pestaña ─────────────────────────────────────────
const headers = computed(() => {
  const common = [
    { title: 'CÓDIGO DE BARRAS', key: 'barcode', sortable: false },
    { title: 'PRODUCTO', key: 'name', sortable: false },
    { title: 'LABORATORIO', key: 'laboratory_name', sortable: false },
  ];

  if (activeTab.value === 'cheaper') {
    return [
      ...common,
      { title: 'NUESTRO STOCK', key: 'our_stock', align: 'center', sortable: false },
      { title: 'NUESTRO COSTO', key: 'our_cost_usd', align: 'end', sortable: false },
      { title: 'NUESTRO PRECIO', key: 'our_price_usd', align: 'end', sortable: false },
      { title: 'MEJOR PRECIO MERCADO', key: 'best_market_price_usd', align: 'end', sortable: false },
      { title: 'VENTAJA ($ / %)', key: 'advantage', align: 'end', sortable: false },
      { title: 'COMPETIDORES', key: 'competitors_count', align: 'center', sortable: false },
    ];
  }

  if (activeTab.value === 'more_expensive') {
    return [
      ...common,
      { title: 'NUESTRO STOCK', key: 'our_stock', align: 'center', sortable: false },
      { title: 'NUESTRO PRECIO', key: 'our_price_usd', align: 'end', sortable: false },
      { title: 'MEJOR PRECIO MERCADO', key: 'best_market_price_usd', align: 'end', sortable: false },
      { title: 'PROMEDIO MERCADO', key: 'avg_market_price_usd', align: 'end', sortable: false },
      { title: 'SOBREPRECIO (%)', key: 'gap', align: 'end', sortable: false },
      { title: 'COMPETIDORES', key: 'competitors_count', align: 'center', sortable: false },
      { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false },
    ];
  }

  // Pestaña 'uncovered'
  return [
    ...common,
    { title: 'ESTADO COBERTURA', key: 'coverage_sub_type', align: 'center', sortable: false },
    { title: 'MEJOR PRECIO MERCADO', key: 'best_market_price_usd', align: 'end', sortable: false },
    { title: 'PROMEDIO MERCADO', key: 'avg_market_price_usd', align: 'end', sortable: false },
    { title: 'STOCK TOTAL MERCADO', key: 'market_total_stock', align: 'center', sortable: false },
    { title: 'DROGUERÍAS OFERTANTES', key: 'competitors_count', align: 'center', sortable: false },
  ];
});

// ─── Funciones de Consulta ────────────────────────────────────────────────────
const fetchSummary = async () => {
  loadingSummary.value = true;
  try {
    const response = await axios.get('/wholesale/market-benchmark/summary');
    if (response.data?.success) {
      summary.value = response.data.data;
    }
  } catch (error) {
    toast.error('Error al obtener métricas consolidadas de mercado.');
  } finally {
    loadingSummary.value = false;
  }
};

const fetchItems = async () => {
  loadingItems.value = true;
  try {
    const response = await axios.get('/wholesale/market-benchmark/items', {
      params: {
        segment: activeTab.value,
        search: search.value,
        coverage_type: activeTab.value === 'uncovered' ? coverageType.value : undefined,
        page: page.value,
        per_page: perPage.value,
      },
    });

    if (response.data?.success) {
      items.value = response.data.data || [];
      totalItems.value = response.data.meta?.total || 0;
    }
  } catch (error) {
    toast.error('Error al cargar la comparativa de mercado.');
  } finally {
    loadingItems.value = false;
  }
};

// ─── Modal de Ajuste de Precio ────────────────────────────────────────────────
const openPriceDialog = (item) => {
  selectedProduct.value = item;
  newPriceUsd.value = item.best_market_price_usd;
  priceDialog.value = true;
};

const saveNewPrice = async () => {
  if (!selectedProduct.value?.product_id || newPriceUsd.value <= 0) return;

  updatingPrice.value = true;
  try {
    const response = await axios.patch(
      `/wholesale/market-benchmark/products/${selectedProduct.value.product_id}/price`,
      { price_usd: newPriceUsd.value }
    );

    if (response.data?.success) {
      toast.success(response.data.message || 'Precio mayorista actualizado exitosamente.');
      priceDialog.value = false;
      await Promise.all([fetchSummary(), fetchItems()]);
    }
  } catch (error) {
    toast.error('Error al actualizar el precio mayorista.');
  } finally {
    updatingPrice.value = false;
  }
};

// ─── Watchers y Ciclo de Vida ─────────────────────────────────────────────────
watch(activeTab, () => {
  page.value = 1;
  fetchItems();
});

let debounceTimer = null;
watch(search, () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    page.value = 1;
    fetchItems();
  }, 400);
});

watch(coverageType, () => {
  page.value = 1;
  fetchItems();
});

onMounted(async () => {
  await Promise.all([fetchSummary(), fetchItems()]);
});
</script>

<template>
  <div class="wholesale-market-benchmark-container d-flex flex-column gap-6">
    <!-- Encabezado Principal -->
    <VCard class="pa-4 pa-sm-6 rounded-xl border elevation-0">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
            <VIcon icon="tabler-chart-arrows" size="26" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-black leading-tight mb-1 text-high-emphasis">
              Posicionamiento y Cobertura de Mercado
            </h1>
            <p class="text-caption text-disabled mb-0">
              Comparativa anónima de inventario propio contra todas las droguerías mayoristas registradas en el Master.
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-3">
          <VBtn
            variant="outlined"
            color="primary"
            prepend-icon="tabler-refresh"
            :loading="loadingSummary || loadingItems"
            @click="Promise.all([fetchSummary(), fetchItems()])"
          >
            Actualizar Mercado
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Fila 1: Tarjetas Resumen / KPIs -->
    <VRow class="match-height">
      <!-- 🟢 Más Baratos (Competitivos) -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-xl border elevation-0 h-100 bg-white">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-disabled text-uppercase">Más Baratos que el Mercado</span>
            <VAvatar color="success" variant="tonal" size="32" class="rounded-md">
              <VIcon icon="tabler-trending-down" size="18" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <span class="text-h4 font-weight-black text-success">{{ summary.cheaper_count }}</span>
            <span class="text-caption font-weight-bold text-success">({{ summary.cheaper_pct }}%)</span>
          </div>
          <p class="text-caption text-disabled mb-0">
            Productos con precio de venta inferior o igual a la mejor oferta del mercado.
          </p>
        </VCard>
      </VCol>

      <!-- 🟡 Más Caros (Fuera de Precio) -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-xl border elevation-0 h-100 bg-white">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-disabled text-uppercase">Más Caros que el Mercado</span>
            <VAvatar color="warning" variant="tonal" size="32" class="rounded-md">
              <VIcon icon="tabler-trending-up" size="18" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <span class="text-h4 font-weight-black text-warning">{{ summary.more_expensive_count }}</span>
            <span class="text-caption font-weight-bold text-warning">({{ summary.more_expensive_pct }}%)</span>
          </div>
          <p class="text-caption text-disabled mb-0">
            Sobreprecio promedio de <strong class="text-high-emphasis">+{{ summary.avg_overprice_gap_pct }}%</strong> frente a la mejor droguería.
          </p>
        </VCard>
      </VCol>

      <!-- 🔴 Sin Cobertura (Oportunidad) -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-xl border elevation-0 h-100 bg-white">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-disabled text-uppercase">Sin Cobertura Local</span>
            <VAvatar color="error" variant="tonal" size="32" class="rounded-md">
              <VIcon icon="tabler-alert-triangle" size="18" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <span class="text-h4 font-weight-black text-error">{{ summary.uncovered_count }}</span>
            <span class="text-caption text-disabled font-weight-bold">en mercado</span>
          </div>
          <p class="text-caption text-disabled mb-0">
            {{ summary.uncovered_not_created_count }} no creados y {{ summary.uncovered_zero_stock_count }} con stock en 0.
          </p>
        </VCard>
      </VCol>

      <!-- 🔵 Índice de Competitividad -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-xl border elevation-0 h-100 bg-white">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-disabled text-uppercase">Índice de Competitividad</span>
            <VAvatar color="info" variant="tonal" size="32" class="rounded-md">
              <VIcon icon="tabler-shield-check" size="18" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <span class="text-h4 font-weight-black text-primary">{{ summary.competitiveness_index }}%</span>
            <span class="text-caption text-disabled">de catálogo líder</span>
          </div>
          <VProgressLinear
            :model-value="summary.competitiveness_index"
            color="primary"
            height="6"
            rounded
            class="mt-2"
          />
        </VCard>
      </VCol>
    </VRow>

    <!-- Fila 2: Pestañas y Filtros -->
    <VCard class="rounded-xl border elevation-0">
      <VCardItem class="pb-2">
        <VTabs v-model="activeTab" color="primary">
          <VTab value="cheaper">
            <VIcon icon="tabler-check" class="me-2" color="success" />
            Más Baratos
            <VChip size="x-small" color="success" variant="tonal" class="ms-2 font-weight-bold">
              {{ summary.cheaper_count }}
            </VChip>
          </VTab>
          <VTab value="more_expensive">
            <VIcon icon="tabler-alert-circle" class="me-2" color="warning" />
            Más Caros
            <VChip size="x-small" color="warning" variant="tonal" class="ms-2 font-weight-bold">
              {{ summary.more_expensive_count }}
            </VChip>
          </VTab>
          <VTab value="uncovered">
            <VIcon icon="tabler-zoom-question" class="me-2" color="error" />
            Sin Cobertura
            <VChip size="x-small" color="error" variant="tonal" class="ms-2 font-weight-bold">
              {{ summary.uncovered_count }}
            </VChip>
          </VTab>
        </VTabs>
      </VCardItem>

      <VDivider />

      <!-- Barra de Filtros -->
      <VCardText class="py-4">
        <VRow dense align="center">
          <VCol cols="12" md="6">
            <VTextField
              v-model="search"
              prepend-inner-icon="tabler-search"
              placeholder="Buscar por código de barras, nombre o laboratorio..."
              density="compact"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>

          <VCol v-if="activeTab === 'uncovered'" cols="12" md="6">
            <VSelect
              v-model="coverageType"
              :items="[
                { title: 'Todos sin cobertura', value: 'all' },
                { title: 'No creados en catálogo local', value: 'not_created' },
                { title: 'Creados pero con Stock en 0', value: 'zero_stock' },
              ]"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="tabler-filter"
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Tabla de Datos Servidor -->
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="items"
        :items-length="totalItems"
        :loading="loadingItems"
        class="elevation-0 text-no-wrap"
        hover
        @update:options="fetchItems"
      >
        <!-- Código de Barras -->
        <template #item.barcode="{ item }">
          <div class="d-flex align-center gap-1">
            <VIcon icon="tabler-barcode" size="16" color="disabled" />
            <span class="font-mono font-weight-bold text-caption text-high-emphasis">
              {{ item.barcode || 'S/N' }}
            </span>
          </div>
        </template>

        <!-- Nombre -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="font-weight-bold text-body-2 text-high-emphasis">{{ item.name }}</span>
            <span class="text-caption text-disabled">{{ item.laboratory_name }}</span>
          </div>
        </template>

        <!-- Nuestro Stock -->
        <template #item.our_stock="{ item }">
          <VChip
            size="small"
            :color="item.our_stock > 0 ? 'success' : 'error'"
            variant="tonal"
            class="font-weight-bold"
          >
            {{ Number(item.our_stock).toLocaleString('es-VE') }} und
          </VChip>
        </template>

        <!-- Costos y Precios -->
        <template #item.our_cost_usd="{ item }">
          <span class="font-mono text-body-2 text-disabled">${{ item.our_cost_usd?.toFixed(2) }}</span>
        </template>

        <template #item.our_price_usd="{ item }">
          <span class="font-mono font-weight-bold text-body-2 text-high-emphasis">${{ item.our_price_usd?.toFixed(2) }}</span>
        </template>

        <template #item.best_market_price_usd="{ item }">
          <span class="font-mono font-weight-bold text-body-2 text-primary">${{ item.best_market_price_usd?.toFixed(2) }}</span>
        </template>

        <template #item.avg_market_price_usd="{ item }">
          <span class="font-mono text-caption text-disabled">${{ item.avg_market_price_usd?.toFixed(2) }}</span>
        </template>

        <!-- Ventaja (Más Baratos) -->
        <template #item.advantage="{ item }">
          <div class="d-flex flex-column align-end">
            <VChip size="small" color="success" variant="flat" class="font-weight-bold mb-1">
              -{{ item.price_diff_pct?.toFixed(1) }}%
            </VChip>
            <span class="text-caption text-success font-weight-bold font-mono">
              ${{ item.price_diff_usd?.toFixed(2) }} menos
            </span>
          </div>
        </template>

        <!-- Brecha (Más Caros) -->
        <template #item.gap="{ item }">
          <div class="d-flex flex-column align-end">
            <VChip size="small" color="warning" variant="flat" class="font-weight-bold mb-1">
              +{{ item.price_diff_pct?.toFixed(1) }}%
            </VChip>
            <span class="text-caption text-warning font-weight-bold font-mono">
              ${{ item.price_diff_usd?.toFixed(2) }} sobre mercado
            </span>
          </div>
        </template>

        <!-- Estado de Cobertura -->
        <template #item.coverage_sub_type="{ item }">
          <VChip
            v-if="item.coverage_sub_type === 'not_created'"
            size="small"
            color="error"
            variant="tonal"
            class="font-weight-bold"
          >
            No Creado en Catálogo
          </VChip>
          <VChip
            v-else
            size="small"
            color="warning"
            variant="tonal"
            class="font-weight-bold"
          >
            Agotado (Stock 0)
          </VChip>
        </template>

        <!-- Competidores en Mercado -->
        <template #item.competitors_count="{ item }">
          <VTooltip text="Droguerías competidoras que ofrecen este código de barras" location="top">
            <template #activator="{ props: tooltipProps }">
              <VChip v-bind="tooltipProps" size="small" color="secondary" variant="outlined" class="font-weight-bold">
                <VIcon icon="tabler-building-store" size="14" class="me-1" />
                {{ item.competitors_count }} droguerías
              </VChip>
            </template>
          </VTooltip>
        </template>

        <!-- Stock Total Mercado -->
        <template #item.market_total_stock="{ item }">
          <span class="font-mono text-caption text-high-emphasis">
            {{ Number(item.market_total_stock).toLocaleString('es-VE') }} und
          </span>
        </template>

        <!-- Acciones (Pestaña Más Caros) -->
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-tag"
            @click="openPriceDialog(item)"
          >
            Ajustar
          </VBtn>
        </template>

        <!-- Estado Vacío -->
        <template #no-data>
          <div class="pa-8 text-center text-disabled">
            <VIcon icon="tabler-mood-empty" size="48" class="mb-2" />
            <p class="text-body-1 font-weight-bold mb-0">No se encontraron productos en este segmento.</p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Diálogo de Ajuste Rápido de Precio -->
    <VDialog v-model="priceDialog" max-width="480">
      <VCard class="rounded-xl pa-2">
        <VCardTitle class="d-flex align-center justify-space-between pt-4 px-4">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-tag" color="primary" />
            <span class="text-h6 font-weight-bold">Ajustar Precio Mayorista</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="priceDialog = false" />
        </VCardTitle>

        <VCardText class="px-4 py-2">
          <div class="mb-4">
            <p class="text-body-2 font-weight-bold mb-1">{{ selectedProduct?.name }}</p>
            <span class="text-caption text-disabled font-mono">Cód: {{ selectedProduct?.barcode }}</span>
          </div>

          <VCard variant="tonal" color="secondary" class="pa-3 mb-4 rounded-lg">
            <div class="d-flex justify-space-between text-caption mb-1">
              <span>Nuestro Costo Base:</span>
              <strong class="font-mono">${{ selectedProduct?.our_cost_usd?.toFixed(2) }}</strong>
            </div>
            <div class="d-flex justify-space-between text-caption mb-1">
              <span>Precio Actual:</span>
              <strong class="font-mono">${{ selectedProduct?.our_price_usd?.toFixed(2) }}</strong>
            </div>
            <div class="d-flex justify-space-between text-caption text-primary">
              <span>Mejor Precio Mercado:</span>
              <strong class="font-mono">${{ selectedProduct?.best_market_price_usd?.toFixed(2) }}</strong>
            </div>
          </VCard>

          <VTextField
            v-model.number="newPriceUsd"
            label="Nuevo Precio Mayorista (USD)"
            type="number"
            step="0.01"
            min="0.01"
            variant="outlined"
            prepend-inner-icon="tabler-currency-dollar"
            class="mb-2"
          />

          <VAlert
            v-if="newPriceUsd < (selectedProduct?.our_cost_usd || 0)"
            type="warning"
            density="compact"
            variant="tonal"
            class="text-caption"
          >
            Atención: El nuevo precio está por debajo de nuestro costo base (${{ selectedProduct?.our_cost_usd?.toFixed(2) }}).
          </VAlert>
        </VCardText>

        <VCardActions class="px-4 pb-4">
          <VSpacer />
          <VBtn variant="outlined" color="secondary" @click="priceDialog = false">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            :loading="updatingPrice"
            :disabled="newPriceUsd <= 0"
            @click="saveNewPrice"
          >
            Guardar Precio
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
