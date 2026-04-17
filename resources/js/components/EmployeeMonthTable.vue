<script setup>
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
});

const headers = [
  { title: "EMPLEADO", key: "name", width: "250px" },
  { title: "PUNTAJE FINAL", key: "scores.total", align: "end", width: "130px" },
  { title: "VENTAS", key: "scores.sales", align: "center", width: "130px" },
  { title: "CRECIMIENTO", key: "scores.growth", align: "center", width: "130px" },
  { title: "VENCIMIENTOS", key: "scores.expiration", align: "center", width: "130px" },
  { title: "INVENTARIO", key: "scores.inventory", align: "center", width: "130px" },
  { title: "PREMIUM", key: "scores.premium", align: "center", width: "130px" },
  { title: "FACTURACIÓN", key: "scores.invoice", align: "center", width: "130px" },
  { title: "LIMPIEZA", key: "scores.cleaning", align: "center", width: "110px" },
  { title: "ESTRATÉGICO", key: "scores.strategy", align: "center", width: "130px" },
];

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

const getScoreColor = (key, item) => {
  if (key === 'growth') return item.growth > 0 ? 'success' : 'primary';
  if (key === 'expiration') return item.expirations > 10 ? 'error' : 'info';
  if (key === 'inventory') return 'info';
  if (key === 'premium') return 'warning';
  if (key === 'cleaning') return 'success';
  if (key === 'strategy') return 'deep-purple-accent-2';
  return 'primary';
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
    <!-- Vista de Escritorio: Tabla Premium -->
    <VCard v-if="!$vuetify.display.smAndDown" class="border shadow-sm overflow-hidden">
      <VDataTable
        :headers="headers"
        :items="props.items"
        item-value="id"
        class="premium-performance-table text-no-wrap"
        density="compact"
      >
        <!-- Custom Headers with Tooltips -->
        <template v-for="header in headers" :key="header.key" #[`header.${header.key}`]="{ column }">
          <div class="d-flex align-center gap-1 justify-center" v-if="header.key !== 'name' && header.key !== 'scores.total'">
            <span class="text-uppercase font-weight-black">{{ column.title }}</span>
            <VTooltip location="top" :text="getScoreInfo(header.key).desc">
              <template #activator="{ props: tooltipProps }">
                <VIcon v-bind="tooltipProps" icon="tabler-info-circle" size="12" class="text-disabled" />
              </template>
            </VTooltip>
          </div>
          <span v-else class="text-uppercase font-weight-black">{{ column.title }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <div class="position-relative">
              <VAvatar 
                :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'" 
                variant="tonal"
                size="34"
                class="rounded"
              >
                <VImg v-if="item.photo" :src="item.photo" cover />
                <span v-else class="text-super-xs font-weight-black">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
              </VAvatar>
              <VIcon
                v-if="props.items.indexOf(item) === 0"
                color="warning"
                icon="tabler-crown"
                size="16"
                class="position-absolute leader-crown"
              />
            </div>
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-black text-high-emphasis text-uppercase leading-tight">
                {{ item.name }} {{ item.last_name }}
              </span>
              <span class="text-super-xs text-disabled uppercase font-weight-black mt-1">ID: #{{ item.id }}</span>
            </div>
          </div>
        </template>

        <template #item.scores.total="{ item }">
          <div class="pe-2">
            <VChip
              :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'"
              variant="tonal"
              class="font-weight-black px-3 rounded text-uppercase"
              size="small"
            >
              {{ formatNumber(item.scores.total) }} PTS
            </VChip>
          </div>
        </template>

        <template v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" #[`item.scores.${key}`]="{ item }">
          <div class="score-cell-desktop py-1">
            <div class="d-flex justify-space-between text-super-xs mb-1 font-weight-black text-uppercase">
              <span :class="key === 'growth' ? (item.growth > 0 ? 'text-success' : (item.growth < 0 ? 'text-error' : '')) : ''">
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
              <span class="text-disabled">{{ formatNumber(item.scores[key]) }}</span>
            </div>
            <VProgressLinear
              :model-value="(item.scores[key] / getScoreInfo(`scores.${key}`).max) * 100"
              height="6"
              rounded
              :color="getScoreColor(key, item)"
              bg-color="secondary"
              bg-opacity="0.1"
            />
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Vista Móvil: Cards de Desempeño Premium -->
    <div v-else class="pa-1 bg-light">
      <VCard
        v-for="(item, index) in props.items"
        :key="item.id"
        class="rounded-lg border shadow-sm mb-4 overflow-hidden"
      >
        <!-- Header del Card -->
        <div class="pa-4 d-flex align-center gap-3">
          <div class="position-relative">
            <VAvatar 
              :color="index === 0 ? 'warning' : 'primary'" 
              variant="tonal"
              size="44"
              class="rounded shadow-sm"
            >
              <VImg v-if="item.photo" :src="item.photo" cover />
              <span v-else class="font-weight-black text-uppercase">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
            </VAvatar>
            <VIcon v-if="index === 0" icon="tabler-crown" color="warning" size="18" class="position-absolute leader-crown-mobile" />
          </div>
          
          <div class="d-flex flex-column flex-grow-1 min-width-0">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
              {{ item.name }} {{ item.last_name }}
            </span>
            <span class="text-super-xs text-primary font-weight-black mt-1 uppercase">Ranking #{{ index + 1 }} • PTS: {{ formatNumber(item.scores.total) }}</span>
          </div>
        </div>

        <VDivider class="border-opacity-10" />

        <!-- Grid de Desempeño Móvil -->
        <div class="pa-4 pt-1">
          <VRow dense>
            <VCol v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" cols="6" class="mt-3">
              <div class="pa-2 rounded-lg border border-opacity-10 bg-surface">
                <div class="d-flex align-center gap-1 mb-2">
                  <VIcon :icon="getScoreInfo(`scores.${key}`).icon" size="12" class="text-disabled" />
                  <span class="text-super-xs font-weight-black text-disabled uppercase truncate">{{ getScoreInfo(`scores.${key}`).title }}</span>
                </div>
                <div class="d-flex justify-space-between align-end mb-1">
                  <span class="text-xs font-weight-black text-uppercase truncate max-width-100">
                    {{ key === 'sales' ? formatCurrency(item.sales) : 
                       key === 'growth' ? `${item.growth}%` :
                       key === 'expiration' ? `${item.expirations}` :
                       `${item.scores[key]}`
                    }}
                  </span>
                  <span class="text-super-xs font-weight-black text-primary">{{ formatNumber(item.scores[key]) }} pts</span>
                </div>
                <VProgressLinear
                  :model-value="(item.scores[key] / getScoreInfo(`scores.${key}`).max) * 100"
                  height="4"
                  rounded
                  :color="getScoreColor(key, item)"
                  bg-opacity="0.1"
                />
              </div>
            </VCol>
          </VRow>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
:deep(.premium-performance-table) {
  background: transparent !important;

  thead th {
    background: white !important;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 900 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05rem !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  }

  tbody tr {
    transition: background-color 0.2s ease;
    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }
    td {
      padding-block: 8px !important;
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
    }
  }
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

.leader-crown {
  inset-block-start: -8px;
  inset-inline-end: -8px;
  transform: rotate(15deg);
}

.leader-crown-mobile {
  inset-block-start: -6px;
  inset-inline-end: -6px;
  transform: rotate(15deg);
}

.score-cell-desktop {
  min-inline-size: 100px;
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.max-width-100 {
  max-width: 100px;
}

.leading-tight {
  line-height: 1.25;
}

:deep(.v-data-table-footer) {
  display: none !important;
}
</style>

