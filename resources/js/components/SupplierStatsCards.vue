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

const emit = defineEmits(["card-click"]);

const formatCurrency = (value) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(value);
};

const formatNumber = (value) => {
  return new Intl.NumberFormat("es-VE").format(value ?? 0);
};

const isMasterMode = computed(() => !!props.stats?.is_master);

const statistics = computed(() => {
  if (isMasterMode.value) {
    return [
      {
        title: "Droguerías Homologadas",
        value: props.stats?.total_suppliers ?? props.stats?.active_suppliers_count ?? 0,
        icon: "tabler-building-store",
        color: "primary",
        description: "Catálogo Maestro Central",
      },
      {
        title: "Con Plantilla Excel",
        value: props.stats?.with_excel_count ?? 0,
        icon: "tabler-file-spreadsheet",
        color: "success",
        description: "Mapeo de columnas listo",
      },
      {
        title: "Conexión Directa (API/FTP)",
        value: props.stats?.with_direct_conn_count ?? 0,
        icon: "tabler-server",
        color: "info",
        description: "Protocolos automáticos",
      },
    ];
  }

  const debtCount = props.stats?.debt_suppliers_count ?? 0;
  const overdueDebt = props.stats?.overdue_debt ?? 0;
  const debtDesc = debtCount > 0
    ? (overdueDebt > 0 ? `${debtCount} prov. • ${formatCurrency(overdueDebt)} vencido` : `${debtCount} proveedores con saldo`)
    : "Sin deuda pendiente";

  const syncedToday = props.stats?.synced_today_count ?? 0;
  const totalCommercial = props.stats?.commercial_suppliers_count ?? props.stats?.active_suppliers_count ?? 0;
  const failed24h = props.stats?.failed_connections_24h ?? 0;
  const syncDesc = failed24h > 0
    ? `${syncedToday} al día • ${failed24h} con fallo`
    : `${syncedToday} sincronizados hoy`;

  const avgScore = props.stats?.avg_score;
  const lowScore = props.stats?.low_score_count ?? 0;
  const scoreDesc = lowScore > 0
    ? `Evaluación 90d • ${lowScore} en riesgo (<60)`
    : "Evaluación últimos 90 días";

  return [
    {
      action: "debt",
      title: "Deuda por Pagar",
      value: formatCurrency(props.stats?.total_debt ?? 0),
      icon: "tabler-currency-dollar",
      color: "error",
      description: debtDesc,
    },
    {
      action: "sync",
      title: "Catálogos al Día (24h)",
      value: `${syncedToday} / ${totalCommercial}`,
      icon: "tabler-refresh",
      color: failed24h > 0 ? "warning" : "success",
      description: syncDesc,
    },
    {
      action: "score",
      title: "Calificación Promedio",
      value: avgScore !== null && avgScore !== undefined ? `${avgScore} / 100` : "N/A",
      icon: "tabler-star",
      color: "warning",
      description: scoreDesc,
    },
    {
      action: "catalog",
      title: "Oferta en Catálogos",
      value: `${formatNumber(props.stats?.unique_skus_count ?? 0)} SKUs`,
      icon: "tabler-packages",
      color: "info",
      description: "Productos únicos (sin duplicados)",
    },
  ];
});
</script>

<template>
  <VRow dense>
    <VCol
      v-for="item in statistics"
      :key="item.title"
      cols="12"
      sm="6"
      :md="isMasterMode ? 4 : 3"
    >
      <VCard
        class="stats-card border-0 overflow-hidden mb-3"
        :class="{ 'cursor-pointer': !isMasterMode && item.action }"
        @click="!isMasterMode && item.action ? emit('card-click', item.action) : null"
      >
        <!-- Decoración de fondo -->
        <div
          class="card-bg-decoration"
          :style="{
            background: `linear-gradient(45deg, rgba(var(--v-theme-${item.color}), 0.1), transparent)`,
          }"
        ></div>

        <VCardText class="pa-3 pa-sm-4 relative-content">
          <div class="d-flex align-center justify-space-between mb-2">
            <VAvatar
              :color="item.color"
              variant="tonal"
              :size="$vuetify.display.xs ? 38 : 42"
              rounded="lg"
              class="elevation-1"
            >
              <VIcon :icon="item.icon" :size="$vuetify.display.xs ? 20 : 22" />
            </VAvatar>

            <div class="text-right">
              <span
                class="text-overline font-weight-bold text-disabled"
                style="letter-spacing: 0.8px !important; font-size: 0.65rem !important;"
                >{{ item.title }}</span
              >
              <h4 class="text-h6 text-md-h5 font-weight-black mt-0">
                <template v-if="loading">
                  <VProgressCircular
                    indeterminate
                    size="20"
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

          <VDivider class="mb-2 opacity-20" />

          <div class="d-flex align-center justify-space-between">
            <span class="text-caption font-weight-medium text-medium-emphasis text-truncate">
              {{ item.description }}
            </span>
            <VIcon
              icon="tabler-trending-up"
              size="15"
              :color="item.color"
              class="opacity-50 flex-shrink-0 ms-1"
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
