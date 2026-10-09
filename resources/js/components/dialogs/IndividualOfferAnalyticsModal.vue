<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  offer: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:modelValue"]);

const { mobile } = useDisplay();

const loading = ref(false);
const activeTab = ref("cross-selling");
const selectedSellerId = ref(null);
const analyticsData = ref(null);

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("es-ES", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

const formatCurrencyUSD = (amount) => {
  const val = parseFloat(amount) || 0;
  return `$${val.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const sellerOptions = computed(() => {
  const list = [{ id: null, name: "Todas las vendedoras" }];
  if (analyticsData.value?.available_sellers) {
    list.push(...analyticsData.value.available_sellers);
  }
  return list;
});

const fetchAnalytics = async () => {
  if (!props.offer?.id) return;

  loading.value = true;
  try {
    const params = {};
    if (selectedSellerId.value) {
      params.seller_id = selectedSellerId.value;
    }

    const response = await axios.get(`/tpv/promotions/individual/${props.offer.id}/analytics`, {
      params,
    });

    analyticsData.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error al cargar analítica de la oferta:", error);
    toast.error("No se pudo cargar la analítica de la oferta");
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.modelValue,
  (val) => {
    if (val && props.offer) {
      selectedSellerId.value = null;
      activeTab.value = "cross-selling";
      fetchAnalytics();
    } else {
      analyticsData.value = null;
    }
  }
);

watch(selectedSellerId, () => {
  if (isVisible.value) {
    fetchAnalytics();
  }
});

const kpis = computed(() => analyticsData.value?.kpis || {});
const crossSellingProducts = computed(() => analyticsData.value?.cross_selling_products || []);
const sellersBreakdown = computed(() => analyticsData.value?.sellers_breakdown || []);
const offerInfo = computed(() => analyticsData.value?.offer || props.offer || {});
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="960"
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
  >
    <VCard class="rounded-lg">
      <!-- Header -->
      <VCardTitle class="d-flex align-start justify-space-between pa-4 bg-surface border-b">
        <div class="d-flex flex-column min-width-0 flex-grow-1 me-2">
          <div class="d-flex align-center gap-2 flex-wrap mb-1">
            <span class="text-overline font-weight-black text-primary letter-spacing-1">
              ANALÍTICA DE OFERTA
            </span>
            <VChip
              size="x-small"
              color="success"
              variant="tonal"
              class="font-weight-black"
            >
              {{ offerInfo.discount_percent }}% OFF
            </VChip>
            <VChip
              size="x-small"
              color="info"
              variant="outlined"
              class="font-weight-bold"
            >
              ID Prod: {{ offerInfo.product_id || offerInfo.product?.id }}
            </VChip>
          </div>

          <h2 class="text-h6 font-weight-black text-uppercase text-truncate mb-1">
            {{ offerInfo.product_name || offerInfo.product?.name || "Oferta Individual" }}
          </h2>

          <div class="d-flex align-center flex-wrap gap-2 text-caption text-medium-emphasis">
            <span>{{ offerInfo.active_ingredient || offerInfo.product?.active_ingredient || "—" }}</span>
            <span>•</span>
            <span class="font-weight-bold text-uppercase">{{ offerInfo.laboratory_name || offerInfo.product?.laboratory?.name || "S/L" }}</span>
            <span>•</span>
            <span>Vigencia: {{ formatDate(offerInfo.start_date) }} al {{ formatDate(offerInfo.end_date) }}</span>
          </div>
        </div>

        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          density="comfortable"
          @click="isVisible = false"
        />
      </VCardTitle>

      <VDivider />

      <!-- Toolbar de Filtros -->
      <div class="px-4 py-3 bg-var-theme-background border-b d-flex flex-wrap align-center justify-space-between gap-2">
        <div class="d-flex align-center gap-2 flex-grow-1" style="max-width: 380px;">
          <VSelect
            v-model="selectedSellerId"
            :items="sellerOptions"
            item-title="name"
            item-value="id"
            label="Filtrar por Vendedora"
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="tabler-user"
            class="seller-filter-select"
            :disabled="loading"
          />
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            color="primary"
            size="small"
            prepend-icon="tabler-refresh"
            :loading="loading"
            @click="fetchAnalytics"
          >
            Actualizar
          </VBtn>
        </div>
      </div>

      <!-- Contenido Principal -->
      <VCardText class="pa-4">
        <VProgressLinear
          v-if="loading"
          indeterminate
          color="primary"
          class="mb-4 rounded"
        />

        <!-- Empty State si no hay ventas -->
        <AppEmptyState
          v-if="!loading && kpis.total_units_sold === 0"
          title="Sin ventas registradas"
          message="No se encontraron órdenes completadas para esta oferta en el rango de fechas seleccionado."
          icon="tabler-chart-off"
          class="my-6"
        />

        <div v-else>
          <!-- Grid de KPIs Principales -->
          <VRow class="mb-4" dense>
            <!-- KPI 1: Unidades & Clientes -->
            <VCol cols="12" sm="6" md="3">
              <VCard variant="outlined" class="pa-3 h-100 kpi-card border-primary-subtle">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                    Unidades Vendidas
                  </span>
                  <VAvatar color="primary" variant="tonal" size="28" rounded>
                    <VIcon icon="tabler-shopping-cart" size="16" />
                  </VAvatar>
                </div>
                <div class="text-h5 font-weight-black text-primary">
                  {{ kpis.total_units_sold || 0 }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1 d-flex justify-space-between">
                  <span>Clientes: <strong>{{ kpis.unique_clients_count || 0 }}</strong></span>
                  <span>Órdenes: <strong>{{ kpis.total_orders_count || 0 }}</strong></span>
                </div>
              </VCard>
            </VCol>

            <!-- KPI 2: Venta Cruzada -->
            <VCol cols="12" sm="6" md="3">
              <VCard variant="outlined" class="pa-3 h-100 kpi-card border-success-subtle">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                    % Venta Cruzada
                  </span>
                  <VAvatar color="success" variant="tonal" size="28" rounded>
                    <VIcon icon="tabler-arrows-cross" size="16" />
                  </VAvatar>
                </div>
                <div class="text-h5 font-weight-black text-success">
                  {{ kpis.cross_sell_percentage || 0 }}%
                </div>
                <VProgressLinear
                  :model-value="kpis.cross_sell_percentage || 0"
                  color="success"
                  height="4"
                  rounded
                  class="my-1-5"
                />
                <div class="text-caption text-medium-emphasis d-flex justify-space-between">
                  <span>Mixtas: <strong>{{ kpis.cross_sell_orders_count || 0 }}</strong></span>
                  <span>Solo oferta: <strong>{{ kpis.single_item_orders_count || 0 }}</strong></span>
                </div>
              </VCard>
            </VCol>

            <!-- KPI 3: Ticket Promedio -->
            <VCol cols="12" sm="6" md="3">
              <VCard variant="outlined" class="pa-3 h-100 kpi-card border-info-subtle">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                    Ticket Promedio
                  </span>
                  <VAvatar color="info" variant="tonal" size="28" rounded>
                    <VIcon icon="tabler-receipt" size="16" />
                  </VAvatar>
                </div>
                <div class="text-h5 font-weight-black text-info">
                  {{ formatCurrencyUSD(kpis.average_ticket_usd) }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1">
                  Total Órdenes: <strong>{{ formatCurrencyUSD(kpis.total_orders_amount_usd) }}</strong>
                </div>
              </VCard>
            </VCol>

            <!-- KPI 4: Ganancia Neta -->
            <VCol cols="12" sm="6" md="3">
              <VCard variant="outlined" class="pa-3 h-100 kpi-card border-warning-subtle">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                    Ganancia Oferta
                  </span>
                  <VAvatar color="warning" variant="tonal" size="28" rounded>
                    <VIcon icon="tabler-chart-pie" size="16" />
                  </VAvatar>
                </div>
                <div
                  class="text-h5 font-weight-black"
                  :class="kpis.offer_profit_usd >= 0 ? 'text-warning' : 'text-error'"
                >
                  {{ formatCurrencyUSD(kpis.offer_profit_usd) }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1 d-flex justify-space-between">
                  <span>Margen: <strong>{{ kpis.offer_profit_margin || 0 }}%</strong></span>
                  <span>Ahorro Clte: <strong>{{ formatCurrencyUSD(kpis.discount_savings_usd) }}</strong></span>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Pestañas Detalladas -->
          <VCard variant="outlined" class="rounded-lg overflow-hidden">
            <VTabs
              v-model="activeTab"
              color="primary"
              density="compact"
              class="border-b"
            >
              <VTab value="cross-selling" class="font-weight-bold text-caption">
                <VIcon icon="tabler-package" class="me-1" size="16" />
                Top Productos Cruzados ({{ crossSellingProducts.length }})
              </VTab>
              <VTab value="sellers" class="font-weight-bold text-caption">
                <VIcon icon="tabler-users" class="me-1" size="16" />
                Rendimiento por Vendedora ({{ sellersBreakdown.length }})
              </VTab>
            </VTabs>

            <VWindow v-model="activeTab">
              <!-- Tab 1: Cross-Selling Products -->
              <VWindowItem value="cross-selling">
                <VTable density="compact" hover class="text-no-wrap">
                  <thead>
                    <tr>
                      <th class="text-left font-weight-bold text-uppercase text-caption">Producto</th>
                      <th class="text-left font-weight-bold text-uppercase text-caption">Laboratorio</th>
                      <th class="text-center font-weight-bold text-uppercase text-caption">Veces en Orden</th>
                      <th class="text-end font-weight-bold text-uppercase text-caption">Cant. Vendida</th>
                      <th class="text-end font-weight-bold text-uppercase text-caption">Total USD</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="crossSellingProducts.length === 0">
                      <td colspan="5" class="text-center py-6 text-medium-emphasis">
                        No se registraron productos adicionales en las órdenes con esta oferta.
                      </td>
                    </tr>
                    <tr v-for="item in crossSellingProducts" :key="item.product_id">
                      <td>
                        <div class="d-flex flex-column py-1">
                          <span class="font-weight-bold text-body-2 text-uppercase">
                            {{ item.product_name }}
                          </span>
                          <span class="text-super-xs text-disabled">ID: #{{ item.product_id }}</span>
                        </div>
                      </td>
                      <td>
                        <VChip size="x-small" variant="tonal" color="secondary" class="font-weight-medium text-uppercase">
                          {{ item.laboratory_name }}
                        </VChip>
                      </td>
                      <td class="text-center">
                        <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
                          {{ item.times_bought_together }}
                        </VChip>
                      </td>
                      <td class="text-end font-weight-bold">
                        {{ item.total_quantity }}
                      </td>
                      <td class="text-end font-weight-black text-success">
                        {{ formatCurrencyUSD(item.total_amount_usd) }}
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VWindowItem>

              <!-- Tab 2: Sellers Breakdown -->
              <VWindowItem value="sellers">
                <VTable density="compact" hover class="text-no-wrap">
                  <thead>
                    <tr>
                      <th class="text-left font-weight-bold text-uppercase text-caption">Vendedora / Cajera</th>
                      <th class="text-center font-weight-bold text-uppercase text-caption">Unidades Oferta</th>
                      <th class="text-center font-weight-bold text-uppercase text-caption">Órdenes</th>
                      <th class="text-center font-weight-bold text-uppercase text-caption">% Venta Cruzada</th>
                      <th class="text-end font-weight-bold text-uppercase text-caption">Total Facturado USD</th>
                      <th class="text-end font-weight-bold text-uppercase text-caption">Ganancia Oferta USD</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="sellersBreakdown.length === 0">
                      <td colspan="6" class="text-center py-6 text-medium-emphasis">
                        No hay datos de vendedoras para esta oferta.
                      </td>
                    </tr>
                    <tr v-for="seller in sellersBreakdown" :key="seller.seller_id || seller.seller_name">
                      <td>
                        <div class="d-flex align-center gap-2 py-1">
                          <VAvatar size="26" color="primary" variant="tonal">
                            <VIcon icon="tabler-user" size="14" />
                          </VAvatar>
                          <span class="font-weight-bold text-body-2 text-uppercase">
                            {{ seller.seller_name }}
                          </span>
                        </div>
                      </td>
                      <td class="text-center font-weight-black text-primary">
                        {{ seller.units_sold }}
                      </td>
                      <td class="text-center font-weight-medium">
                        {{ seller.orders_count }}
                      </td>
                      <td class="text-center">
                        <VChip
                          size="small"
                          :color="seller.cross_sell_percentage >= 50 ? 'success' : 'warning'"
                          variant="tonal"
                          class="font-weight-bold"
                        >
                          {{ seller.cross_sell_percentage }}% ({{ seller.cross_sell_orders_count }})
                        </VChip>
                      </td>
                      <td class="text-end font-weight-bold">
                        {{ formatCurrencyUSD(seller.total_usd) }}
                      </td>
                      <td
                        class="text-end font-weight-black"
                        :class="seller.profit_usd >= 0 ? 'text-success' : 'text-error'"
                      >
                        {{ formatCurrencyUSD(seller.profit_usd) }}
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VWindowItem>
            </VWindow>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <!-- Footer -->
      <VCardActions class="pa-4 bg-surface justify-end">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isVisible = false"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
}

.my-1-5 {
  margin-top: 6px !important;
  margin-bottom: 6px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.kpi-card {
  background: rgb(var(--v-theme-surface));
  transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.border-primary-subtle {
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}

.border-success-subtle {
  border-color: rgba(var(--v-theme-success), 0.3) !important;
}

.border-info-subtle {
  border-color: rgba(var(--v-theme-info), 0.3) !important;
}

.border-warning-subtle {
  border-color: rgba(var(--v-theme-warning), 0.3) !important;
}

:deep(.v-table th) {
  background-color: rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
}
</style>
