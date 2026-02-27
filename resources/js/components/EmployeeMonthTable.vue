<script setup>
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
});

const headers = [
  { title: "Empleado", key: "name", width: "250px" },
  { title: "Puntaje Total", key: "scores.total", align: "end", sortable: true },
  { title: "Ventas", key: "scores.sales", align: "center", width: "120px" },
  { title: "Crecimiento", key: "scores.growth", align: "center", width: "120px" },
  { title: "Vencimientos", key: "scores.expiration", align: "center", width: "120px" },
  { title: "Inventario", key: "scores.inventory", align: "center", width: "150px" },
  { title: "Premium", key: "scores.premium", align: "center", width: "120px" },
  { title: "Facturación", key: "scores.invoice", align: "center", width: "120px" },
  { title: "Limpieza", key: "scores.cleaning", align: "center", width: "100px" },
  { title: "Estratégico", key: "scores.strategy", align: "center", width: "120px" },
];

const getScoreInfo = (key) => {
  const infos = {
    "scores.sales": { max: 25, desc: "Basado en el volumen total de ventas comparado con el líder." },
    "scores.growth": { max: 15, desc: "Porcentaje de crecimiento respecto al mes anterior." },
    "scores.expiration": { max: 15, desc: "Premia el bajo índice de productos vencidos en zona." },
    "scores.inventory": { max: 10, desc: "Calidad y cantidad de conteos cíclicos realizados." },
    "scores.premium": { max: 10, desc: "Ventas de productos de alto valor (>$15)." },
    "scores.invoice": { max: 15, desc: "Desempeño en carga, registro y archivo de facturas." },
    "scores.cleaning": { max: 5, desc: "Cumplimiento de cronograma de limpieza asignado." },
    "scores.strategy": { max: 5, desc: "Venta de productos/marcas priorizadas por la gerencia." },
  };
  return infos[key] || { max: 100, desc: "" };
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
  <VCard>
    <VDataTable
      :headers="headers"
      :items="props.items"
      item-value="id"
      class="text-no-wrap"
    >
      <!-- Custom Headers with Tooltips -->
      <template v-for="header in headers" :key="header.key" #[`header.${header.key}`]="{ column }">
        <div class="d-flex align-center gap-1 justify-center" v-if="header.key !== 'name' && header.key !== 'scores.total'">
          <span class="text-xs font-weight-bold text-uppercase">{{ column.title }}</span>
          <VTooltip location="top" :text="getScoreInfo(header.key).desc">
            <template #activator="{ props }">
              <VIcon v-bind="props" icon="tabler-info-circle" size="14" class="text-disabled" />
            </template>
          </VTooltip>
        </div>
        <span v-else class="text-xs font-weight-bold text-uppercase">{{ column.title }}</span>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <div class="position-relative">
            <VAvatar 
              :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'" 
              :variant="props.items.indexOf(item) === 0 ? 'elevated' : 'tonal'" 
              size="40"
              :class="props.items.indexOf(item) === 0 ? 'leader-avatar' : ''"
            >
              <VImg v-if="item.photo" :src="item.photo" />
              <span v-else>{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
            </VAvatar>
            <VIcon
              v-if="props.items.indexOf(item) === 0"
              color="warning"
              icon="tabler-crown"
              size="18"
              class="position-absolute leader-crown"
            />
          </div>
          <div class="d-flex flex-column">
            <span :class="['font-weight-bold', props.items.indexOf(item) === 0 ? 'text-warning' : 'text-high-emphasis']">
              {{ item.name }} {{ item.last_name }}
            </span>
            <span class="text-xs text-disabled">ID: {{ item.identification || item.id }}</span>
          </div>
        </div>
      </template>

      <template #item.scores.total="{ item }">
        <div class="d-flex flex-column align-end">
          <VChip
            :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'"
            :variant="props.items.indexOf(item) === 0 ? 'elevated' : 'tonal'"
            class="font-weight-black px-4"
            size="large"
          >
            {{ formatNumber(item.scores.total) }}
          </VChip>
          <span class="text-xs mt-1 text-disabled">Puntaje Final</span>
        </div>
      </template>

      <template #item.scores.sales="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <span class="font-weight-medium">{{ formatCurrency(item.sales) }}</span>
            <span class="text-disabled">{{ formatNumber(item.scores.sales) }}/25</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.sales / 25) * 100"
            height="6"
            rounded
            color="primary"
            bg-color="secondary"
            bg-opacity="0.1"
          />
        </div>
      </template>

      <template #item.scores.growth="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <span :class="['font-weight-medium', item.growth > 0 ? 'text-success' : 'text-error']">
              {{ item.growth > 0 ? '+' : '' }}{{ item.growth }}%
            </span>
            <span class="text-disabled">{{ formatNumber(item.scores.growth) }}/15</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.growth / 15) * 100"
            height="6"
            rounded
            :color="item.growth > 0 ? 'success' : 'primary'"
            bg-opacity="0.1"
          />
        </div>
      </template>

      <template #item.scores.expiration="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <span :class="['font-weight-medium', item.expirations > 10 ? 'text-error' : 'text-medium-emphasis']">
              {{ item.expirations }} unid.
            </span>
            <span class="text-disabled">{{ formatNumber(item.scores.expiration) }}/15</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.expiration / 15) * 100"
            height="6"
            rounded
            :color="item.expirations > 10 ? 'error' : 'info'"
            bg-opacity="0.1"
          />
        </div>
      </template>

      <template #item.scores.inventory="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <span class="font-weight-medium">{{ item.inventory_counted }} conteos</span>
            <span class="text-disabled">{{ formatNumber(item.scores.inventory) }}/10</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.inventory / 10) * 100"
            height="6"
            rounded
            color="info"
            bg-opacity="0.1"
          />
          <div class="text-[10px] text-error mt-1" v-if="item.inventory_errors > 0">
             {{ item.inventory_errors }} errores detectados
          </div>
        </div>
      </template>

      <template #item.scores.premium="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <span class="font-weight-medium">{{ item.premium_products }} unid.</span>
            <span class="text-disabled">{{ formatNumber(item.scores.premium) }}/10</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.premium / 10) * 100"
            height="6"
            rounded
            color="warning"
            bg-opacity="0.1"
          />
        </div>
      </template>

      <template #item.scores.invoice="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <div class="d-flex align-center gap-1">
              <span class="font-weight-medium">{{ item.invoice_items }}</span>
              <VIcon icon="tabler-packages" size="12" class="text-disabled" />
            </div>
            <span class="text-disabled">{{ formatNumber(item.scores.invoice) }}/15</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.invoice / 15) * 100"
            height="6"
            rounded
            color="primary"
            bg-opacity="0.1"
          />
        </div>
      </template>

      <template #item.scores.cleaning="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <span class="font-weight-bold">{{ Math.round((item.cleaning_completed / (item.cleaning_assigned || 1)) * 100) }}%</span>
            <span class="text-disabled">{{ formatNumber(item.scores.cleaning) }}/5</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.cleaning / 5) * 100"
            height="6"
            rounded
            color="success"
            bg-opacity="0.1"
          />
        </div>
      </template>

      <template #item.scores.strategy="{ item }">
        <div class="score-cell">
          <div class="d-flex justify-space-between text-xs mb-1">
            <span class="font-weight-medium">{{ item.strategy_sales }} unid.</span>
            <span class="text-disabled">{{ formatNumber(item.scores.strategy) }}/5</span>
          </div>
          <VProgressLinear
            :model-value="(item.scores.strategy / 5) * 100"
            height="6"
            rounded
            color="deep-purple-accent-2"
            bg-opacity="0.1"
          />
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>

<style scoped>
.leader-avatar {
  border: 2px solid #ffb400;
  box-shadow: 0 0 10px rgba(255, 180, 0, 40%);
}

.leader-crown {
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 20%));
  inset-block-start: -8px;
  inset-inline-end: -8px;
  transform: rotate(15deg);
}

.score-cell {
  min-inline-size: 100px;
  padding-block: 8px;
  padding-inline: 0;
}

:deep(.v-data-table__tr:first-child) {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}

:deep(.v-data-table-header__content) {
  justify-content: center !important;
}

.text-[10px] {
  font-size: 10px;
}
</style>
