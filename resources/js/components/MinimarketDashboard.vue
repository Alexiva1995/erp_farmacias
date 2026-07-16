<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import VueApexCharts from 'vue3-apexcharts';
import { useTheme } from 'vuetify';
import { useRouter } from 'vue-router';

const vuetifyTheme = useTheme();
const router = useRouter();

const activePreset = ref('month');

const setDatePreset = (preset) => {
  activePreset.value = preset;
  const today = new Date();
  if (preset === 'today') {
    startDate.value = today.toISOString().substr(0, 10);
    endDate.value = today.toISOString().substr(0, 10);
  } else if (preset === 'yesterday') {
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    startDate.value = yesterday.toISOString().substr(0, 10);
    endDate.value = yesterday.toISOString().substr(0, 10);
  } else if (preset === 'week') {
    const current = new Date();
    const day = current.getDay();
    const diff = current.getDate() - day + (day === 0 ? -6 : 1);
    const monday = new Date(current.setDate(diff));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    startDate.value = monday.toISOString().substr(0, 10);
    endDate.value = sunday.toISOString().substr(0, 10);
  }
};

const replenishWithIa = (item) => {
  if (item.supplier_id) {
    router.push({
      path: '/suppliers/auto-replenishment',
      query: { supplier_id: item.supplier_id }
    });
  } else {
    router.push({
      path: '/suppliers/auto-replenishment'
    });
  }
};

const stats = ref({
  general_stats: {
    total_sales: 0,
    pos_sales: 0,
    web_sales: 0,
    total_profit: 0,
    pos_transactions: 0,
    web_transactions: 0,
    total_transactions: 0
  },
  payment_distribution: [],
  category_sales: [],
  low_stock: [],
  recent_web_orders: []
});

const loading = ref(true);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));

const fetchMinimarketStats = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get('/dashboard/minimarket-stats', {
      params: {
        start_date: startDate.value,
        end_date: endDate.value
      }
    });
    stats.value = data;
  } catch (error) {
    console.error('Error al cargar métricas del Minimarket:', error);
  } finally {
    loading.value = false;
  }
};

watch([startDate, endDate], () => {
  fetchMinimarketStats();
});

onMounted(() => {
  fetchMinimarketStats();
});

// Formateadores
const formatUSD = (val) => {
  return new Intl.NumberFormat('es-US', { style: 'currency', currency: 'USD' }).format(val);
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const getStatusColor = (status) => {
  const colors = {
    'Pending': 'warning',
    'Paid': 'success',
    'Shipped': 'info',
    'Delivered': 'success',
    'Cancelled': 'error'
  };
  return colors[status] || 'secondary';
};

const getStatusText = (status) => {
  const texts = {
    'Pending': 'Pendiente',
    'Paid': 'Pagado',
    'Shipped': 'Enviado',
    'Delivered': 'Entregado',
    'Cancelled': 'Cancelado'
  };
  return texts[status] || status;
};

// Configuración de Gráfico de Donas (Métodos de Pago)
const paymentChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value;
  return {
    chart: {
      type: 'donut',
      parentHeightOffset: 0,
      toolbar: { show: false }
    },
    labels: stats.value.payment_distribution.map(i => i.label),
    colors: ['#7367F0', '#28C76F', '#FF9F43', '#00CFE8', '#82868B'],
    stroke: {
      width: 4,
      colors: [currentTheme.colors.surface]
    },
    legend: {
      show: true,
      position: 'bottom',
      labels: {
        colors: currentTheme.colors['on-surface']
      }
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => `${val.toFixed(0)}%`
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            name: {
              show: true,
              fontSize: '14px',
              fontFamily: 'Public Sans',
              color: currentTheme.colors['on-surface-variant'],
              offsetY: -10
            },
            value: {
              show: true,
              fontSize: '20px',
              fontFamily: 'Public Sans',
              fontWeight: '600',
              color: currentTheme.colors['on-surface'],
              offsetY: 10,
              formatter: (val) => formatUSD(val)
            },
            total: {
              show: true,
              label: 'Total Pago',
              fontSize: '13px',
              fontFamily: 'Public Sans',
              color: currentTheme.colors['on-surface-variant'],
              formatter: (w) => {
                return formatUSD(stats.value.payment_distribution.reduce((acc, curr) => acc + curr.value, 0));
              }
            }
          }
        }
      }
    }
  };
});

const paymentChartSeries = computed(() => {
  return stats.value.payment_distribution.map(i => i.value);
});

// Configuración de Gráfico de Barras (Ventas por Categoría)
const categoryChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value;
  return {
    chart: {
      type: 'bar',
      toolbar: { show: false }
    },
    plotOptions: {
      bar: {
        horizontal: true,
        barHeight: '60%',
        borderRadius: 4
      }
    },
    colors: ['#7367F0'],
    dataLabels: {
      enabled: true,
      formatter: (val) => formatUSD(val),
      style: {
        colors: ['#fff']
      }
    },
    grid: {
      borderColor: currentTheme.colors['border-color']
    },
    xaxis: {
      categories: stats.value.category_sales.map(i => i.name),
      labels: {
        style: { colors: currentTheme.colors['on-surface-variant'] },
        formatter: (val) => formatUSD(val)
      }
    },
    yaxis: {
      labels: {
        style: { colors: currentTheme.colors['on-surface-variant'] }
      }
    }
  };
});

const categoryChartSeries = computed(() => {
  return [{
    name: 'Ventas',
    data: stats.value.category_sales.map(i => i.value)
  }];
});
</script>

<template>
  <div>
    <!-- Filtros de Fecha -->
    <VCard class="mb-6 pa-4">
      <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4">
        <div>
          <h4 class="text-h5 font-weight-bold mb-1">Dashboard Consolidado</h4>
          <span class="text-caption text-medium-emphasis">Ventas POS y Tienda Virtual</span>
        </div>
        <div class="d-flex flex-column gap-y-2 align-end">
          <div class="d-flex align-center gap-3 w-100 w-sm-auto">
            <VTextField
              v-model="startDate"
              type="date"
              label="Desde"
              density="compact"
              hide-details
              variant="outlined"
              style="max-width: 160px;"
            />
            <VTextField
              v-model="endDate"
              type="date"
              label="Hasta"
              density="compact"
              hide-details
              variant="outlined"
              style="max-width: 160px;"
            />
          </div>
          <div class="d-flex gap-x-2 mt-1">
            <VBtn size="x-small" :variant="activePreset === 'today' ? 'flat' : 'tonal'" color="primary" @click="setDatePreset('today')">Hoy</VBtn>
            <VBtn size="x-small" :variant="activePreset === 'yesterday' ? 'flat' : 'tonal'" color="primary" @click="setDatePreset('yesterday')">Ayer</VBtn>
            <VBtn size="x-small" :variant="activePreset === 'week' ? 'flat' : 'tonal'" color="primary" @click="setDatePreset('week')">Esta Semana</VBtn>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Fila 1: Tarjetas KPIs -->
    <VRow class="mb-6">
      <!-- Ventas Totales -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="h-100 position-relative overflow-hidden" style="background: linear-gradient(135deg, #7367F0 0%, #9E95F5 100%); color: white;">
          <VCardText class="d-flex flex-column justify-space-between h-100 pa-5">
            <div>
              <div class="d-flex justify-space-between align-center mb-4">
                <span class="text-subtitle-1 font-weight-medium">Ventas Consolidadas</span>
                <VAvatar color="white" color-opacity="20" size="40">
                  <VIcon icon="tabler-scale-balanced" color="white" size="22" />
                </VAvatar>
              </div>
              <h3 class="text-h4 font-weight-black mb-1">
                {{ formatUSD(stats.general_stats.total_sales) }}
              </h3>
            </div>
            <div class="text-caption opacity-80 mt-2">
              {{ stats.general_stats.total_transactions }} Transacciones Totales
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Ventas POS -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="h-100">
          <VCardText class="d-flex flex-column justify-space-between h-100 pa-5">
            <div class="d-flex justify-space-between align-center mb-4">
              <span class="text-subtitle-2 text-medium-emphasis">Ventas Tienda (POS)</span>
              <VAvatar color="primary" variant="light" size="40">
                <VIcon icon="tabler-device-computer-camera" size="22" />
              </VAvatar>
            </div>
            <div>
              <h3 class="text-h4 font-weight-bold mb-1">
                {{ formatUSD(stats.general_stats.pos_sales) }}
              </h3>
              <div class="text-caption text-medium-emphasis">
                {{ stats.general_stats.pos_transactions }} tickets facturados
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Ventas E-commerce -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="h-100">
          <VCardText class="d-flex flex-column justify-space-between h-100 pa-5">
            <div class="d-flex justify-space-between align-center mb-4">
              <span class="text-subtitle-2 text-medium-emphasis">Ventas Online</span>
              <VAvatar color="info" variant="light" size="40">
                <VIcon icon="tabler-shopping-cart" size="22" />
              </VAvatar>
            </div>
            <div>
              <h3 class="text-h4 font-weight-bold mb-1">
                {{ formatUSD(stats.general_stats.web_sales) }}
              </h3>
              <div class="text-caption text-medium-emphasis">
                {{ stats.general_stats.web_transactions }} pedidos aprobados
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Ganancia Neta -->
      <VCol cols="12" sm="6" lg="3">
        <VCard class="h-100" style="border-left: 5px solid #28C76F;">
          <VCardText class="d-flex flex-column justify-space-between h-100 pa-5">
            <div class="d-flex justify-space-between align-center mb-4">
              <span class="text-subtitle-2 text-medium-emphasis font-weight-bold">Utilidad Bruta</span>
              <VAvatar color="success" variant="light" size="40">
                <VIcon icon="tabler-currency-dollar" size="22" />
              </VAvatar>
            </div>
            <div>
              <h3 class="text-h4 font-weight-bold mb-1 text-success">
                {{ formatUSD(stats.general_stats.total_profit) }}
              </h3>
              <div class="text-caption text-medium-emphasis">
                Margen estimado: {{ stats.general_stats.total_sales > 0 ? Math.round((stats.general_stats.total_profit / stats.general_stats.total_sales) * 100) : 0 }}%
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Fila 2: Gráficos -->
    <VRow class="mb-6">
      <!-- Distribución de Pagos -->
      <VCol cols="12" md="5">
        <VCard class="h-100">
          <VCardTitle class="pa-4 d-flex justify-space-between align-center">
            <span class="text-h6 font-weight-bold">Métodos de Pago</span>
            <VIcon icon="tabler-chart-pie" class="text-medium-emphasis" />
          </VCardTitle>
          <VDivider />
          <VCardText class="pa-4 d-flex justify-center align-center">
            <div v-if="stats.payment_distribution.length === 0" class="text-center py-8 text-disabled">
              Sin transacciones en este período
            </div>
            <div v-else class="w-100">
              <VueApexCharts
                :options="paymentChartOptions"
                :series="paymentChartSeries"
                height="280"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Ventas por Categoría -->
      <VCol cols="12" md="7">
        <VCard class="h-100">
          <VCardTitle class="pa-4 d-flex justify-space-between align-center">
            <span class="text-h6 font-weight-bold">Ventas por Categoría (Top 5)</span>
            <VIcon icon="tabler-chart-bar" class="text-medium-emphasis" />
          </VCardTitle>
          <VDivider />
          <VCardText class="pa-4">
            <div v-if="stats.category_sales.length === 0" class="text-center py-8 text-disabled">
              Sin ventas registradas en las categorías
            </div>
            <div v-else>
              <VueApexCharts
                :options="categoryChartOptions"
                :series="categoryChartSeries"
                height="250"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Fila 3: Alertas de Stock e Historial de Pedidos -->
    <VRow class="mb-6">
      <!-- Alertas de Stock Bajo -->
      <VCol cols="12" md="5">
        <VCard class="h-100">
          <VCardTitle class="pa-4 d-flex justify-space-between align-center">
            <span class="text-h6 font-weight-bold text-error">Alertas de Stock Bajo</span>
            <VIcon icon="tabler-alert-circle" color="error" />
          </VCardTitle>
          <VDivider />
          <VCardText class="pa-0">
            <VList density="compact">
              <VListItem v-for="item in stats.low_stock" :key="item.id" class="px-4 py-3 border-bottom">
                <div class="d-flex align-center justify-space-between w-100">
                  <div class="min-width-0 mr-3">
                    <div class="text-sm font-weight-bold text-high-emphasis text-uppercase text-truncate">{{ item.name }}</div>
                    <span class="text-super-xs text-disabled text-uppercase">{{ item.category }}</span>
                  </div>
                  <div class="d-flex align-center gap-x-2 text-right">
                    <VChip
                      :color="item.stock <= 0 ? 'error' : 'warning'"
                      label
                      size="x-small"
                      variant="flat"
                      class="font-weight-black"
                    >
                      {{ item.stock <= 0 ? 'Agotado' : `${item.stock} Unidades` }}
                    </VChip>
                    <VBtn
                      icon
                      variant="text"
                      color="primary"
                      density="compact"
                      class="ml-1"
                      @click="replenishWithIa(item)"
                    >
                      <VIcon icon="tabler-robot" size="18" />
                      <VTooltip activator="parent">Reabastecer con IA</VTooltip>
                    </VBtn>
                  </div>
                </div>
              </VListItem>
              <VListItem v-if="stats.low_stock.length === 0" class="px-4 py-8 text-center text-disabled">
                Inventario óptimo. Sin alertas de stock.
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Últimos Pedidos E-commerce -->
      <VCol cols="12" md="7">
        <VCard class="h-100">
          <VCardTitle class="pa-4 d-flex justify-space-between align-center">
            <span class="text-h6 font-weight-bold">Últimos Pedidos E-commerce</span>
            <VIcon icon="tabler-shopping-cart-discount" class="text-medium-emphasis" />
          </VCardTitle>
          <VDivider />
          <VCardText class="pa-0">
            <VTable class="text-no-wrap" density="compact">
              <thead>
                <tr>
                  <th class="text-left font-weight-bold">Orden #</th>
                  <th class="text-left font-weight-bold">Cliente</th>
                  <th class="text-right font-weight-bold">Total</th>
                  <th class="text-center font-weight-bold">Estatus</th>
                  <th class="text-left font-weight-bold">Método</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="order in stats.recent_web_orders" :key="order.id">
                  <td>#{{ order.id }}</td>
                  <td>{{ order.customer_name }}</td>
                  <td class="text-right font-weight-bold text-primary">{{ formatUSD(order.total_amount) }}</td>
                  <td class="text-center">
                    <VChip
                      :color="getStatusColor(order.status)"
                      size="x-small"
                      label
                      class="font-weight-bold"
                    >
                      {{ getStatusText(order.status) }}
                    </VChip>
                  </td>
                  <td>{{ order.payment_method }}</td>
                </tr>
                <tr v-slot:bottom v-if="stats.recent_web_orders.length === 0">
                  <td colspan="5" class="text-center py-8 text-disabled">
                    Sin pedidos en la tienda virtual
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.border-bottom {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08);
}
.text-super-xs {
  font-size: 10px;
}
</style>
