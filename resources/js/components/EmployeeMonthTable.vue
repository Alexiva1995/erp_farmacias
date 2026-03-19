<script setup>
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
});


const headers = [
  { title: "Empleado", key: "name", width: "250px" },
  { title: "Puntaje Final", key: "scores.total", align: "end", width: "130px" },
  { title: "Ventas", key: "scores.sales", align: "center", width: "130px" },
  { title: "Crecimiento", key: "scores.growth", align: "center", width: "130px" },
  { title: "Vencimientos", key: "scores.expiration", align: "center", width: "130px" },
  { title: "Inventario", key: "scores.inventory", align: "center", width: "130px" },
  { title: "Premium", key: "scores.premium", align: "center", width: "130px" },
  { title: "Facturación", key: "scores.invoice", align: "center", width: "130px" },
  { title: "Limpieza", key: "scores.cleaning", align: "center", width: "110px" },
  { title: "Estratégico", key: "scores.strategy", align: "center", width: "130px" },
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
    <VCard class="rounded-xl border-0 shadow-sm overflow-hidden d-none d-md-block">
      <VDataTable
        :headers="headers"
        :items="props.items"
        item-value="id"
        class="premium-performance-table text-no-wrap"
      >
        <!-- Custom Headers with Tooltips -->
        <template v-for="header in headers" :key="header.key" #[`header.${header.key}`]="{ column }">
          <div class="d-flex align-center gap-1 justify-center" v-if="header.key !== 'name' && header.key !== 'scores.total'">
            <span class="text-super-xs font-weight-black text-uppercase">{{ column.title }}</span>
            <VTooltip location="top" :text="getScoreInfo(header.key).desc">
              <template #activator="{ props: tooltipProps }">
                <VIcon v-bind="tooltipProps" icon="tabler-info-circle" size="14" class="text-disabled" />
              </template>
            </VTooltip>
          </div>
          <span v-else class="text-super-xs font-weight-black text-uppercase">{{ column.title }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-3">
            <div class="position-relative">
              <VAvatar 
                :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'" 
                :variant="props.items.indexOf(item) === 0 ? 'elevated' : 'tonal'" 
                size="42"
                v-if="item.photo"
              >
                <VImg :src="item.photo" />
              </VAvatar>
              <VAvatar
                v-else
                :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'"
                variant="tonal"
                size="42"
              >
                <span class="font-weight-bold text-lg">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
              </VAvatar>
              <VIcon
                v-if="props.items.indexOf(item) === 0"
                color="warning"
                icon="tabler-crown"
                size="20"
                class="position-absolute leader-crown"
              />
            </div>
            <div class="d-flex flex-column">
              <span :class="['font-weight-black', props.items.indexOf(item) === 0 ? 'text-warning' : 'text-high-emphasis']">
                {{ item.name }} {{ item.last_name }}
              </span>
              <span class="text-super-xs text-disabled uppercase">ID: {{ item.identification || item.id }}</span>
            </div>
          </div>
        </template>

        <template #item.scores.total="{ item }">
          <div class="d-flex flex-column align-end pe-4">
            <VChip
              :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'"
              :variant="props.items.indexOf(item) === 0 ? 'elevated' : 'tonal'"
              class="font-weight-black px-4 rounded-lg"
              size="large"
            >
              {{ formatNumber(item.scores.total) }}
            </VChip>
          </div>
        </template>

        <template v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" #[`item.scores.${key}`]="{ item }">
          <div class="score-cell-desktop py-2">
            <div class="d-flex justify-space-between text-xs mb-1">
              <span class="font-weight-bold" :class="key === 'growth' ? (item.growth > 0 ? 'text-success' : 'text-error') : ''">
                {{ key === 'sales' ? formatCurrency(item.sales) : 
                   key === 'growth' ? `${item.growth > 0 ? '+' : ''}${item.growth}%` :
                   key === 'expiration' ? `${item.expirations} u.` :
                   key === 'inventory' ? `${item.inventory_counted} c.` :
                   key === 'premium' ? `${item.premium_products} u.` :
                   key === 'invoice' ? `${item.invoice_items} icon.` :
                   key === 'cleaning' ? `${Math.round((item.cleaning_completed / (item.cleaning_assigned || 1)) * 100)}%` :
                   `${item.strategy_sales} u.`
                }}
              </span>
              <span class="text-disabled font-weight-bold">{{ formatNumber(item.scores[key]) }}/{{ getScoreInfo(`scores.${key}`).max }}</span>
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

    <!-- Vista Móvil: Cards de Desempeño -->
    <div class="d-flex flex-column gap-4 d-md-none">
      <VCard
        v-for="(item, index) in props.items"
        :key="item.id"
        class="performance-card-mobile rounded-xl border-0 shadow-sm overflow-hidden"
        :class="{ 'leader-card-border': index === 0 }"
      >
        <!-- Header del Card -->
        <div class="pa-4 d-flex align-center gap-3" :class="index === 0 ? 'bg-warning-lighten-5' : 'bg-surface'">
          <div class="position-relative">
            <VAvatar 
              :color="index === 0 ? 'warning' : 'primary'" 
              :variant="index === 0 ? 'elevated' : 'tonal'" 
              size="48"
              class="rounded-lg shadow-sm"
            >
              <VImg v-if="item.photo" :src="item.photo" />
              <span v-else class="font-weight-black">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
            </VAvatar>
            <VIcon v-if="index === 0" icon="tabler-crown" color="warning" size="20" class="position-absolute leader-crown-mobile" />
          </div>
          
          <div class="d-flex flex-column flex-grow-1">
            <span class="text-base font-weight-black leading-tight" :class="index === 0 ? 'text-warning' : ''">
              {{ item.name }} {{ item.last_name }}
            </span>
            <span class="text-super-xs text-disabled uppercase">Ranking #{{ index + 1 }}</span>
          </div>

          <VChip
            :color="index === 0 ? 'warning' : 'primary'"
            variant="flat"
            class="font-weight-black px-4 rounded-lg shadow-sm"
          >
            {{ formatNumber(item.scores.total) }} pts
          </VChip>
        </div>

        <VDivider class="opacity-10" />

        <!-- Grid de Desempeño Móvil -->
        <div class="pa-4">
          <div class="performance-grid">
            <div v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" class="perf-item pa-2 rounded-lg border border-opacity-10">
              <div class="d-flex align-center gap-1 mb-1">
                <VIcon :icon="getScoreInfo(`scores.${key}`).icon" size="14" class="text-disabled" />
                <span class="text-super-xs font-weight-black text-disabled uppercase truncate">{{ getScoreInfo(`scores.${key}`).title }}</span>
              </div>
              <div class="d-flex justify-space-between align-end">
                <span class="text-xs font-weight-black">
                  {{ key === 'sales' ? formatCurrency(item.sales) : 
                     key === 'growth' ? `${item.growth}%` :
                     key === 'expiration' ? `${item.expirations}` :
                     `${item.scores[key]}`
                  }}
                </span>
                <span class="text-super-xs font-weight-bold text-primary">{{ formatNumber(item.scores[key]) }} pts</span>
              </div>
              <VProgressLinear
                :model-value="(item.scores[key] / getScoreInfo(`scores.${key}`).max) * 100"
                height="4"
                rounded
                :color="getScoreColor(key, item)"
                bg-opacity="0.1"
                class="mt-1"
              />
            </div>
          </div>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.625rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

.leader-crown {
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 10%));
  inset-block-start: -10px;
  inset-inline-end: -8px;
  transform: rotate(15deg);
}

.leader-crown-mobile {
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 20%));
  inset-block-start: -8px;
  inset-inline-end: -6px;
  transform: rotate(15deg);
}

.score-cell-desktop {
  min-inline-size: 110px;
}

:deep(.premium-performance-table) {
  .v-data-table-header th {
    background-color: rgba(var(--v-theme-surface-variant), 0.05) !important;
    text-transform: uppercase;
  }

  .v-data-table__tr:hover {
    background-color: rgba(var(--v-theme-primary), 0.02) !important;
  }
}

.performance-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(2, 1fr);
}

.bg-warning-lighten-5 {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}

.leader-card-border {
  border: 1px solid rgba(var(--v-theme-warning), 0.3) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 600px) {
  .performance-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
