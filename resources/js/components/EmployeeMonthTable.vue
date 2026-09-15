<script setup>
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const headers = [
  { title: "EMPLEADO", key: "name", width: "160px" },
  { title: "PUNTAJE", key: "scores.total", align: "center", width: "100px" },
  { title: "VENTAS", key: "scores.sales", align: "center", width: "140px" },
  { title: "CRECIMIENTO", key: "scores.growth", align: "center", width: "140px" },
  { title: "VENCIMIENTOS", key: "scores.expiration", align: "center", width: "130px" },
  { title: "INVENTARIO", key: "scores.inventory", align: "center", width: "130px" },
  { title: "PREMIUM", key: "scores.premium", align: "center", width: "120px" },
  { title: "FACTURACIÓN", key: "scores.invoice", align: "center", width: "125px" },
  { title: "LIMPIEZA", key: "scores.cleaning", align: "center", width: "120px" },
  { title: "ESTRATÉGICO", key: "scores.strategy", align: "center", width: "120px" },
];

const formatShortName = (name, lastName) => {
  if (!name && !lastName) return "";
  const firstNameInitial = name ? `${name.trim().charAt(0)}.` : "";
  const firstLastName = lastName ? lastName.trim().split(" ")[0] : "";
  return `${firstNameInitial} ${firstLastName}`.toUpperCase();
};

const getScoreInfo = (key) => {
  const infos = {
    "scores.sales": { title: "Ventas", icon: "tabler-currency-dollar", max: 25, desc: "Basado en el volumen total de ventas." },
    "scores.growth": { title: "Crecimiento", icon: "tabler-trending-up", max: 15, desc: "Crecimiento porcentual respecto al mes anterior." },
    "scores.expiration": { title: "Vencimientos", icon: "tabler-calendar-off", max: 15, desc: "Premia el bajo índice de productos vencidos." },
    "scores.inventory": { title: "Inventario", icon: "tabler-package", max: 10, desc: "Calidad y cantidad de conteos cíclicos." },
    "scores.premium": { title: "Premium", icon: "tabler-pills", max: 10, desc: "Ventas de productos de alto valor (>$15)." },
    "scores.invoice": { title: "Facturación", icon: "tabler-file-invoice", max: 15, desc: "Desempeño en gestión de facturas." },
    "scores.cleaning": { title: "Limpieza", icon: "tabler-brush", max: 5, desc: "Cumplimiento de cronograma de limpieza." },
    "scores.strategy": { title: "Estratégico", icon: "tabler-target", max: 5, desc: "Venta de marcas priorizadas." },
  };
  return infos[key] || { title: key, icon: "tabler-info-circle", max: 100, desc: "" };
};

const getPointsColor = (points, max) => {
  const ratio = (points || 0) / (max || 1);
  if (ratio >= 0.8) return "success";
  if (ratio >= 0.4) return "primary";
  if (ratio > 0) return "warning";
  return "secondary";
};

const formatNumber = (num) =>
  new Intl.NumberFormat("es-VE", { maximumFractionDigits: 2 }).format(num);

const formatCurrency = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);
</script>

<template>
  <div class="employee-month-table-container">
    <!-- Vista de Escritorio: Tabla Limpia y Profesional -->
    <VCard v-if="!$vuetify.display.smAndDown" border variant="flat" class="rounded-lg overflow-hidden">
      <VDataTable
        :headers="headers"
        :items="props.items"
        :loading="props.loading"
        item-value="id"
        class="clean-performance-table text-no-wrap"
        density="comfortable"
      >
        <template #no-data>
          <div class="pa-8 text-center text-medium-emphasis">
            <VIcon icon="tabler-users-minus" size="48" color="secondary" class="mb-2 opacity-50" />
            <h3 class="text-h6 font-weight-bold">No se encontraron empleados</h3>
            <p class="text-body-2 text-disabled">No hay datos disponibles para el periodo o criterio de búsqueda seleccionado.</p>
          </div>
        </template>

        <!-- Custom Headers with Tooltips -->
        <template v-for="header in headers" :key="header.key" #[`header.${header.key}`]="{ column }">
          <div class="d-flex align-center gap-1 justify-center" v-if="header.key !== 'name' && header.key !== 'scores.total'">
            <span class="text-uppercase font-weight-bold text-xs">{{ column.title }}</span>
            <VTooltip location="top" :text="getScoreInfo(header.key).desc">
              <template #activator="{ props: tooltipProps }">
                <VIcon v-bind="tooltipProps" icon="tabler-info-circle" size="13" class="text-disabled cursor-pointer" />
              </template>
            </VTooltip>
          </div>
          <span v-else class="text-uppercase font-weight-bold text-xs">{{ column.title }}</span>
        </template>

        <!-- Empleado -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-2">
            <div class="position-relative">
              <VAvatar 
                :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'" 
                variant="tonal"
                size="34"
                class="rounded-lg font-weight-bold"
              >
                <VImg v-if="item.photo" :src="item.photo" cover />
                <span v-else class="text-xs">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
              </VAvatar>
              <VIcon
                v-if="props.items.indexOf(item) === 0"
                color="warning"
                icon="tabler-crown"
                size="14"
                class="position-absolute leader-crown"
              />
            </div>
            <div class="d-flex flex-column truncate">
              <span class="text-sm font-weight-medium text-high-emphasis text-uppercase leading-tight truncate">
                {{ formatShortName(item.name, item.last_name) }}
                <VTooltip activator="parent" location="top">{{ item.name }} {{ item.last_name }}</VTooltip>
              </span>
              <span class="text-super-xs text-medium-emphasis uppercase font-weight-medium">ID: #{{ item.id }}</span>
            </div>
          </div>
        </template>

        <!-- Puntaje Total Destacado -->
        <template #item.scores.total="{ item }">
          <div class="d-flex justify-center">
            <VChip
              :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'"
              variant="flat"
              class="font-weight-bold px-3 rounded-lg text-uppercase shadow-sm"
              size="small"
            >
              {{ formatNumber(item.scores.total) }} pts
            </VChip>
          </div>
        </template>

        <!-- Métricas con Micro-Badge (Sin barras de colores caóticas) -->
        <template v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" #[`item.scores.${key}`]="{ item }">
          <div class="metric-cell-wrapper px-2 py-1.5">
            <!-- Valor numérico principal a la izquierda -->
            <span 
              class="text-xs font-weight-medium text-high-emphasis tabular-nums"
              :class="key === 'growth' ? (item.growth > 0 ? 'text-success font-weight-bold' : (item.growth < 0 ? 'text-error font-weight-bold' : '')) : ''"
            >
              {{ key === 'sales' ? formatCurrency(item.sales) : 
                 key === 'growth' ? `${item.growth}%` :
                 key === 'expiration' ? `${item.expirations} U.` :
                 key === 'inventory' ? `${item.inventory_counted} C.` :
                 key === 'premium' ? `${item.premium_products} U.` :
                 key === 'invoice' ? `${item.invoice_items} I.` :
                 key === 'cleaning' ? `${Math.round((item.cleaning_completed / (item.cleaning_assigned || 1)) * 100)}%` :
                 `${item.strategy_sales} U.`
              }}
            </span>

            <!-- Micro-Badge de puntos ganados a la derecha -->
            <VChip
              :color="getPointsColor(item.scores[key], getScoreInfo(`scores.${key}`).max)"
              variant="tonal"
              size="x-small"
              class="font-weight-bold micro-badge rounded ms-2 tabular-nums"
            >
              +{{ formatNumber(item.scores[key]) }}
            </VChip>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Vista Móvil: Cards de Desempeño Limpias -->
    <div v-else class="pa-1 bg-light">
      <VCard
        v-for="(item, index) in props.items"
        :key="item.id"
        variant="flat"
        border
        class="rounded-lg mb-3 overflow-hidden bg-white"
      >
        <!-- Header del Card -->
        <div class="pa-4 d-flex align-center gap-3">
          <div class="position-relative">
            <VAvatar 
              :color="index === 0 ? 'warning' : 'primary'" 
              variant="tonal"
              size="42"
              class="rounded-lg font-weight-bold"
            >
              <VImg v-if="item.photo" :src="item.photo" cover />
              <span v-else class="text-sm font-weight-bold text-uppercase">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
            </VAvatar>
            <VIcon v-if="index === 0" icon="tabler-crown" color="warning" size="16" class="position-absolute leader-crown-mobile" />
          </div>
          
          <div class="d-flex flex-column flex-grow-1 min-width-0">
            <span class="text-sm font-weight-bold text-high-emphasis text-uppercase leading-tight truncate">
              {{ item.name }} {{ item.last_name }}
            </span>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-medium-emphasis font-weight-medium uppercase">Puesto #{{ index + 1 }}</span>
              <VChip size="x-small" :color="index === 0 ? 'warning' : 'primary'" variant="flat" class="font-weight-bold rounded px-2">
                {{ formatNumber(item.scores.total) }} pts
              </VChip>
            </div>
          </div>
        </div>

        <VDivider class="border-opacity-10" />

        <!-- Grid de Desempeño Móvil Limpio -->
        <div class="pa-3">
          <VRow dense>
            <VCol v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" cols="6" class="pa-1">
              <div class="pa-2 rounded-lg bg-light border d-flex flex-column justify-space-between h-100">
                <div class="d-flex align-center gap-1 mb-1">
                  <VIcon :icon="getScoreInfo(`scores.${key}`).icon" size="13" class="text-medium-emphasis" />
                  <span class="text-super-xs font-weight-bold text-disabled uppercase truncate">{{ getScoreInfo(`scores.${key}`).title }}</span>
                </div>
                <div class="d-flex justify-space-between align-center">
                  <span 
                    class="text-xs font-weight-bold text-high-emphasis tabular-nums truncate"
                    :class="key === 'growth' ? (item.growth > 0 ? 'text-success' : (item.growth < 0 ? 'text-error' : '')) : ''"
                  >
                    {{ key === 'sales' ? formatCurrency(item.sales) : 
                       key === 'growth' ? `${item.growth}%` :
                       key === 'expiration' ? `${item.expirations} U.` :
                       key === 'inventory' ? `${item.inventory_counted} C.` :
                       key === 'premium' ? `${item.premium_products} U.` :
                       key === 'invoice' ? `${item.invoice_items} I.` :
                       key === 'cleaning' ? `${Math.round((item.cleaning_completed / (item.cleaning_assigned || 1)) * 100)}%` :
                       `${item.strategy_sales} U.`
                    }}
                  </span>
                  <VChip
                    :color="getPointsColor(item.scores[key], getScoreInfo(`scores.${key}`).max)"
                    variant="tonal"
                    size="x-small"
                    class="font-weight-bold micro-badge rounded ms-1 tabular-nums"
                  >
                    +{{ formatNumber(item.scores[key]) }}
                  </VChip>
                </div>
              </div>
            </VCol>
          </VRow>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
:deep(.clean-performance-table) {
  background: transparent !important;

  thead th {
    background: #fafbfc !important;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
    font-size: 0.72rem !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.04rem !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08) !important;
    padding-inline: 8px !important;
  }

  tbody tr {
    transition: background-color 0.15s ease;
    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.025) !important;
    }
    td {
      padding-block: 8px !important;
      padding-inline: 8px !important;
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.04) !important;
    }
  }
}

.metric-cell-wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(var(--v-theme-surface), 0.6);
  border-radius: 6px;
}

.micro-badge {
  font-size: 0.65rem !important;
  height: 20px !important;
  padding-inline: 6px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.03em !important;
  line-height: normal;
}

.leader-crown {
  inset-block-start: -7px;
  inset-inline-end: -6px;
  transform: rotate(15deg);
}

.leader-crown-mobile {
  inset-block-start: -6px;
  inset-inline-end: -5px;
  transform: rotate(15deg);
}

.bg-light {
  background-color: #f8fafc !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.min-width-0 {
  min-width: 0;
}

:deep(.v-data-table-footer) {
  display: none !important;
}
</style>


