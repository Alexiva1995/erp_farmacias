<script setup>
import axios from "@/plugins/axios";
import { ref, watch, computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  clientId: { type: [Number, null], default: null },
});

const emit = defineEmits(["update:modelValue"]);

const loading = ref(false);
const clientData = ref(null);
const stats = ref(null);
const topProducts = ref([]);
const lastProducts = ref([]);

const badgeConfig = computed(() => {
  if (!stats.value) return {};
  const map = {
    VIP: { color: "warning", icon: "tabler-crown", text: "VIP" },
    Frecuente: { color: "success", icon: "tabler-heart", text: "Frecuente" },
    "En Riesgo": { color: "error", icon: "tabler-alert-triangle", text: "En Riesgo" },
    Ocasional: { color: "info", icon: "tabler-user", text: "Ocasional" },
    Nuevo: { color: "secondary", icon: "tabler-user-plus", text: "Nuevo" },
  };
  return map[stats.value.badge] || map["Nuevo"];
});

const dialogTitle = computed(() => {
  if (!clientData.value) return "Estadísticas del Cliente";
  const name = clientData.value.name || "";
  const lastName = clientData.value.last_name || "";
  return `${name} ${lastName}`.trim();
});

const fetchStats = async (id) => {
  if (!id) return;
  loading.value = true;
  try {
    const response = await axios.get(`/crm/clients/${id}/stats`);
    const data = response.data.data;
    clientData.value = data.client;
    stats.value = data.stats;
    topProducts.value = data.top_products || [];
    lastProducts.value = data.last_products || [];
  } catch (error) {
    console.error("Error al cargar estadísticas:", error);
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible && props.clientId) {
      fetchStats(props.clientId);
    }
    if (!isVisible) {
      clientData.value = null;
      stats.value = null;
      topProducts.value = [];
      lastProducts.value = [];
    }
  }
);

const onClose = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="750px"
    scrollable
    :retain-focus="false"
    @click:outside="onClose"
    @keydown.esc="onClose"
  >
    <VCard :loading="loading">
      <!-- Header -->
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-chart-bar" size="24" color="white" />
          <span class="text-h6 text-white">{{ dialogTitle }}</span>
          <VChip
            v-if="stats"
            :color="badgeConfig.color"
            :prepend-icon="badgeConfig.icon"
            size="small"
            variant="elevated"
            class="ms-2"
          >
            {{ badgeConfig.text }}
          </VChip>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="onClose">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <!-- Loading -->
        <div v-if="loading" class="d-flex justify-center align-center py-10">
          <VProgressCircular indeterminate color="primary" size="48" />
        </div>

        <template v-else-if="stats">
          <!-- Info del cliente -->
          <div v-if="clientData" class="mb-5">
            <div class="d-flex align-center flex-wrap gap-x-4 gap-y-1 text-body-2 text-medium-emphasis">
              <span class="d-flex align-center gap-1">
                <VIcon icon="tabler-id" size="18" />
                {{ clientData.identification_type }}{{ clientData.identification }}
              </span>
              <span v-if="clientData.company" class="d-flex align-center gap-1">
                <VIcon icon="tabler-building" size="18" />
                {{ clientData.company }}
              </span>
              <span v-if="clientData.phone" class="d-flex align-center gap-1">
                <VIcon icon="tabler-phone" size="18" />
                {{ clientData.phone }}
              </span>
              <span v-if="stats.last_purchase_date" class="d-flex align-center gap-1">
                <VIcon icon="tabler-calendar-check" size="18" />
                Última compra: {{ stats.last_purchase_date }}
              </span>
            </div>
          </div>

          <VDivider class="mb-5" />

          <!-- Stats Cards -->
          <VRow>
            <!-- Total Gastado -->
            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="success" class="pa-4 text-center stat-card">
                <VIcon icon="tabler-currency-dollar" size="32" color="success" class="mb-2" />
                <div class="text-h6 font-weight-bold">${{ stats.total_spent?.toFixed(2) }}</div>
                <div class="text-caption text-medium-emphasis">Total Gastado</div>
              </VCard>
            </VCol>

            <!-- Total Órdenes -->
            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="primary" class="pa-4 text-center stat-card">
                <VIcon icon="tabler-shopping-cart" size="32" color="primary" class="mb-2" />
                <div class="text-h6 font-weight-bold">{{ stats.total_orders }}</div>
                <div class="text-caption text-medium-emphasis">Compras</div>
              </VCard>
            </VCol>

            <!-- Ticket Promedio -->
            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="info" class="pa-4 text-center stat-card">
                <VIcon icon="tabler-receipt" size="32" color="info" class="mb-2" />
                <div class="text-h6 font-weight-bold">${{ stats.average_ticket?.toFixed(2) }}</div>
                <div class="text-caption text-medium-emphasis">Ticket Promedio</div>
              </VCard>
            </VCol>

            <!-- Días sin comprar -->
            <VCol cols="12" sm="6" md="3">
              <VCard
                variant="tonal"
                :color="stats.days_since_last_purchase !== null && stats.days_since_last_purchase > 30 ? 'error' : 'warning'"
                class="pa-4 text-center stat-card"
              >
                <VIcon
                  icon="tabler-calendar-time"
                  size="32"
                  :color="stats.days_since_last_purchase !== null && stats.days_since_last_purchase > 30 ? 'error' : 'warning'"
                  class="mb-2"
                />
                <div class="text-h6 font-weight-bold">
                  {{ stats.days_since_last_purchase !== null ? Number(stats.days_since_last_purchase).toFixed(0) : '—' }}
                </div>
                <div class="text-caption text-medium-emphasis">Días sin comprar</div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Top 5 Productos más comprados -->
          <div v-if="topProducts.length > 0" class="mt-5">
            <div class="d-flex align-center gap-2 mb-3">
              <VIcon icon="tabler-star" size="20" color="warning" />
              <span class="text-body-1 font-weight-medium">Top 5 Productos Favoritos</span>
            </div>

            <VList lines="two" density="compact" class="rounded border">
              <template v-for="(product, index) in topProducts" :key="'top-' + index">
                <VListItem>
                  <template #prepend>
                    <VAvatar
                      :color="index === 0 ? 'warning' : index === 1 ? 'secondary' : index === 2 ? 'info' : index === 3 ? 'success' : 'primary'"
                      variant="tonal"
                      size="36"
                    >
                      <span class="text-body-2 font-weight-bold">{{ index + 1 }}</span>
                    </VAvatar>
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    {{ product.product_name }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    <span v-if="product.laboratory_name" class="font-weight-medium">{{ product.laboratory_name }} · </span>
                    {{ product.total_quantity }} unidades compradas
                  </VListItemSubtitle>
                </VListItem>
                <VDivider v-if="index < topProducts.length - 1" />
              </template>
            </VList>
          </div>

          <!-- Últimas 10 compras -->
          <div v-if="lastProducts.length > 0" class="mt-5">
            <div class="d-flex align-center gap-2 mb-3">
              <VIcon icon="tabler-history" size="20" color="primary" />
              <span class="text-body-1 font-weight-medium">Últimas 10 Compras</span>
            </div>

            <VList lines="two" density="compact" class="rounded border">
              <template v-for="(product, index) in lastProducts" :key="'last-' + index">
                <VListItem>
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="36">
                      <VIcon icon="tabler-pill" size="18" />
                    </VAvatar>
                  </template>
                  <VListItemTitle class="text-body-2 font-weight-medium">
                    {{ product.product_name }}
                    <span v-if="product.laboratory_name" class="text-caption text-medium-emphasis ms-1">({{ product.laboratory_name }})</span>
                  </VListItemTitle>
                  <VListItemSubtitle class="text-caption">
                    {{ product.date }} · {{ product.quantity }} uds. · ${{ product.price_usd?.toFixed(2) }} c/u
                  </VListItemSubtitle>
                  <template #append>
                    <span class="text-body-2 font-weight-medium text-success">
                      ${{ product.total_usd?.toFixed(2) }}
                    </span>
                  </template>
                </VListItem>
                <VDivider v-if="index < lastProducts.length - 1" />
              </template>
            </VList>
          </div>

          <!-- Sin datos -->
          <VCard v-if="stats.total_orders === 0" variant="tonal" color="secondary" class="mt-5 pa-5 text-center">
            <VIcon icon="tabler-shopping-cart-off" size="48" color="secondary" class="mb-3" />
            <div class="text-body-1 font-weight-medium">Sin compras registradas</div>
            <div class="text-body-2 text-medium-emphasis">Este cliente aún no tiene órdenes completadas.</div>
          </VCard>
        </template>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="12" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="onClose"
            >
              Cerrar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.stat-card {
  border-radius: 12px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 10%);
  transform: translateY(-2px);
}
</style>
