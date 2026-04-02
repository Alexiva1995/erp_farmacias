<script setup>
import { computed } from "vue";
const props = defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      total_debt: 0,
      active_suppliers_count: 0,
      connection_success_rate: 100,
      successful_connections: 0,
      total_connections_24h: 0,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const statistics = computed(() => [
  {
    title: "Deuda Total",
    value: formatCurrency(props.stats.total_debt),
    icon: "tabler-currency-dollar",
    color: "error",
    description: "Pendiente por pagar",
  },
  {
    title: "Proveedores",
    value: props.stats.active_suppliers_count,
    icon: "tabler-users",
    color: "primary",
    description: "Registrados activos",
  },
  {
    title: "Éxito Conexión",
    value: `${props.stats.connection_success_rate}%`,
    icon: "tabler-api",
    color: "success",
    description: "Últimas 24 horas",
  },
]);
</script>

<template>
  <VRow>
    <VCol v-for="item in statistics" :key="item.title" cols="12" sm="6" md="4">
      <VCard class="stats-card border-0 overflow-hidden mb-6">
        <!-- Decoración de fondo -->
        <div
          class="card-bg-decoration"
          :style="{
            background: `linear-gradient(45deg, rgba(var(--v-theme-${item.color}), 0.1), transparent)`,
          }"
        ></div>

        <VCardText class="pa-5 relative-content">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar
              :color="item.color"
              variant="tonal"
              size="48"
              rounded="lg"
              class="elevation-1"
            >
              <VIcon :icon="item.icon" size="26" />
            </VAvatar>

            <div class="text-right">
              <span
                class="text-overline font-weight-bold text-disabled"
                style="letter-spacing: 1px !important"
                >{{ item.title }}</span
              >
              <h4 class="text-h4 font-weight-black mt-1">
                <template v-if="loading">
                  <VProgressCircular
                    indeterminate
                    size="24"
                    width="3"
                    color="primary"
                  />
                </template>
                <template v-else>
                  {{ item.value }}
                </template>
              </h4>
            </div>
          </div>

          <VDivider class="mb-3 opacity-20" />

          <div class="d-flex align-center justify-space-between">
            <span class="text-caption font-weight-medium text-medium-emphasis">
              {{ item.description }}
            </span>
            <VIcon
              icon="tabler-trending-up"
              size="16"
              :color="item.color"
              class="opacity-50"
            />
          </div>
        </VCardText>

        <!-- Borde de acento lateral -->
        <div
          class="accent-border"
          :style="{ backgroundColor: `rgb(var(--v-theme-${item.color}))` }"
        ></div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 5%) !important;
  transition: all 0.3s ease;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 70%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 15%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-h4 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}
</style>

<style scoped>
/* Estilos eliminados ya que usamos variant="tonal" de Vuetify */
</style>
