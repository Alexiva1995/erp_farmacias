<template>
  <div>
    <!-- Fila 1: Felicitaciones y Estadísticas -->
    <VRow class="mb-6 match-height">
      <!-- Tarjeta de Felicitaciones -->
      <VCol cols="12" md="4">
        <VCard class="h-100 bg-light-primary">
          <VCardText class="d-flex flex-column justify-space-between h-100">
            <div class="d-flex align-center gap-3 mb-2">
              <VAvatar size="50" class="leader-avatar border-2 border-white shadow-lg">
                <VImg v-if="leader?.photo" :src="leader.photo" />
                <div v-else class="text-h5 font-weight-black text-white bg-primary d-flex align-center justify-center h-100 w-100">
                  {{ leader?.name?.charAt(0) || 'A' }}
                </div>
              </VAvatar>
              <div>
                <h6 class="text-h6 text-primary font-weight-semibold mb-0">
                  ¡Felicitaciones {{ leader?.name || 'Admin' }}! 🎉
                </h6>
                <div class="text-caption text-medium-emphasis">
                  Líder de Ventas
                </div>
              </div>
            </div>
            <div>
              <div class="text-h5 text-primary font-weight-bold">
                {{ formatCurrencyUSD(leader?.sales || 0) }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Tarjeta de Estadísticas -->
      <VCol cols="12" md="8">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4 d-flex justify-space-between align-center">
            <span>Estadísticas</span>
            <span class="text-caption text-medium-emphasis">Actualizado hace 1 mes</span>
          </VCardTitle>
          <VCardText class="pa-4 d-flex align-center justify-space-around flex-wrap">
            <!-- Ventas -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="primary-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-chart-bar" color="primary" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.sales }}</div>
                <div class="text-caption text-medium-emphasis">Ventas</div>
              </div>
            </div>
            <!-- Clientes -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="info-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-users" color="info" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.clients }}</div>
                <div class="text-caption text-medium-emphasis">Clientes Nuevos</div>
              </div>
            </div>
            <!-- Productos -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="error-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-box" color="error" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.products }}</div>
                <div class="text-caption text-medium-emphasis">Productos (Unidades)</div>
              </div>
            </div>
            <!-- Ingresos -->
            <div class="d-flex align-center mb-4">
              <VAvatar color="success-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-currency-dollar" color="success" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.revenue }}</div>
                <div class="text-caption text-medium-emphasis">Ganancia</div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Fila 2: Reportes y Gráficos -->
    <VRow class="mb-6 match-height">
      <!-- Columna Izquierda: Profit y Expenses -->
      <VCol cols="12" md="4">
        <VRow class="match-height">
          <!-- Profit -->
          <VCol cols="12" sm="6" md="12">
            <VCard class="mb-4">
              <VCardText>
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2 text-medium-emphasis">Ganancia</span>
                  <span class="text-success text-caption font-weight-medium">+8.24%</span>
                </div>
                <div class="text-h5 font-weight-bold mb-1">624k</div>
                <div class="text-caption text-medium-emphasis">Mes Pasado</div>
              </VCardText>
            </VCard>
          </VCol>
          <!-- Expenses -->
          <VCol cols="12" sm="6" md="12">
            <VCard>
              <VCardText class="d-flex flex-column align-center justify-center">
                <div class="text-h5 font-weight-bold mb-1">82.5K</div>
                <div class="text-caption text-medium-emphasis mb-2">Gastos</div>
                <VProgressCircular
                  :model-value="78"
                  :size="80"
                  :width="8"
                  color="warning"
                >
                  <span class="text-caption font-weight-bold">78%</span>
                </VProgressCircular>
                <div class="text-caption text-medium-emphasis mt-2">$21k Gastos más que el mes pasado</div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>

      <!-- Revenue Report (Usando componente existente si es posible) -->
      <VCol cols="12" md="8">
        <VCard class="h-100">
          <VCardText class="pa-0">
            <!-- Aquí usamos el componente existente del proyecto -->
            <EcommerceRevenueReport />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Fila 3: Reportes Detallados -->
    <VRow class="mb-6 match-height">
      <!-- Earning Reports -->
      <VCol cols="12" md="4">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4">Reporte de Ganancias</VCardTitle>
          <VCardText class="pa-4">
            <div class="mb-4">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-body-2">Ganancia Neta</span>
                <span class="text-success text-caption font-weight-medium">18.6%</span>
              </div>
              <div class="text-h6 font-weight-bold">$1,619</div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-body-2">Ingresos Totales</span>
                <span class="text-success text-caption font-weight-medium">39.6%</span>
              </div>
              <div class="text-h6 font-weight-bold">$3,571</div>
            </div>
            <div>
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-body-2">Gastos Totales</span>
                <span class="text-danger text-caption font-weight-medium">52.8%</span>
              </div>
              <div class="text-h6 font-weight-bold">$430</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Popular Products -->
      <VCol cols="12" md="4">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4">Productos Populares</VCardTitle>
          <VCardText class="pa-0">
            <VList density="compact">
              <VListItem v-for="(prod, index) in popularProducts" :key="index" class="px-4 py-2">
                <template #prepend>
                  <VAvatar color="primary-lighten-5" size="36" class="mr-3" rounded="lg">
                    <VIcon icon="tabler-package" color="primary" size="20" />
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">{{ prod.name }}</VListItemTitle>
                <VListItemSubtitle class="text-caption text-medium-emphasis">{{ prod.code }}</VListItemSubtitle>
                <template #append>
                  <span class="text-body-2 font-weight-bold">${{ prod.price }}</span>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Transactions -->
      <VCol cols="12" md="4">
        <VCard class="h-100">
          <VCardTitle class="pt-4 px-4">Transacciones</VCardTitle>
          <VCardText class="pa-0">
            <VList density="compact">
              <VListItem v-for="(tx, index) in transactions" :key="index" class="px-4 py-2">
                <template #prepend>
                  <VAvatar :color="tx.color + '-lighten-5'" size="36" class="mr-3" rounded="lg">
                    <VIcon :icon="tx.icon" :color="tx.color" size="20" />
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">{{ tx.title }}</VListItemTitle>
                <VListItemSubtitle class="text-caption text-medium-emphasis">{{ tx.subtitle }}</VListItemSubtitle>
                <template #append>
                  <span :class="`text-body-2 font-weight-bold ${tx.amount > 0 ? 'text-success' : 'text-error'}`">
                    {{ tx.amount > 0 ? '+' : '' }}{{ tx.amount }}
                  </span>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import EcommerceRevenueReport from "@/components/EcommerceRevenueReport.vue";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";

const authStore = useAuthStore();
const router = useRouter();

const leader = ref(null);
const stats = ref({
  sales: '$0.00',
  clients: '0',
  products: '0',
  revenue: '$0.00',
});

const fetchStats = async () => {
  try {
    // 1. Ventas desde Cierre de Caja (Monto unificado en USD)
    const cashResponse = await axios.get("/api/finances/cash-closure/monthlyCash");
    if (cashResponse.data && cashResponse.data.data && cashResponse.data.data.length > 0) {
      // Buscamos el mes actual en los datos (por nombre o tomamos el primero si no se encuentra)
      const monthsEn = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
      const monthsEs = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
      
      const currentMonthIdx = new Date().getMonth();
      const currentYear = new Date().getFullYear().toString();
      
      const currentMonthNameEn = monthsEn[currentMonthIdx];
      const currentMonthNameEs = monthsEs[currentMonthIdx];
      
      const currentMonthData = cashResponse.data.data.find(item => 
        (item.period.includes(currentMonthNameEn) || item.period.includes(currentMonthNameEs)) && 
        item.period.includes(currentYear)
      ) || cashResponse.data.data[0]; // Si no lo encuentra, usa el más reciente (primero)
      
      stats.value.sales = `${currentMonthData.total_usd_equivalent} USD`;
    }

    // 2. Ingresos (Ganancia) desde Reporte Fiscal
    const revResponse = await axios.get("/api/dashboard/revenue-report", {
      params: { year: new Date().getFullYear() }
    });
    if (revResponse.data && revResponse.data.data) {
      const monthlyData = revResponse.data.data.monthly_data;
      const currentMonth = new Date().getMonth() + 1;
      const currentMonthData = monthlyData.find(m => m.month === currentMonth);
      if (currentMonthData) {
        stats.value.revenue = formatCurrencyUSD(currentMonthData.net);
      }
    }
    
    // 3. Clientes Nuevos
    const startOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const endOfMonth = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0];
    const clientsResponse = await axios.post("/api/crm/clients/filtrar-sin-paginar", {
      fechaDesde_filtro: startOfMonth,
      fechaHasta_filtro: endOfMonth,
    });
    if (clientsResponse.data && clientsResponse.data.data) {
      stats.value.clients = clientsResponse.data.data.length.toString();
    }

    // 4. Productos (Unidades Vendidas)
    const unitsResponse = await axios.get("/api/dashboard/units-sold", {
      params: {
        start_date: startOfMonth,
        end_date: endOfMonth,
      }
    });
    if (unitsResponse.data && unitsResponse.data.units !== undefined) {
      stats.value.products = unitsResponse.data.units.toString();
    }
  } catch (error) {
    console.error("Error fetching stats:", error);
  }
};

const fetchLeader = async () => {
  try {
    const response = await axios.get("/api/rrhh/employee-performance", {
      params: {
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
      },
    });
    if (response.data && response.data.status) {
      const employees = response.data.data;
      if (employees.length > 0) {
        let maxSales = -1;
        let bestEmployee = null;
        employees.forEach(e => {
          const sales = Number(e.sales || 0);
          if (sales > maxSales) {
            maxSales = sales;
            bestEmployee = e;
          }
        });
        leader.value = bestEmployee;
      }
    }
  } catch (error) {
    console.error("Error fetching leader:", error);
  }
};

const formatCurrencyUSD = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);

// Datos Mock basados en la captura
const popularProducts = ref([
  { name: 'Apple iPhone 13', code: 'Item: #FXZ-4567', price: '999.29' },
  { name: 'Nike Air Jordan', code: 'Item: #FXZ-3456', price: '72.40' },
  { name: 'Beats Studio 2', code: 'Item: #FXZ-9485', price: '99.00' },
  { name: 'Apple Watch Series 7', code: 'Item: #FXZ-2345', price: '249.99' },
  { name: 'Amazon Echo Dot', code: 'Item: #FXZ-8959', price: '79.40' },
]);

const transactions = ref([
  { title: 'Wallet', subtitle: 'Starbucks', amount: -75, icon: 'tabler-wallet', color: 'primary' },
  { title: 'Bank Transfer', subtitle: 'Add Money', amount: 480, icon: 'tabler-building-bank', color: 'success' },
  { title: 'PayPal', subtitle: 'Client Payment', amount: 268, icon: 'tabler-brand-paypal', color: 'info' },
  { title: 'Master Card', subtitle: 'Ordered iPhone 13', amount: -699, icon: 'tabler-credit-card', color: 'warning' },
  { title: 'Bank Transactions', subtitle: 'Refund', amount: 98, icon: 'tabler-building-bank', color: 'success' },
]);

onMounted(() => {
  if (authStore.isLoaded) {
    fetchLeader();
    fetchStats();
  }
});

watch(() => authStore.user, (newUser) => {
  if (newUser) {
    fetchLeader();
    fetchStats();
  }
}, { immediate: true });

watch(() => authStore.isLoaded, (isLoaded) => {
  if (isLoaded && !authStore.isAdmin) {
    router.push('/tpv/orderUser');
  }
});
</script>

<style scoped>
.match-height .v-col {
  display: flex;
}

.match-height .v-card {
  width: 100%;
}

.bg-light-primary {
  background-color: rgb(var(--v-theme-primary), 0.1) !important;
}
</style>
