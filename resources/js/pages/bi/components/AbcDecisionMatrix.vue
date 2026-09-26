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

// Estrategias operativas y de compras para los 9 cuadrantes Supply Chain con Mapa de Calor Semántico
const quadrantConfigs = {
  // 🟢 Zona Verde Esmeralda (Éxito y Núcleo Estratégico)
  AX: {
    title: 'Núcleo Estratégico',
    subtitle: 'Alta Venta · Demanda Estable',
    strategy: 'Automatizar reposición · Cero quiebres',
    color: 'success',
    cardBg: 'rgba(16, 185, 129, 0.07)',
    cardBorder: 'rgba(16, 185, 129, 0.28)',
    hoverBorder: '#10B981',
    activeGlow: 'rgba(16, 185, 129, 0.25)',
    badgeBg: '#D1FAE5',
    badgeColor: '#065F46',
    badgeBorder: '#6EE7B7',
    icon: 'tabler-shield-check',
  },
  BX: {
    title: 'Estable Regular',
    subtitle: 'Venta Media · Demanda Estable',
    strategy: 'Reposición periódica estándar',
    color: 'info',
    cardBg: 'rgba(20, 184, 166, 0.06)',
    cardBorder: 'rgba(20, 184, 166, 0.24)',
    hoverBorder: '#14B8A6',
    activeGlow: 'rgba(20, 184, 166, 0.25)',
    badgeBg: '#CCFBF1',
    badgeColor: '#115E59',
    badgeBorder: '#5EEAD4',
    icon: 'tabler-refresh',
  },

  // 🔵 Zona Azul / Violeta (Oportunidad y Fluctuación)
  AY: {
    title: 'Volumen Fluctuante',
    subtitle: 'Alta Venta · Demanda Variable',
    strategy: 'Buffer de stock dinámico · Monitoreo',
    color: 'primary',
    cardBg: 'rgba(59, 130, 246, 0.07)',
    cardBorder: 'rgba(59, 130, 246, 0.25)',
    hoverBorder: '#3B82F6',
    activeGlow: 'rgba(59, 130, 246, 0.25)',
    badgeBg: '#DBEAFE',
    badgeColor: '#1E40AF',
    badgeBorder: '#93C5FD',
    icon: 'tabler-chart-arrows',
  },
  BY: {
    title: 'Oportunidad / Rotación',
    subtitle: 'Venta Media · Demanda Variable',
    strategy: 'Control de márgenes y promociones',
    color: 'secondary',
    cardBg: 'rgba(139, 92, 246, 0.07)',
    cardBorder: 'rgba(139, 92, 246, 0.25)',
    hoverBorder: '#8B5CF6',
    activeGlow: 'rgba(139, 92, 246, 0.25)',
    badgeBg: '#EDE9FE',
    badgeColor: '#5B21B6',
    badgeBorder: '#C4B5FD',
    icon: 'tabler-adjustments-horizontal',
  },
  CX: {
    title: 'Cola Larga Estable',
    subtitle: 'Baja Venta · Demanda Constante',
    strategy: 'Punto de reorden bajo · Mínimo stock',
    color: 'secondary',
    cardBg: 'rgba(100, 116, 139, 0.05)',
    cardBorder: 'rgba(100, 116, 139, 0.18)',
    hoverBorder: '#64748B',
    activeGlow: 'rgba(100, 116, 139, 0.2)',
    badgeBg: '#F1F5F9',
    badgeColor: '#334155',
    badgeBorder: '#CBD5E1',
    icon: 'tabler-dots',
  },
  CY: {
    title: 'Marginal Fluctuante',
    subtitle: 'Baja Venta · Demanda Variable',
    strategy: 'Racionalizar catálogo y sustitutos',
    color: 'secondary',
    cardBg: 'rgba(107, 114, 128, 0.05)',
    cardBorder: 'rgba(107, 114, 128, 0.18)',
    hoverBorder: '#6B7280',
    activeGlow: 'rgba(107, 114, 128, 0.2)',
    badgeBg: '#F3F4F6',
    badgeColor: '#374151',
    badgeBorder: '#D1D5DB',
    icon: 'tabler-filter',
  },

  // 🟡 Zona Ámbar / Naranja (Atención y Alerta de Variabilidad)
  AZ: {
    title: 'Crítico / Esporádico',
    subtitle: 'Alta Venta · Demanda Errática',
    strategy: 'Lotes pequeños · Compras ágiles',
    color: 'warning',
    cardBg: 'rgba(245, 158, 11, 0.08)',
    cardBorder: 'rgba(245, 158, 11, 0.28)',
    hoverBorder: '#F59E0B',
    activeGlow: 'rgba(245, 158, 11, 0.25)',
    badgeBg: '#FEF3C7',
    badgeColor: '#92400E',
    badgeBorder: '#FCD34D',
    icon: 'tabler-alert-circle',
  },
  BZ: {
    title: 'Alerta Variabilidad',
    subtitle: 'Venta Media · Demanda Errática',
    strategy: 'No sobrecomprar · Stock mínimo',
    color: 'warning',
    cardBg: 'rgba(249, 115, 22, 0.08)',
    cardBorder: 'rgba(249, 115, 22, 0.28)',
    hoverBorder: '#F97316',
    activeGlow: 'rgba(249, 115, 22, 0.25)',
    badgeBg: '#FFEDD5',
    badgeColor: '#9A3412',
    badgeBorder: '#FDBA74',
    icon: 'tabler-clock-exclamation',
  },

  // 🔴 Zona Rojo Coral / Peligro (Candidato a Liquidación)
  CZ: {
    title: 'Candidato a Liquidación',
    subtitle: 'Baja Venta · Demanda Errática',
    strategy: 'Ofertas Flash TPV · Descatalogar',
    color: 'error',
    cardBg: 'rgba(239, 68, 68, 0.09)',
    cardBorder: 'rgba(239, 68, 68, 0.32)',
    hoverBorder: '#EF4444',
    activeGlow: 'rgba(239, 68, 68, 0.3)',
    badgeBg: '#FEE2E2',
    badgeColor: '#991B1B',
    badgeBorder: '#FCA5A5',
    icon: 'tabler-trash',
  },
};

const rows = [
  { key: 'A', label: 'Clase A (80% Ventas)', desc: 'Alto impacto financiero', headerClass: 'header-row-a' },
  { key: 'B', label: 'Clase B (15% Ventas)', desc: 'Impacto intermedio', headerClass: 'header-row-b' },
  { key: 'C', label: 'Clase C (5% Ventas)', desc: 'Bajo impacto / Cola larga', headerClass: 'header-row-c' },
];

const cols = [
  { key: 'X', label: 'X (Estable)', desc: 'CV < 0.5 · Predecible', headerClass: 'header-col-x' },
  { key: 'Y', label: 'Y (Fluctuante)', desc: '0.5 ≤ CV ≤ 1.0 · Variable', headerClass: 'header-col-y' },
  { key: 'Z', label: 'Z (Errática)', desc: 'CV > 1.0 · Riesgo', headerClass: 'header-col-z' },
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
          <VAvatar color="primary" variant="tonal" size="32" rounded="lg">
            <VIcon icon="tabler-grid-dots" size="18" />
          </VAvatar>
          <div>
            <h3 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-0 d-flex align-center gap-1.5">
              Matriz de Decisión Estratégica 3×3 (ABC Ventas vs. XYZ Predictibilidad)
            </h3>
            <span class="text-caption text-medium-emphasis">
              Mapa de calor interactivo: haz clic en cualquier cuadrante para filtrar al instante los productos y aplicar su política.
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
              <th class="matrix-corner-th pa-2 text-center">
                <div class="text-caption font-weight-black text-primary text-uppercase letter-spacing-1">
                  Ventas \ Demanda
                </div>
              </th>
              <th
                v-for="col in cols"
                :key="col.key"
                class="matrix-header-th pa-2 text-center"
                :class="col.headerClass"
              >
                <div class="text-subtitle-2 font-weight-black text-high-emphasis">{{ col.label }}</div>
                <div class="text-caption text-medium-emphasis font-weight-medium" style="font-size: 0.68rem;">{{ col.desc }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.key">
              <td
                class="matrix-row-header-td pa-2 text-center"
                :class="row.headerClass"
                style="width: 140px;"
              >
                <div class="text-subtitle-2 font-weight-black text-high-emphasis">{{ row.label }}</div>
                <div class="text-caption text-medium-emphasis font-weight-medium" style="font-size: 0.68rem;">{{ row.desc }}</div>
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
                    backgroundColor: quadrantConfigs[row.key + col.key]?.cardBg,
                    borderColor: selectedQuadrant === (row.key + col.key)
                      ? (quadrantConfigs[row.key + col.key]?.hoverBorder || 'rgb(var(--v-theme-primary))')
                      : (quadrantConfigs[row.key + col.key]?.cardBorder || 'rgba(var(--v-border-color), 0.15)'),
                    '--hover-border-color': quadrantConfigs[row.key + col.key]?.hoverBorder,
                    '--active-glow-color': quadrantConfigs[row.key + col.key]?.activeGlow,
                  }"
                  @click="handleQuadrantClick(row.key + col.key)"
                >
                  <!-- Cabecera del Cuadrante -->
                  <div class="d-flex align-center justify-space-between mb-1.5">
                    <span
                      class="px-2 py-0.5 rounded-pill text-caption font-weight-black border"
                      :style="{
                        background: quadrantConfigs[row.key + col.key]?.badgeBg || '#F1F3F4',
                        color: quadrantConfigs[row.key + col.key]?.badgeColor || '#3C4043',
                        borderColor: quadrantConfigs[row.key + col.key]?.badgeBorder || 'transparent',
                        fontSize: '0.74rem',
                        letterSpacing: '0.5px',
                      }"
                    >
                      {{ row.key + col.key }}
                    </span>
                    <span class="text-caption font-weight-black text-high-emphasis">
                      {{ matrixData[row.key + col.key].count }} SKUs
                      <span class="text-disabled font-weight-medium text-super-xs">
                        ({{ totalProducts > 0 ? Math.round((matrixData[row.key + col.key].count / totalProducts) * 100) : 0 }}%)
                      </span>
                    </span>
                  </div>

                  <!-- Título y Estrategia -->
                  <div class="mb-2">
                    <div class="text-caption font-weight-black text-high-emphasis leading-tight d-flex align-center gap-1">
                      <VIcon
                        v-if="quadrantConfigs[row.key + col.key]?.icon"
                        :icon="quadrantConfigs[row.key + col.key].icon"
                        size="13"
                        :color="quadrantConfigs[row.key + col.key].color"
                      />
                      {{ quadrantConfigs[row.key + col.key]?.title }}
                    </div>
                    <div class="text-super-xs text-medium-emphasis mt-0.5 font-weight-medium">
                      {{ quadrantConfigs[row.key + col.key]?.strategy }}
                    </div>
                  </div>

                  <!-- Métricas de Ventas y Capital Inmovilizado -->
                  <div class="d-flex align-center justify-space-between pt-1.5 border-t border-opacity-15 mt-auto text-super-xs">
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
  border-spacing: 7px;
}

/* Ejes y Cabeceras Modernas con Jerarquía */
.matrix-corner-th {
  background: rgba(var(--v-theme-primary), 0.06);
  border: 1px solid rgba(var(--v-theme-primary), 0.15);
  border-radius: 8px;
}

.matrix-header-th,
.matrix-row-header-td {
  border-radius: 8px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
}

.header-col-x {
  background: rgba(16, 185, 129, 0.05);
  border-color: rgba(16, 185, 129, 0.2) !important;
}

.header-col-y {
  background: rgba(59, 130, 246, 0.05);
  border-color: rgba(59, 130, 246, 0.2) !important;
}

.header-col-z {
  background: rgba(245, 158, 11, 0.05);
  border-color: rgba(245, 158, 11, 0.2) !important;
}

.header-row-a {
  background: rgba(59, 130, 246, 0.05);
  border-color: rgba(59, 130, 246, 0.2) !important;
}

.header-row-b {
  background: rgba(139, 92, 246, 0.05);
  border-color: rgba(139, 92, 246, 0.2) !important;
}

.header-row-c {
  background: rgba(239, 68, 68, 0.04);
  border-color: rgba(239, 68, 68, 0.18) !important;
}

/* Tarjetas Cuadrante con Heatmap */
.quadrant-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.quadrant-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  border-color: var(--hover-border-color) !important;
}

.active-quadrant {
  box-shadow: 0 0 0 2.5px var(--hover-border-color), 0 8px 20px var(--active-glow-color) !important;
  transform: scale(1.015);
}

.inactive-quadrant {
  opacity: 0.45;
  filter: grayscale(20%);
}

.letter-spacing-1 {
  letter-spacing: 0.5px;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.transition-all {
  transition: all 0.2s ease;
}
</style>
