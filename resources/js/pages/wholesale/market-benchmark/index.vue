<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';

// ─── Estados y Pestañas ────────────────────────────────────────────────────────
const activeTab = ref('cheaper');
const loadingSummary = ref(false);
const loadingItems = ref(false);
const updatingPrice = ref(false);
const copyingSummary = ref(false);

const summary = ref({
  total_market_products: 0,
  our_active_evaluated_count: 0,
  comparable_total: 0,
  cheaper_count: 0,
  cheaper_pct: 0,
  more_expensive_count: 0,
  more_expensive_pct: 0,
  avg_overprice_gap_pct: 0,
  median_overprice_gap_pct: 0,
  uncovered_count: 0,
  uncovered_not_created_count: 0,
  uncovered_zero_stock_count: 0,
  high_demand_uncovered_count: 0,
  competitiveness_index: 0,
});

const items = ref([]);
const totalItems = ref(0);
const page = ref(1);
const perPage = ref(10);
const search = ref('');
const coverageType = ref('all');
const onlyMultiCompetitors = ref(true); // Por defecto >= 2 competidores en Más caros
const onlyHighDemand = ref(false); // >= 3 competidores en Sin cobertura

// Modal de Ajuste Rápido de Precio
const priceDialog = ref(false);
const selectedProduct = ref(null);
const newPriceUsd = ref(0);

// ─── Headers de Tabla según la Pestaña ─────────────────────────────────────────
const headers = computed(() => {
  if (activeTab.value === 'cheaper') {
    return [
      { title: 'PRODUCTO', key: 'name', sortable: false, minWidth: '320px' },
      { title: 'NUESTRO PRECIO', key: 'our_price_usd', align: 'end', sortable: false, width: '130px' },
      { title: 'MEJOR PRECIO MERCADO', key: 'best_market_price_usd', align: 'end', sortable: false, width: '160px' },
      { title: 'VENTAJA (%)', key: 'advantage', align: 'end', sortable: false, width: '120px' },
      { title: 'COMPETIDORES', key: 'competitors_count', align: 'center', sortable: false, width: '130px' },
      { title: 'STOCK', key: 'our_stock', align: 'center', sortable: false, width: '110px' },
    ];
  }

  if (activeTab.value === 'more_expensive') {
    return [
      { title: 'PRODUCTO', key: 'name', sortable: false, minWidth: '300px' },
      { title: 'NUESTRO PRECIO', key: 'our_price_usd', align: 'end', sortable: false, width: '120px' },
      { title: 'MEJOR PRECIO MERCADO', key: 'best_market_price_usd', align: 'end', sortable: false, width: '150px' },
      { title: 'SOBREPRECIO (%)', key: 'gap', align: 'end', sortable: false, width: '160px' },
      { title: 'DIFERENCIA $', key: 'price_diff_usd', align: 'end', sortable: false, width: '120px' },
      { title: 'COMPETIDORES', key: 'competitors_count', align: 'center', sortable: false, width: '120px' },
      { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '110px' },
    ];
  }

  // Pestaña 'uncovered'
  return [
    { title: 'PRODUCTO', key: 'name', sortable: false, minWidth: '360px' },
    { title: 'MEJOR PRECIO MERCADO', key: 'best_market_price_usd', align: 'end', sortable: false, width: '180px' },
    { title: 'DROGUERÍAS OFERTANTES', key: 'competitors_count', align: 'center', sortable: false, width: '180px' },
    { title: 'STOCK TOTAL MERCADO', key: 'market_total_stock', align: 'center', sortable: false, width: '180px' },
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
    const params = {
      segment: activeTab.value,
      search: search.value,
      page: page.value,
      per_page: perPage.value,
    };

    if (activeTab.value === 'uncovered') {
      params.coverage_type = coverageType.value;
      if (onlyHighDemand.value) {
        params.min_competitors = 3;
      }
    } else if (activeTab.value === 'more_expensive') {
      if (onlyMultiCompetitors.value) {
        params.min_competitors = 2;
      }
    }

    const response = await axios.get('/wholesale/market-benchmark/items', { params });

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

// ─── Copiar Resumen Ejecutivo (Prioridad 1 - H) ───────────────────────────────
const copyExecutiveSummary = async () => {
  copyingSummary.value = true;
  try {
    const [cheaperRes, moreExpRes] = await Promise.all([
      axios.get('/wholesale/market-benchmark/items', { params: { segment: 'cheaper', per_page: 5, page: 1 } }),
      axios.get('/wholesale/market-benchmark/items', { params: { segment: 'more_expensive', per_page: 5, page: 1, min_competitors: 2 } }),
    ]);

    const topCheaper = cheaperRes.data?.data || [];
    const topExpensive = moreExpRes.data?.data || [];

    let text = `📊 RESUMEN EJECUTIVO: POSICIONAMIENTO DE MERCADO\n`;
    text += `==================================================\n`;
    text += `De ${summary.value.comparable_total || 0} productos comparables con stock:\n`;
    text += `• Con el mejor precio: ${summary.value.cheaper_count} (${summary.value.cheaper_pct}%)\n`;
    text += `• Por encima del mercado: ${summary.value.more_expensive_count} (${summary.value.more_expensive_pct}%)\n`;
    text += `• Mediana de sobreprecio: +${summary.value.median_overprice_gap_pct}%\n`;
    text += `• Alta demanda no cubierta (>=3 competidores con stock): ${summary.value.high_demand_uncovered_count || 0}\n\n`;

    text += `🟢 TOP 5 CON MAYOR VENTAJA:\n`;
    if (topCheaper.length === 0) {
      text += `  (No hay productos en este segmento)\n`;
    } else {
      topCheaper.forEach((item, idx) => {
        text += `  ${idx + 1}. ${item.name} | Propio: $${item.our_price_usd?.toFixed(2)} vs Mercado: $${item.best_market_price_usd?.toFixed(2)} (-${item.price_diff_pct?.toFixed(1)}%)\n`;
      });
    }

    text += `\n🔴 TOP 5 CON MAYOR SOBREPRECIO (>=2 competidores):\n`;
    if (topExpensive.length === 0) {
      text += `  (No hay productos en este segmento)\n`;
    } else {
      topExpensive.forEach((item, idx) => {
        text += `  ${idx + 1}. ${item.name} | Propio: $${item.our_price_usd?.toFixed(2)} vs Mercado: $${item.best_market_price_usd?.toFixed(2)} (+${item.price_diff_pct?.toFixed(1)}% | Dif: +$${item.price_diff_usd?.toFixed(2)})\n`;
      });
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
      toast.success('Resumen ejecutivo copiado al portapapeles.');
    } else {
      toast.info('No se pudo acceder al portapapeles del navegador.');
    }
  } catch (error) {
    toast.error('Error al generar el resumen para copiar.');
  } finally {
    copyingSummary.value = false;
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

watch(onlyMultiCompetitors, () => {
  page.value = 1;
  fetchItems();
});

watch(onlyHighDemand, () => {
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
              Comparativa anónima de inventario propio frente a droguerías mayoristas del mercado.
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <!-- Botón Copiar Resumen (Prioridad 1 - H) -->
          <VBtn
            variant="tonal"
            color="primary"
            prepend-icon="tabler-copy"
            :loading="copyingSummary"
            @click="copyExecutiveSummary"
          >
            Copiar Resumen
          </VBtn>

          <VBtn
            variant="outlined"
            color="primary"
            prepend-icon="tabler-refresh"
            :loading="loadingSummary || loadingItems"
            @click="Promise.all([fetchSummary(), fetchItems()])"
          >
            Actualizar
          </VBtn>
        </div>
      </div>

      <!-- Frase ejecutiva calculada (Prioridad 0 - E) -->
      <div class="text-body-2 text-high-emphasis mt-4 pt-3 border-t">
        <template v-if="summary.comparable_total > 0">
          De <strong>{{ summary.comparable_total }}</strong> productos comparables con stock, tienes el mejor precio en
          <strong class="text-success">{{ summary.cheaper_count }} ({{ summary.cheaper_pct }}%)</strong> y estás por encima en
          <strong class="text-warning">{{ summary.more_expensive_count }} ({{ summary.more_expensive_pct }}%)</strong>.
        </template>
        <template v-else>
          <span class="text-disabled">Catálogo sin productos con stock activo cruzados con el mercado.</span>
        </template>
      </div>

      <!-- Barra Apilada Horizontal: Posición de Precio (Prioridad 1 - G) -->
      <div v-if="summary.comparable_total > 0" class="mt-3">
        <div class="d-flex align-center justify-space-between text-caption mb-1">
          <span class="text-disabled font-weight-bold">Posición de precio en catálogo activo</span>
          <div class="d-flex align-center gap-3 font-mono">
            <span class="text-success font-weight-bold">● Mejor precio: {{ summary.cheaper_pct }}%</span>
            <span class="text-warning font-weight-bold">● Por encima: {{ summary.more_expensive_pct }}%</span>
          </div>
        </div>
        <div class="position-relative w-100 rounded-pill overflow-hidden bg-light" style="height: 8px; display: flex;">
          <div
            class="h-100 bg-success"
            :style="{ width: `${summary.cheaper_pct}%`, transition: 'width 0.4s ease' }"
          />
          <div
            class="h-100 bg-warning"
            :style="{ width: `${summary.more_expensive_pct}%`, transition: 'width 0.4s ease' }"
          />
        </div>
      </div>
    </VCard>

    <!-- Fila: 3 Tarjetas Resumen / KPIs Ejecutivos (Prioridad 0 - E) -->
    <VRow class="match-height">
      <!-- 🟢 1. Con el Mejor Precio -->
      <VCol cols="12" md="4">
        <VCard class="pa-4 rounded-xl border elevation-0 h-100 bg-white">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-disabled text-uppercase">Con el Mejor Precio</span>
            <VAvatar color="success" variant="tonal" size="32" class="rounded-md">
              <VIcon icon="tabler-award" size="18" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <span class="text-h4 font-weight-black text-success">{{ summary.cheaper_count }}</span>
            <span class="text-caption font-weight-bold text-success font-mono">({{ summary.cheaper_pct }}%)</span>
          </div>
          <p class="text-caption text-disabled mb-0">
            Productos con precio líder o igual a la mejor oferta del mercado con stock disponible.
          </p>
        </VCard>
      </VCol>

      <!-- 🟡 2. Por Encima del Mejor Precio -->
      <VCol cols="12" md="4">
        <VCard class="pa-4 rounded-xl border elevation-0 h-100 bg-white">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-disabled text-uppercase">Por Encima del Mejor Precio</span>
            <VAvatar color="warning" variant="tonal" size="32" class="rounded-md">
              <VIcon icon="tabler-trending-up" size="18" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <span class="text-h4 font-weight-black text-warning">{{ summary.more_expensive_count }}</span>
            <span class="text-caption font-weight-bold text-warning font-mono">({{ summary.more_expensive_pct }}%)</span>
          </div>
          <p class="text-caption text-disabled mb-0">
            Mediana de sobreprecio: <strong class="text-high-emphasis font-mono">+{{ summary.median_overprice_gap_pct }}%</strong> frente al mejor competidor.
          </p>
        </VCard>
      </VCol>

      <!-- 🔴 3. Muy Ofertados que no Tienes -->
      <VCol cols="12" md="4">
        <VCard class="pa-4 rounded-xl border elevation-0 h-100 bg-white">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-disabled text-uppercase">Muy Ofertados que no Tienes</span>
            <VAvatar color="error" variant="tonal" size="32" class="rounded-md">
              <VIcon icon="tabler-sparkles" size="18" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <span class="text-h4 font-weight-black text-error">{{ summary.high_demand_uncovered_count }}</span>
            <span class="text-caption text-disabled font-weight-bold">alta demanda</span>
          </div>
          <p class="text-caption text-disabled mb-0">
            Productos con 3 o más droguerías ofertando y stock activo en el mercado sin stock propio.
          </p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Fila: Pestañas y Filtros -->
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
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" :md="activeTab === 'cheaper' ? 12 : 6">
            <VTextField
              v-model="search"
              prepend-inner-icon="tabler-search"
              placeholder="Buscar producto..."
              density="compact"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>

          <!-- Filtro específico para Más Caros -->
          <VCol v-if="activeTab === 'more_expensive'" cols="12" md="6" class="d-flex align-center justify-md-end">
            <VSwitch
              v-model="onlyMultiCompetitors"
              label="Solo con 2+ droguerías competidoras"
              density="compact"
              color="warning"
              hide-details
            />
          </VCol>

          <!-- Filtros específicos para Sin Cobertura -->
          <VCol v-if="activeTab === 'uncovered'" cols="12" md="6" class="d-flex flex-wrap align-center gap-3">
            <VSelect
              v-model="coverageType"
              :items="[
                { title: 'Todos sin cobertura', value: 'all' },
                { title: 'No creados en catálogo', value: 'not_created' },
                { title: 'Agotados (Stock en 0)', value: 'zero_stock' },
              ]"
              density="compact"
              variant="outlined"
              hide-details
              style="min-width: 220px;"
            />
            <VSwitch
              v-model="onlyHighDemand"
              label="Solo alta demanda (>= 3 droguerías)"
              density="compact"
              color="error"
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Tabla de Datos Servidor (Compacta, Encabezado Fijo, Sin Scroll Horizontal) -->
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :items-per-page-options="[10, 25, 50]"
        :headers="headers"
        :items="items"
        :items-length="totalItems"
        :loading="loadingItems"
        density="compact"
        fixed-header
        hover
        class="elevation-0 text-no-wrap"
        style="max-height: 520px;"
        @update:options="fetchItems"
      >
        <!-- Producto en Una Sola Línea con Tooltip -->
        <template #item.name="{ item }">
          <div class="d-flex align-center py-1">
            <VTooltip location="top" :text="item.name">
              <template #activator="{ props: tooltipProps }">
                <span
                  v-bind="tooltipProps"
                  class="text-truncate text-body-2 font-weight-medium text-high-emphasis cursor-pointer"
                  style="max-width: 360px; display: inline-block;"
                >
                  {{ item.name }}
                </span>
              </template>
            </VTooltip>
          </div>
        </template>

        <!-- Precios -->
        <template #item.our_price_usd="{ item }">
          <span class="font-mono font-weight-bold text-body-2 text-high-emphasis">
            ${{ item.our_price_usd?.toFixed(2) }}
          </span>
        </template>

        <template #item.best_market_price_usd="{ item }">
          <span class="font-mono font-weight-bold text-body-2 text-primary">
            ${{ item.best_market_price_usd?.toFixed(2) }}
          </span>
        </template>

        <!-- Ventaja (Más Baratos) -->
        <template #item.advantage="{ item }">
          <VChip size="small" color="success" variant="flat" class="font-weight-bold font-mono">
            -{{ item.price_diff_pct?.toFixed(1) }}%
          </VChip>
        </template>

        <!-- Stock Propio (Más Baratos) -->
        <template #item.our_stock="{ item }">
          <span class="font-mono text-caption font-weight-bold text-high-emphasis">
            {{ Number(item.our_stock).toLocaleString('es-VE') }} und
          </span>
        </template>

        <!-- Sobreprecio (Más Caros) -->
        <template #item.gap="{ item }">
          <VChip
            v-if="item.price_diff_pct > 100"
            size="small"
            color="error"
            variant="flat"
            class="font-weight-bold font-mono"
          >
            <VIcon icon="tabler-alert-triangle" size="13" class="me-1" />
            Revisar dato (+{{ item.price_diff_pct?.toFixed(1) }}%)
          </VChip>
          <VChip
            v-else
            size="small"
            color="warning"
            variant="flat"
            class="font-weight-bold font-mono"
          >
            +{{ item.price_diff_pct?.toFixed(1) }}%
          </VChip>
        </template>

        <!-- Diferencia en Dólares (Más Caros) -->
        <template #item.price_diff_usd="{ item }">
          <span class="font-mono text-body-2 font-weight-bold text-warning">
            +${{ item.price_diff_usd?.toFixed(2) }}
          </span>
        </template>

        <!-- Competidores / Droguerías Ofertantes -->
        <template #item.competitors_count="{ item }">
          <VChip size="small" color="secondary" variant="tonal" class="font-weight-bold">
            {{ item.competitors_count }} {{ item.competitors_count === 1 ? 'droguería' : 'droguerías' }}
          </VChip>
        </template>

        <!-- Stock Total Mercado (Sin Cobertura) -->
        <template #item.market_total_stock="{ item }">
          <span class="font-mono text-caption text-high-emphasis font-weight-bold">
            {{ Number(item.market_total_stock).toLocaleString('es-VE') }} und
          </span>
        </template>

        <!-- Acciones (Más Caros: Ajustar Precio) -->
        <template #item.actions="{ item }">
          <VBtn
            size="x-small"
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
            <p class="text-body-2 font-weight-bold mb-1 text-truncate">{{ selectedProduct?.name }}</p>
          </div>

          <VCard variant="tonal" color="secondary" class="pa-3 mb-4 rounded-lg">
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
            v-if="selectedProduct?.our_cost_usd && newPriceUsd < selectedProduct.our_cost_usd"
            type="warning"
            density="compact"
            variant="tonal"
            class="text-caption"
          >
            Atención: El nuevo precio está por debajo del costo base (${{ selectedProduct.our_cost_usd.toFixed(2) }}).
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

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
