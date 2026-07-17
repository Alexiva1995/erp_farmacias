<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import VueApexCharts from 'vue3-apexcharts';
import { useTheme } from 'vuetify';
import { useRouter } from 'vue-router';
import { useBrandingStore } from '@/stores/useBrandingStore';

const vuetifyTheme = useTheme();
const router = useRouter();
const brandingStore = useBrandingStore();

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
    if (data && data.general_stats) {
      stats.value = data;
    }
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
const formatCurrency = (val) => {
  const currency = brandingStore.settings.default_currency || 'USD';
  return new Intl.NumberFormat('es-CO', { 
    style: 'currency', 
    currency: currency,
    minimumFractionDigits: 2
  }).format(val);
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
    labels: (stats.value?.payment_distribution || []).map(i => i.label),
    colors: ['#89141C', '#D97706', '#059669', '#2563EB', '#4B5563'],
    stroke: {
      width: 2,
      colors: [currentTheme.colors.surface]
    },
    legend: {
      show: true,
      position: 'bottom',
      fontSize: '11px',
      fontFamily: 'Outfit, sans-serif',
      labels: {
        colors: currentTheme.colors['on-surface']
      }
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => `${val.toFixed(0)}%`,
      style: {
        fontSize: '10px',
        fontFamily: 'Outfit, sans-serif'
      }
    },
    plotOptions: {
      pie: {
        donut: {
          size: '75%',
          labels: {
            show: true,
            name: {
              show: true,
              fontSize: '12px',
              fontFamily: 'Outfit, sans-serif',
              color: currentTheme.colors['on-surface-variant'],
              offsetY: -3
            },
            value: {
              show: true,
              fontSize: '16px',
              fontFamily: 'Outfit, sans-serif',
              fontWeight: '500',
              color: currentTheme.colors['on-surface'],
              offsetY: 3,
              formatter: (val) => formatCurrency(val)
            },
            total: {
              show: true,
              label: 'Total Pago',
              fontSize: '11px',
              fontFamily: 'Outfit, sans-serif',
              color: currentTheme.colors['on-surface-variant'],
              formatter: (w) => {
                return formatCurrency((stats.value?.payment_distribution || []).reduce((acc, curr) => acc + curr.value, 0));
              }
            }
          }
        }
      }
    }
  };
});

const paymentChartSeries = computed(() => {
  return (stats.value?.payment_distribution || []).map(i => i.value);
});

// Configuración de Gráfico de Barras (Ventas por Categoría)
const categoryChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value;
  return {
    chart: {
      parentHeightOffset: 0,
      toolbar: { show: false },
      type: 'bar'
    },
    plotOptions: {
      bar: {
        horizontal: true,
        barHeight: '30%',
        borderRadius: 3
      }
    },
    colors: ['#89141C'],
    dataLabels: {
      enabled: true,
      formatter: (val) => formatCurrency(val),
      style: {
        fontSize: '10px',
        fontFamily: 'Outfit, sans-serif',
        colors: ['#fff']
      }
    },
    grid: {
      borderColor: currentTheme.colors['border-color'],
      strokeDashArray: 3
    },
    xaxis: {
      categories: (stats.value?.category_sales || []).map(i => i.name),
      labels: {
        style: {
          colors: currentTheme.colors['on-surface-variant'],
          fontSize: '10px',
          fontFamily: 'Outfit, sans-serif'
        },
        formatter: (val) => formatCurrency(val)
      }
    },
    yaxis: {
      labels: {
        style: {
          colors: currentTheme.colors['on-surface-variant'],
          fontSize: '11px',
          fontFamily: 'Outfit, sans-serif'
        }
      }
    }
  };
});

const categoryChartSeries = computed(() => {
  return [{
    name: 'Ventas',
    data: (stats.value?.category_sales || []).map(i => i.value)
  }];
});
</script>

<template>
  <div class="minimarket-dashboard-wrapper">
    <!-- Filtros de Fecha -->
    <VCard class="mb-6 border-0 shadow-sm border-5px">
      <VCardText class="pa-4">
        <div class="d-flex flex-column flex-md-row justify-space-between align-md-center gap-4">
          <div>
            <h4 class="text-subtitle-1 font-weight-medium mb-1 text-high-emphasis">Consola de Control</h4>
            <span class="text-caption text-medium-emphasis">Métricas consolidadas de Tienda Física (POS) y E-commerce</span>
          </div>
          <div class="d-flex flex-column flex-sm-row align-sm-center gap-3">
            <div class="d-flex align-center gap-2">
              <VTextField
                v-model="startDate"
                type="date"
                label="Desde"
                density="compact"
                hide-details
                variant="outlined"
                class="date-input"
              />
              <VTextField
                v-model="endDate"
                type="date"
                label="Hasta"
                density="compact"
                hide-details
                variant="outlined"
                class="date-input"
              />
            </div>
            <div class="d-flex gap-1 mt-2 mt-sm-0">
              <VBtn size="small" :variant="activePreset === 'today' ? 'flat' : 'outlined'" color="primary" class="font-weight-medium" @click="setDatePreset('today')">Hoy</VBtn>
              <VBtn size="small" :variant="activePreset === 'yesterday' ? 'flat' : 'outlined'" color="primary" class="font-weight-medium" @click="setDatePreset('yesterday')">Ayer</VBtn>
              <VBtn size="small" :variant="activePreset === 'week' ? 'flat' : 'outlined'" color="primary" class="font-weight-medium" @click="setDatePreset('week')">Semana</VBtn>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Estado de Carga con Skeleton Loader -->
    <div v-if="loading">
      <VRow class="mb-6">
        <VCol v-for="i in 4" :key="i" cols="12" sm="6" lg="3">
          <VCard class="border-0 shadow-sm pa-4 border-5px">
            <VSkeletonLoader type="list-item-avatar-two-line" />
          </VCard>
        </VCol>
      </VRow>
      <VRow class="mb-6">
        <VCol cols="12" md="5">
          <VCard class="border-0 shadow-sm pa-4 border-5px">
            <VSkeletonLoader type="card, actions" />
          </VCard>
        </VCol>
        <VCol cols="12" md="7">
          <VCard class="border-0 shadow-sm pa-4 border-5px">
            <VSkeletonLoader type="card, actions" />
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Contenido del Dashboard -->
    <div v-else class="animate-fade-in">
      <!-- Fila 1: Tarjetas KPIs -->
      <VRow class="mb-6 match-height">
        <!-- Ventas Totales -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card bg-primary-gradient border-0 shadow-sm text-white border-5px">
            <VCardText class="d-flex flex-column justify-space-between h-100 pa-4">
              <div>
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-subtitle-2 font-weight-medium opacity-90">Ventas Consolidadas</span>
                  <VAvatar color="white" class="bg-opacity-20" size="34">
                    <VIcon icon="tabler-scale-balanced" color="white" size="18" />
                  </VAvatar>
                </div>
                <h3 class="text-h5 font-weight-medium tracking-tight">
                  {{ formatCurrency(stats.general_stats.total_sales) }}
                </h3>
              </div>
              <div class="text-caption opacity-80 mt-2 font-weight-medium">
                {{ stats.general_stats.total_transactions }} Transacciones
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ventas POS -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card border-0 shadow-sm border-5px">
            <VCardText class="d-flex flex-column justify-space-between h-100 pa-4">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-subtitle-2 text-medium-emphasis font-weight-medium">Ventas Tienda (POS)</span>
                <VAvatar color="primary" variant="tonal" size="34">
                  <VIcon icon="tabler-device-computer-camera" size="18" />
                </VAvatar>
              </div>
              <div>
                <h3 class="text-h5 font-weight-medium text-high-emphasis tracking-tight">
                  {{ formatCurrency(stats.general_stats.pos_sales) }}
                </h3>
                <div class="text-caption text-medium-emphasis font-weight-medium mt-1">
                  {{ stats.general_stats.pos_transactions }} tickets facturados
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ventas E-commerce -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card border-0 shadow-sm border-5px">
            <VCardText class="d-flex flex-column justify-space-between h-100 pa-4">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-subtitle-2 text-medium-emphasis font-weight-medium">Ventas Online (Web)</span>
                <VAvatar color="info" variant="tonal" size="34">
                  <VIcon icon="tabler-shopping-cart" size="18" />
                </VAvatar>
              </div>
              <div>
                <h3 class="text-h5 font-weight-medium text-high-emphasis tracking-tight">
                  {{ formatCurrency(stats.general_stats.web_sales) }}
                </h3>
                <div class="text-caption text-medium-emphasis font-weight-medium mt-1">
                  {{ stats.general_stats.web_transactions }} pedidos aprobados
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ganancia Neta -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card border-0 shadow-sm border-start-success-4 border-5px">
            <VCardText class="d-flex flex-column justify-space-between h-100 pa-4">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-subtitle-2 text-medium-emphasis font-weight-medium">Utilidad Bruta</span>
                <VAvatar color="success" variant="tonal" size="34">
                  <VIcon icon="tabler-currency-dollar" size="18" />
                </VAvatar>
              </div>
              <div>
                <h3 class="text-h5 font-weight-medium text-success tracking-tight">
                  {{ formatCurrency(stats.general_stats.total_profit) }}
                </h3>
                <div class="text-caption text-medium-emphasis font-weight-medium mt-1">
                  Margen: {{ stats.general_stats.total_sales > 0 ? Math.round((stats.general_stats.total_profit / stats.general_stats.total_sales) * 100) : 0 }}%
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
          <VCard class="border-0 shadow-sm h-100 border-5px">
            <VCardTitle class="px-4 py-3 d-flex justify-space-between align-center">
              <span class="text-subtitle-2 font-weight-medium text-high-emphasis">Métodos de Pago</span>
              <VIcon icon="tabler-chart-pie" class="text-medium-emphasis" size="18" />
            </VCardTitle>
            <VDivider />
            <VCardText class="pa-4 d-flex justify-center align-center">
              <div v-if="stats.payment_distribution.length === 0" class="text-center py-12 text-disabled w-100">
                <VIcon icon="tabler-chart-pie-off" size="44" class="mb-2 opacity-40" />
                <div>Sin transacciones</div>
              </div>
              <div v-else class="w-100">
                <VueApexCharts
                  :options="paymentChartOptions"
                  :series="paymentChartSeries"
                  height="260"
                />
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ventas por Categoría -->
        <VCol cols="12" md="7">
          <VCard class="border-0 shadow-sm h-100 border-5px">
            <VCardTitle class="px-4 py-3 d-flex justify-space-between align-center">
              <span class="text-subtitle-2 font-weight-medium text-high-emphasis">Ventas por Categoría (Top 5)</span>
              <VIcon icon="tabler-chart-bar" class="text-medium-emphasis" size="18" />
            </VCardTitle>
            <VDivider />
            <VCardText class="pa-4">
              <div v-if="stats.category_sales.length === 0" class="text-center py-12 text-disabled">
                <VIcon icon="tabler-chart-bar-off" size="44" class="mb-2 opacity-40" />
                <div>Sin ventas registradas</div>
              </div>
              <div v-else>
                <VueApexCharts
                  :options="categoryChartOptions"
                  :series="categoryChartSeries"
                  height="230"
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
          <VCard class="border-0 shadow-sm h-100 border-5px">
            <VCardTitle class="px-4 py-3 d-flex justify-space-between align-center">
              <span class="text-subtitle-2 font-weight-medium text-error">Alertas de Stock Bajo</span>
              <VIcon icon="tabler-alert-triangle" color="error" size="18" />
            </VCardTitle>
            <VDivider />
            <VCardText class="pa-0">
              <VList density="compact" class="py-1">
                <VListItem v-for="item in stats.low_stock" :key="item.id" class="px-4 py-2 border-bottom-light">
                  <template #title>
                    <div class="text-sm font-weight-medium text-high-emphasis text-uppercase text-truncate">{{ item.name }}</div>
                  </template>
                  <template #subtitle>
                    <span class="text-xxs text-disabled text-uppercase font-weight-medium">{{ item.category }}</span>
                  </template>
                  <template #append>
                    <div class="d-flex align-center gap-2">
                      <VChip
                        :color="item.stock <= 0 ? 'error' : 'warning'"
                        label
                        size="x-small"
                        variant="flat"
                        class="font-weight-medium"
                      >
                        {{ item.stock <= 0 ? 'Agotado' : `${item.stock} Unids` }}
                      </VChip>
                      <VBtn
                        icon
                        variant="tonal"
                        color="primary"
                        density="comfortable"
                        @click="replenishWithIa(item)"
                      >
                        <VIcon icon="tabler-robot" size="15" />
                        <VTooltip activator="parent">Reabastecer con IA</VTooltip>
                      </VBtn>
                    </div>
                  </template>
                </VListItem>
                <VListItem v-if="stats.low_stock.length === 0" class="px-4 py-12 text-center text-disabled">
                  <VIcon icon="tabler-circle-check" color="success" size="44" class="mb-2 opacity-55" />
                  <div>Inventario óptimo. Sin alertas.</div>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Últimos Pedidos E-commerce -->
        <VCol cols="12" md="7">
          <VCard class="border-0 shadow-sm h-100 border-5px">
            <VCardTitle class="px-4 py-3 d-flex justify-space-between align-center">
              <span class="text-subtitle-2 font-weight-medium text-high-emphasis">Últimos Pedidos E-commerce</span>
              <VIcon icon="tabler-shopping-cart-discount" class="text-medium-emphasis" size="18" />
            </VCardTitle>
            <VDivider />
            <VCardText class="pa-0">
              <VTable class="text-no-wrap" density="comfortable">
                <thead>
                  <tr>
                    <th class="text-left font-weight-medium px-4">Orden #</th>
                    <th class="text-left font-weight-medium">Cliente</th>
                    <th class="text-right font-weight-medium">Total</th>
                    <th class="text-center font-weight-medium">Estatus</th>
                    <th class="text-left font-weight-medium">Método</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="order in stats.recent_web_orders" :key="order.id" class="table-row-hover">
                    <td class="px-4 font-weight-medium">#{{ order.id }}</td>
                    <td class="text-medium-emphasis">{{ order.customer_name }}</td>
                    <td class="text-right font-weight-medium text-primary">{{ formatCurrency(order.total_amount) }}</td>
                    <td class="text-center">
                      <VChip
                        :color="getStatusColor(order.status)"
                        size="x-small"
                        label
                        class="font-weight-medium"
                      >
                        {{ getStatusText(order.status) }}
                      </VChip>
                    </td>
                    <td class="text-medium-emphasis">{{ order.payment_method }}</td>
                  </tr>
                  <tr v-if="stats.recent_web_orders.length === 0">
                    <td colspan="5" class="text-center py-12 text-disabled">
                      <VIcon icon="tabler-shopping-cart-off" size="44" class="mb-2 opacity-40" />
                      <div>Sin pedidos en la tienda virtual</div>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.minimarket-dashboard-wrapper {
  font-family: 'Outfit', 'Public Sans', sans-serif;
}

.date-input {
  max-width: 155px;
}

.kpi-card {
  transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.22s ease;
  border: 1px solid rgba(var(--v-border-color), 0.04) !important;
}

.kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 16px -4px rgba(var(--v-shadow-key-umbrella), 0.1) !important;
}

.bg-primary-gradient {
  background: linear-gradient(135deg, #89141C 0%, #B82531 100%) !important;
  border: none !important;
}

.border-start-success-4 {
  border-inline-start: 4px solid #28C76F !important;
}

.border-bottom-light {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.04);
}

.tracking-tight {
  letter-spacing: -0.4px;
}

.text-xxs {
  font-size: 11px;
}

.table-row-hover {
  transition: background-color 0.2s ease;
}

.table-row-hover:hover {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

/* Forzar que todos los elementos internos del Dashboard respeten el límite estricto de 5px */
.border-5px,
:deep(.v-card),
:deep(.v-btn),
:deep(.v-chip),
:deep(.v-avatar),
:deep(.v-field),
:deep(.v-input__control),
:deep(.v-list-item) {
  border-radius: 5px !important;
}

/* Animación de Entrada Suave */
.animate-fade-in {
  animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
