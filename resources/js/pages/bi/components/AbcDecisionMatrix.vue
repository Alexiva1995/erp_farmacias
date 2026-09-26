<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  matrixData: {
    type: Object,
    default: () => ({}),
  },
  selectedQuadrant: {
    type: String,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  totalProducts: {
    type: Number,
    default: 0,
  },
  totalSales: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(['select-quadrant', 'clear-quadrant']);

// Estrategias operativas y de compras para los 9 cuadrantes Supply Chain
const quadrantConfigs = {
  AX: {
    title: 'Núcleo Estratégico',
    subtitle: 'Alta Venta · Demanda Estable',
    strategy: 'Automatizar reposición · Cero quiebres',
    color: 'success',
    badgeBg: '#E6F4EA',
    badgeColor: '#137333',
    icon: 'tabler-shield-check',
  },
  AY: {
    title: 'Volumen Fluctuante',
    subtitle: 'Alta Venta · Demanda Variable',
    strategy: 'Buffer de stock dinámico · Monitoreo',
    color: 'primary',
    badgeBg: '#E8F0FE',
    badgeColor: '#1A73E8',
    icon: 'tabler-chart-arrows',
  },
  AZ: {
    title: 'Crítico / Esporádico',
    subtitle: 'Alta Venta · Demanda Errática',
    strategy: 'Lotes pequeños · Compras ágiles',
    color: 'warning',
    badgeBg: '#FEF7E0',
    badgeColor: '#B06000',
    icon: 'tabler-alert-circle',
  },
  BX: {
    title: 'Estable Regular',
    subtitle: 'Venta Media · Demanda Estable',
    strategy: 'Reposición periódica estándar',
    color: 'info',
    badgeBg: '#E8F0FE',
    badgeColor: '#1A73E8',
    icon: 'tabler-refresh',
  },
  BY: {
    title: 'Oportunidad / Rotación',
    subtitle: 'Venta Media · Demanda Variable',
    strategy: 'Control de márgenes y promociones',
    color: 'secondary',
    badgeBg: '#F3E8FD',
    badgeColor: '#7627BB',
    icon: 'tabler-adjustments-horizontal',
  },
  BZ: {
    title: 'Alerta Variabilidad',
    subtitle: 'Venta Media · Demanda Errática',
    strategy: 'No sobrecomprar · Stock mínimo',
    color: 'warning',
    badgeBg: '#FEF7E0',
    badgeColor: '#B06000',
    icon: 'tabler-clock-exclamation',
  },
  CX: {
    title: 'Cola Larga Estable',
    subtitle: 'Baja Venta · Demanda Constante',
    strategy: 'Punto de reorden bajo · Mínimo stock',
    color: 'secondary',
    badgeBg: '#F1F3F4',
    badgeColor: '#3C4043',
    icon: 'tabler-dots',
  },
  CY: {
    title: 'Marginal Fluctuante',
    subtitle: 'Baja Venta · Demanda Variable',
    strategy: 'Racionalizar catálogo y sustitutos',
    color: 'secondary',
    badgeBg: '#F1F3F4',
    badgeColor: '#3C4043',
    icon: 'tabler-filter',
  },
  CZ: {
    title: 'Candidato a Liquidación',
    subtitle: 'Baja Venta · Demanda Errática',
    strategy: 'Ofertas Flash TPV · Descatalogar',
    color: 'error',
    badgeBg: '#FCE8E6',
    badgeColor: '#C5221F',
    icon: 'tabler-trash',
  },
};

const rows = [
  { key: 'A', label: 'Clase A (80% Ventas)', desc: 'Alto impacto financiero' },
  { key: 'B', label: 'Clase B (15% Ventas)', desc: 'Impacto intermedio' },
  { key: 'C', label: 'Clase C (5% Ventas)', desc: 'Bajo impacto / Cola larga' },
];

const cols = [
  { key: 'X', label: 'X (Estable)', desc: 'CV < 0.5 · Predecible' },
  { key: 'Y', label: 'Y (Fluctuante)', desc: '0.5 ≤ CV ≤ 1.0 · Variable' },
  { key: 'Z', label: 'Z (Errática)', desc: 'CV > 1.0 · Riesgo' },
];

const handleQuadrantClick = (code) => {
  if (props.selectedQuadrant === code) {
    emit('clear-quadrant');
  } else {
    emit('select-quadrant', code);
  }
};
</script>

<template>
  <VCard class="mb-4 rounded-lg border shadow-sm bg-surface overflow-hidden">
    <VCardText class="pa-4">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-center gap-2">
          <VAvatar color="info" variant="tonal" size="32" rounded="lg">
            <VIcon icon="tabler-grid-dots" size="18" />
          </VAvatar>
          <div>
            <h3 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-0 d-flex align-center gap-1.5">
              Matriz de Decisión Estratégica 3×3 (ABC Ventas vs. XYZ Predictibilidad)
            </h3>
            <span class="text-caption text-medium-emphasis">
              Haz clic en cualquier cuadrante para filtrar instantáneamente los productos y aplicar su política de inventario.
            </span>
          </div>
        </div>

        <div v-if="selectedQuadrant" class="d-flex align-center gap-1.5">
          <VChip color="primary" size="small" variant="flat" class="font-weight-bold">
            <VIcon icon="tabler-filter" size="13" class="me-1" />
            Filtrando Cuadrante: {{ selectedQuadrant }}
          </VChip>
          <VBtn size="x-small" variant="outlined" color="secondary" @click="emit('clear-quadrant')">
            <VIcon icon="tabler-x" size="12" class="me-0.5" />
            Limpiar Filtro
          </VBtn>
        </div>
      </div>

      <!-- Tabla Grilla 3x3 -->
      <div class="matrix-grid-wrapper overflow-x-auto">
        <table class="matrix-table w-100">
          <thead>
            <tr>
              <th class="matrix-corner-th pa-2 text-caption font-weight-bold text-disabled text-uppercase text-center">
                Ventas \ Demanda
              </th>
              <th
                v-for="col in cols"
                :key="col.key"
                class="matrix-header-th pa-2 text-center"
              >
                <div class="text-subtitle-2 font-weight-bold text-high-emphasis">{{ col.label }}</div>
                <div class="text-caption text-disabled" style="font-size: 0.68rem;">{{ col.desc }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.key">
              <td class="matrix-row-header-td pa-2 text-center" style="width: 140px;">
                <div class="text-subtitle-2 font-weight-bold text-high-emphasis">{{ row.label }}</div>
                <div class="text-caption text-disabled" style="font-size: 0.68rem;">{{ row.desc }}</div>
              </td>
              <td
                v-for="col in cols"
                :key="col.key"
                class="pa-1.5 matrix-cell-td"
                style="width: 28%;"
              >
                <div
                  v-if="matrixData[row.key + col.key]"
                  class="quadrant-card rounded-lg pa-2.5 border transition-all cursor-pointer h-100 d-flex flex-column justify-space-between"
                  :class="{
                    'active-quadrant': selectedQuadrant === (row.key + col.key),
                    'inactive-quadrant': selectedQuadrant && selectedQuadrant !== (row.key + col.key),
                  }"
                  :style="{
                    borderColor: selectedQuadrant === (row.key + col.key) ? 'rgb(var(--v-theme-primary))' : 'rgba(var(--v-border-color), 0.12)',
                    background: selectedQuadrant === (row.key + col.key) ? 'rgba(var(--v-theme-primary), 0.05)' : 'rgba(var(--v-theme-surface), 1)',
                  }"
                  @click="handleQuadrantClick(row.key + col.key)"
                >
                  <!-- Cabecera del Cuadrante -->
                  <div class="d-flex align-center justify-space-between mb-1.5">
                    <span
                      class="px-2 py-0.5 rounded-pill text-caption font-weight-black"
                      :style="{
                        background: quadrantConfigs[row.key + col.key]?.badgeBg || '#F1F3F4',
                        color: quadrantConfigs[row.key + col.key]?.badgeColor || '#3C4043',
                        fontSize: '0.72rem',
                      }"
                    >
                      {{ row.key + col.key }}
                    </span>
                    <span class="text-caption font-weight-bold text-high-emphasis">
                      {{ matrixData[row.key + col.key].count }} SKUs
                      <span class="text-disabled font-weight-normal text-super-xs">
                        ({{ totalProducts > 0 ? Math.round((matrixData[row.key + col.key].count / totalProducts) * 100) : 0 }}%)
                      </span>
                    </span>
                  </div>

                  <!-- Título y Estrategia -->
                  <div class="mb-2">
                    <div class="text-caption font-weight-bold text-high-emphasis leading-tight">
                      {{ quadrantConfigs[row.key + col.key]?.title }}
                    </div>
                    <div class="text-super-xs text-medium-emphasis mt-0.5">
                      {{ quadrantConfigs[row.key + col.key]?.strategy }}
                    </div>
                  </div>

                  <!-- Métricas de Ventas y Capital Inmovilizado -->
                  <div class="d-flex align-center justify-space-between pt-1 border-t border-opacity-10 mt-auto text-super-xs">
                    <span class="text-medium-emphasis">
                      Ventas: <strong class="text-high-emphasis">{{ formatCurrency(matrixData[row.key + col.key].total_sales) }}</strong>
                    </span>
                    <span class="text-medium-emphasis">
                      Stock: <strong class="text-high-emphasis">{{ formatCurrency(matrixData[row.key + col.key].inventory_value) }}</strong>
                    </span>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.matrix-table {
  border-collapse: separate;
  border-spacing: 6px;
}

.matrix-corner-th,
.matrix-header-th,
.matrix-row-header-td {
  background: rgba(var(--v-theme-surface-variant), 0.2);
  border-radius: 6px;
}

.quadrant-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: all 0.2s ease-in-out;
}

.quadrant-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  border-color: rgba(var(--v-theme-primary), 0.4) !important;
}

.active-quadrant {
  box-shadow: 0 0 0 2px rgb(var(--v-theme-primary)) !important;
  transform: scale(1.01);
}

.inactive-quadrant {
  opacity: 0.55;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.transition-all {
  transition: all 0.2s ease;
}
</style>
