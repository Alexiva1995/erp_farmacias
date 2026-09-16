<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";

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
  { title: "EMPLEADO", key: "name", sortable: true, width: "180px" },
  { title: "PUNTAJE", key: "scores.total", sortable: true, align: "center", width: "110px" },
  { title: "VENTAS", key: "scores.sales", sortable: true, align: "end", width: "135px" },
  { title: "CRECIMIENTO", key: "scores.growth", sortable: true, align: "end", width: "135px" },
  { title: "VENCIMIENTOS", key: "scores.expiration", sortable: true, align: "end", width: "130px" },
  { title: "INVENTARIO", key: "scores.inventory", sortable: true, align: "end", width: "130px" },
  { title: "PREMIUM", key: "scores.premium", sortable: true, align: "end", width: "120px" },
  { title: "FACTURACIÓN", key: "scores.invoice", sortable: true, align: "end", width: "125px" },
  { title: "LIMPIEZA", key: "scores.cleaning", sortable: true, align: "end", width: "120px" },
  { title: "ESTRATÉGICO", key: "scores.strategy", sortable: true, align: "end", width: "120px" },
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
    "scores.inventory": { title: "Inventario", icon: "tabler-package", max: 10, desc: "1 pt (1-60), 1.5 pts (61-120), 2 pts (121-180), 2.5 pts (181-240), 3 pts (>240 diarios), -10/-20 pts discrepancias." },
    "scores.premium": { title: "Premium", icon: "tabler-pills", max: 10, desc: "Ventas de productos de alto valor (>$15)." },
    "scores.invoice": { title: "Facturación", icon: "tabler-file-invoice", max: 10, desc: "1 pt reg. auto, 2 pts reg. manual, +0.05 pts/ítem auto, +0.25 pts/ítem manual, +0.125 pts/ítem ubicado." },
    "scores.cleaning": { title: "Limpieza", icon: "tabler-brush", max: 5, desc: "Cumplimiento de cronograma de limpieza." },
    "scores.strategy": { title: "Estratégico", icon: "tabler-target", max: 5, desc: "Venta de marcas priorizadas." },
  };
  return infos[key] || { title: key, icon: "tabler-info-circle", max: 100, desc: "" };
};

const getPointsTextColor = (points, max) => {
  if (points < 0) return "text-error";
  const ratio = (points || 0) / (max || 1);
  if (ratio >= 0.8) return "text-success";
  if (ratio >= 0.4) return "text-primary";
  if (ratio > 0) return "text-warning";
  return "text-disabled";
};

const formatPointsText = (points) => {
  const num = Number(points) || 0;
  if (num > 0) return `+${formatNumber(num)} pts`;
  if (num < 0) return `${formatNumber(num)} pts`;
  return `0 pts`;
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
    <!-- Desktop View -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTable
          :headers="headers"
          :items="props.items"
          :loading="props.loading"
          item-value="id"
          class="text-no-wrap clean-table-aligned"
          density="comfortable"
        >
          <template #no-data>
            <AppEmptyState
              title="No se encontraron empleados"
              message="No hay datos disponibles para el periodo o criterio de búsqueda seleccionado."
              icon="tabler-users-minus"
            />
          </template>

          <!-- Custom Headers with Tooltips -->
          <template v-for="header in headers" :key="header.key" #[`header.${header.key}`]="{ column }">
            <div 
              class="d-flex align-center gap-1"
              :class="header.align === 'end' ? 'justify-end' : (header.align === 'center' ? 'justify-center' : 'justify-start')"
              v-if="header.key !== 'name' && header.key !== 'scores.total'"
            >
              <span>{{ column.title }}</span>
              <VTooltip location="top" :text="getScoreInfo(header.key).desc">
                <template #activator="{ props: tooltipProps }">
                  <VIcon v-bind="tooltipProps" icon="tabler-info-circle" size="13" class="text-disabled cursor-pointer" />
                </template>
              </VTooltip>
            </div>
            <span v-else>{{ column.title }}</span>
          </template>

          <!-- Empleado -->
          <template #item.name="{ item }">
            <div class="d-flex align-center gap-3 py-2">
              <div class="position-relative">
                <VAvatar 
                  :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'" 
                  variant="tonal"
                  size="36"
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

          <!-- Puntaje Total Destacado (Única Badge con Color de Fondo) -->
          <template #item.scores.total="{ item }">
            <div class="d-flex justify-center">
              <VChip
                :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'"
                variant="flat"
                class="font-weight-bold px-3 rounded text-uppercase"
                size="small"
              >
                {{ formatNumber(item.scores.total) }} pts
              </VChip>
            </div>
          </template>

          <!-- Métricas Numéricas Limpias Apiladas Verticalmente (Alineadas a la Derecha) -->
          <template v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" #[`item.scores.${key}`]="{ item }">
            <div class="metric-cell-vertical py-1">
              <!-- Línea 1: Métrica Real (Negrita, texto principal) con Tooltip de desglose si aplica -->
              <div class="d-flex align-center justify-end gap-1">
                <span 
                  class="text-xs font-weight-bold tabular-nums leading-tight"
                  :class="key === 'growth' ? (item.growth > 0 ? 'text-success' : (item.growth < 0 ? 'text-error' : 'text-high-emphasis')) : 'text-high-emphasis'"
                >
                  {{ key === 'sales' ? formatCurrency(item.sales) : 
                     key === 'growth' ? `${item.growth}%` :
                     key === 'expiration' ? `${item.expirations} U.` :
                     key === 'inventory' ? `${item.inventory_counted} C.` :
                     key === 'premium' ? `${item.premium_products} U.` :
                     key === 'invoice' ? `${formatNumber(item.invoice_points ?? item.invoice_breakdown?.total_points ?? 0)} P.` :
                     key === 'cleaning' ? `${Math.round((item.cleaning_completed / (item.cleaning_assigned || 1)) * 100)}%` :
                     `${item.strategy_sales} U.`
                  }}
                </span>

                <!-- Tooltip de Desglose para Inventario -->
                <VTooltip v-if="key === 'inventory' && item.inventory_breakdown" location="top" max-width="260">
                  <template #activator="{ props: tooltipProps }">
                    <VIcon v-bind="tooltipProps" icon="tabler-help-circle" size="12" class="text-medium-emphasis cursor-pointer" />
                  </template>
                  <div class="pa-1 text-xs">
                    <div class="font-weight-bold mb-1 border-b pb-1">Desglose de Inventario:</div>
                    <div class="d-flex justify-space-between py-0.5">
                      <span>• Conteos Productos ({{ formatNumber(item.inventory_breakdown.product_count ?? 0) }}):</span>
                      <strong class="text-success ms-2">+{{ item.inventory_breakdown.product_points }} pts</strong>
                    </div>
                    <div class="d-flex justify-space-between py-0.5">
                      <span>• Conteos Ventas ({{ formatNumber(item.inventory_breakdown.sale_count ?? 0) }}):</span>
                      <strong class="text-success ms-2">+{{ item.inventory_breakdown.sale_points }} pts</strong>
                    </div>
                    <div class="d-flex justify-space-between py-0.5">
                      <span>• Conteos Facturas ({{ formatNumber(item.inventory_breakdown.invoice_count ?? 0) }}):</span>
                      <strong class="text-success ms-2">+{{ item.inventory_breakdown.invoice_count_points }} pts</strong>
                    </div>
                    <div class="d-flex justify-space-between py-0.5">
                      <span>• Como Supervisor ({{ formatNumber(item.inventory_breakdown.supervisor_count ?? 0) }}):</span>
                      <strong class="text-success ms-2">+{{ item.inventory_breakdown.supervisor_points }} pts</strong>
                    </div>
                    <div v-if="item.inventory_breakdown.penalties > 0" class="d-flex justify-space-between py-0.5 text-error">
                      <span>• Penalizaciones:</span>
                      <strong class="ms-2">-{{ item.inventory_breakdown.penalties }} pts</strong>
                    </div>
                    <div class="d-flex justify-space-between pt-1 mt-1 border-t font-weight-bold">
                      <span>Total Neto:</span>
                      <span>{{ item.inventory_breakdown.net_points }} pts</span>
                    </div>
                  </div>
                </VTooltip>

                <!-- Tooltip de Desglose para Facturación -->
                <VTooltip v-if="key === 'invoice' && item.invoice_breakdown" location="top" max-width="260">
                  <template #activator="{ props: tooltipProps }">
                    <VIcon v-bind="tooltipProps" icon="tabler-help-circle" size="12" class="text-medium-emphasis cursor-pointer" />
                  </template>
                  <div class="pa-1 text-xs">
                    <div class="font-weight-bold mb-1 border-b pb-1">Desglose de Facturación:</div>
                    <div class="d-flex justify-space-between py-0.5">
                      <span>• Registro Facturas:</span>
                      <strong class="text-success ms-2">+{{ item.invoice_breakdown.header_points }} pts</strong>
                    </div>
                    <div class="d-flex justify-space-between py-0.5">
                      <span>• Ítems Cargados:</span>
                      <strong class="text-success ms-2">+{{ item.invoice_breakdown.loaded_items_points }} pts</strong>
                    </div>
                    <div class="d-flex justify-space-between py-0.5">
                      <span>• Ítems Ubicados:</span>
                      <strong class="text-success ms-2">+{{ item.invoice_breakdown.organized_items_points }} pts</strong>
                    </div>
                    <div class="d-flex justify-space-between pt-1 mt-1 border-t font-weight-bold">
                      <span>Total Ganado:</span>
                      <span>{{ item.invoice_breakdown.total_points }} pts</span>
                    </div>
                  </div>
                </VTooltip>
              </div>

              <!-- Línea 2: Puntos Ganados como Subtexto Limpio (Sin cajitas de color) -->
              <span 
                class="points-subtext tabular-nums font-weight-medium"
                :class="getPointsTextColor(item.scores[key], getScoreInfo(`scores.${key}`).max)"
              >
                {{ formatPointsText(item.scores[key]) }}
              </span>
            </div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

      <AppEmptyState
        v-if="props.items.length === 0 && !props.loading"
        title="No se encontraron empleados"
        message="No hay datos disponibles para el periodo o criterio de búsqueda seleccionado."
        icon="tabler-users-minus"
      />

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="(item, index) in props.items"
          :key="item.id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <!-- Header del Card -->
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3 min-width-0">
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
                <div class="d-flex flex-column min-width-0">
                  <span class="text-primary font-weight-black text-xs uppercase mb-0.5">
                    ID #{{ item.id }}
                  </span>
                  <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                    {{ item.name }} {{ item.last_name }}
                  </h3>
                  <span class="text-super-xs text-medium-emphasis font-weight-medium uppercase">
                    Puesto #{{ index + 1 }}
                  </span>
                </div>
              </div>

              <VChip size="small" :color="index === 0 ? 'warning' : 'primary'" variant="flat" class="font-weight-bold rounded px-2">
                {{ formatNumber(item.scores.total) }} pts
              </VChip>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Grid de Desempeño Móvil Limpio con Jerarquía Vertical -->
            <VRow dense>
              <VCol v-for="key in ['sales', 'growth', 'expiration', 'inventory', 'premium', 'invoice', 'cleaning', 'strategy']" :key="key" cols="6" class="pa-1">
                <div class="pa-2 rounded-lg bg-light border d-flex flex-column justify-space-between h-100">
                  <div class="d-flex align-center gap-1 mb-1">
                    <VIcon :icon="getScoreInfo(`scores.${key}`).icon" size="13" class="text-medium-emphasis" />
                    <span class="text-super-xs font-weight-bold text-disabled uppercase truncate">{{ getScoreInfo(`scores.${key}`).title }}</span>
                  </div>
                  <div class="d-flex flex-column align-end">
                    <div class="d-flex align-center gap-1">
                      <span 
                        class="text-xs font-weight-bold tabular-nums truncate leading-tight"
                        :class="key === 'growth' ? (item.growth > 0 ? 'text-success' : (item.growth < 0 ? 'text-error' : 'text-high-emphasis')) : 'text-high-emphasis'"
                      >
                        {{ key === 'sales' ? formatCurrency(item.sales) : 
                           key === 'growth' ? `${item.growth}%` :
                           key === 'expiration' ? `${item.expirations} U.` :
                           key === 'inventory' ? `${item.inventory_counted} C.` :
                           key === 'premium' ? `${item.premium_products} U.` :
                           key === 'invoice' ? `${formatNumber(item.invoice_points ?? item.invoice_breakdown?.total_points ?? 0)} P.` :
                           key === 'cleaning' ? `${Math.round((item.cleaning_completed / (item.cleaning_assigned || 1)) * 100)}%` :
                           `${item.strategy_sales} U.`
                        }}
                      </span>

                      <!-- Tooltip de Desglose para Inventario (Móvil) -->
                      <VTooltip v-if="key === 'inventory' && item.inventory_breakdown" location="top" max-width="260">
                        <template #activator="{ props: tooltipProps }">
                          <VIcon v-bind="tooltipProps" icon="tabler-help-circle" size="12" class="text-medium-emphasis cursor-pointer" />
                        </template>
                        <div class="pa-1 text-xs">
                          <div class="font-weight-bold mb-1 border-b pb-1">Desglose de Inventario:</div>
                          <div class="d-flex justify-space-between py-0.5">
                            <span>• Conteos Productos ({{ formatNumber(item.inventory_breakdown.product_count ?? 0) }}):</span>
                            <strong class="text-success ms-2">+{{ item.inventory_breakdown.product_points }} pts</strong>
                          </div>
                          <div class="d-flex justify-space-between py-0.5">
                            <span>• Conteos Ventas ({{ formatNumber(item.inventory_breakdown.sale_count ?? 0) }}):</span>
                            <strong class="text-success ms-2">+{{ item.inventory_breakdown.sale_points }} pts</strong>
                          </div>
                          <div class="d-flex justify-space-between py-0.5">
                            <span>• Conteos Facturas ({{ formatNumber(item.inventory_breakdown.invoice_count ?? 0) }}):</span>
                            <strong class="text-success ms-2">+{{ item.inventory_breakdown.invoice_count_points }} pts</strong>
                          </div>
                          <div class="d-flex justify-space-between py-0.5">
                            <span>• Como Supervisor ({{ formatNumber(item.inventory_breakdown.supervisor_count ?? 0) }}):</span>
                            <strong class="text-success ms-2">+{{ item.inventory_breakdown.supervisor_points }} pts</strong>
                          </div>
                          <div v-if="item.inventory_breakdown.penalties > 0" class="d-flex justify-space-between py-0.5 text-error">
                            <span>• Penalizaciones:</span>
                            <strong class="ms-2">-{{ item.inventory_breakdown.penalties }} pts</strong>
                          </div>
                          <div class="d-flex justify-space-between pt-1 mt-1 border-t font-weight-bold">
                            <span>Total Neto:</span>
                            <span>{{ item.inventory_breakdown.net_points }} pts</span>
                          </div>
                        </div>
                      </VTooltip>

                      <!-- Tooltip de Desglose para Facturación (Móvil) -->
                      <VTooltip v-if="key === 'invoice' && item.invoice_breakdown" location="top" max-width="260">
                        <template #activator="{ props: tooltipProps }">
                          <VIcon v-bind="tooltipProps" icon="tabler-help-circle" size="12" class="text-medium-emphasis cursor-pointer" />
                        </template>
                        <div class="pa-1 text-xs">
                          <div class="font-weight-bold mb-1 border-b pb-1">Desglose de Facturación:</div>
                          <div class="d-flex justify-space-between py-0.5">
                            <span>• Registro Facturas:</span>
                            <strong class="text-success ms-2">+{{ item.invoice_breakdown.header_points }} pts</strong>
                          </div>
                          <div class="d-flex justify-space-between py-0.5">
                            <span>• Ítems Cargados:</span>
                            <strong class="text-success ms-2">+{{ item.invoice_breakdown.loaded_items_points }} pts</strong>
                          </div>
                          <div class="d-flex justify-space-between py-0.5">
                            <span>• Ítems Ubicados:</span>
                            <strong class="text-success ms-2">+{{ item.invoice_breakdown.organized_items_points }} pts</strong>
                          </div>
                          <div class="d-flex justify-space-between pt-1 mt-1 border-t font-weight-bold">
                            <span>Total Ganado:</span>
                            <span>{{ item.invoice_breakdown.total_points }} pts</span>
                          </div>
                        </div>
                      </VTooltip>
                    </div>

                    <span 
                      class="points-subtext tabular-nums font-weight-medium mt-0-5"
                      :class="getPointsTextColor(item.scores[key], getScoreInfo(`scores.${key}`).max)"
                    >
                      {{ formatPointsText(item.scores[key]) }}
                    </span>
                  </div>
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
.metric-cell-vertical {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  line-height: 1.2;
}

.points-subtext {
  font-size: 0.6875rem !important; /* 11px */
  line-height: 1.1;
  margin-top: 1px;
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

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
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

.mt-0-5 {
  margin-top: 2px !important;
}

.gap-1 { gap: 4px !important; }
.gap-3 { gap: 12px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>


