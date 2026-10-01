<template>
  <VDialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="880">
    <VCard class="pa-0 rounded-lg overflow-hidden elevation-6">
      <!-- Header Premium del Sistema -->
      <VCardTitle class="pa-0">
        <div class="header-gradient px-4 py-2 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="34" class="me-3 elevation-2">
            <VIcon icon="tabler-calculator" color="primary" size="20" />
          </VAvatar>
          <div class="flex-grow-1 overflow-hidden">
            <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0 text-uppercase text-truncate" style="max-inline-size: 680px;">
              Auditoría: {{ item?.name || item?.producto || 'Producto' }}
            </h2>
            <div class="d-flex align-center gap-2 mt-0">
              <span class="text-super-xs text-white opacity-90 text-uppercase font-weight-bold text-truncate">
                SKU: {{ item?.product_id ?? item?.id }} | Grupo: {{ item?.group_name || 'Sin Grupo' }} (#{{ item?.group_id || 'N/A' }})
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" class="rounded-lg ms-2" @click="$emit('update:modelValue', false)">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <!-- Contenido Compacto en 2 Columnas Sin Scroll Forzado -->
      <VCardText class="pa-3 bg-grey-lighten-5">
        <VRow dense>
          
          <!-- COLUMNA IZQUIERDA: Demanda, Historial y Posición Comercial -->
          <VCol cols="12" md="6" class="d-flex flex-column ga-2">
            
            <!-- FASE 1: CLUSTERIZACIÓN Y LIGA -->
            <VCard variant="outlined" class="bg-white rounded pa-2 border-s-lg border-primary">
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-xs font-weight-black text-primary text-uppercase">
                  🏷️ FASE 1: Clusterización y Liga
                </span>
                <VChip
                  :color="item?.liga_color || (item?.liga_nombre === 'Premium' ? 'success' : (item?.liga_nombre === 'Promedio' ? 'info' : 'pink'))"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black text-uppercase"
                  style="height: 18px;"
                >
                  LIGA {{ item?.tier_name || item?.liga_nombre || 'N/A' }}
                </VChip>
              </div>
              <div class="d-flex justify-space-between text-xs">
                <div>
                  <span class="text-disabled">Costo Unit.:</span>
                  <strong class="ms-1 text-success">${{ Number(item?.costo ?? item?.unit_cost ?? 0).toFixed(2) }}</strong>
                </div>
                <div>
                  <span class="text-disabled">Cobertura:</span>
                  <strong class="ms-1">{{ totalCoverageDays }}d ({{ leadTimeDays }}d + {{ bufferDays }}d)</strong>
                </div>
              </div>
            </VCard>

            <!-- FASE 2: HISTORIAL Y SANACIÓN DE DEMANDA -->
            <VCard 
              variant="outlined" 
              class="bg-white rounded pa-2 border-s-lg"
              :class="hasQuiebreAlert ? 'border-warning' : 'border-info'"
            >
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-xs font-weight-black text-slate-800 text-uppercase" style="color: #0f172a;">
                  📊 FASE 2: Historial y Sanación
                </span>
                <VChip v-if="hasQuiebreAlert" color="warning" size="x-small" variant="tonal" class="font-weight-bold" style="height: 18px;">
                  SANACIÓN APLICADA
                </VChip>
              </div>

              <!-- Cuadrícula / Tabla de Métricas M1, M2, M3 -->
              <div class="border rounded bg-grey-lighten-5 pa-1 mb-1">
                <VRow dense class="text-center font-mono text-super-xs">
                  <VCol cols="3" class="text-start font-weight-bold text-medium-emphasis">Métrica</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M1 (0-30d)</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M2 (31-60d)</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M3 (61-90d)</VCol>
                </VRow>
                <VDivider class="my-0" />
                <VRow dense class="text-center font-mono text-super-xs">
                  <VCol cols="3" class="text-start text-disabled">Ventas</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_30d ?? 0) }} un</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m2 ?? 0) }} un</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m3 ?? 0) }} un</VCol>
                </VRow>
                <VRow dense class="text-center font-mono text-super-xs">
                  <VCol cols="3" class="text-start text-disabled">Días Stock</VCol>
                  <VCol cols="3" :class="Number(item?.dias_con_stock_m1 ?? 30) < 5 ? 'text-error font-weight-bold' : ''">
                    {{ Number(item?.dias_con_stock_m1 ?? 30) }}d
                  </VCol>
                  <VCol cols="3">{{ Number(item?.dias_con_stock_m2 ?? 30) }}d</VCol>
                  <VCol cols="3">{{ Number(item?.dias_con_stock_m3 ?? 30) }}d</VCol>
                </VRow>
                <VRow dense class="text-center font-mono text-super-xs text-medium-emphasis">
                  <VCol cols="3" class="text-start">Pond.</VCol>
                  <VCol cols="3">{{ item?.peso_m1 !== undefined ? item?.peso_m1 + '%' : '50%' }}</VCol>
                  <VCol cols="3">{{ item?.peso_m2 !== undefined ? item?.peso_m2 + '%' : '30%' }}</VCol>
                  <VCol cols="3">{{ item?.peso_m3 !== undefined ? item?.peso_m3 + '%' : '20%' }}</VCol>
                </VRow>
              </div>

              <!-- Banner de Alerta de Quiebre -->
              <div v-if="hasQuiebreAlert" class="pa-1 bg-amber-lighten-5 border border-amber rounded mb-1 d-flex align-center ga-1 text-super-xs text-amber-darken-4 font-weight-bold">
                <VIcon icon="tabler-alert-triangle" size="12" color="warning" />
                <span>Quiebre detectado ({{ Number(item?.dias_quiebre || 0) }}d). Se recalculó la velocidad diaria por días con stock real.</span>
              </div>

              <!-- Variables de Cálculo -->
              <div class="d-flex flex-column ga-1 text-xs text-high-emphasis">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Venta Diaria Sanada (VPD):</strong></span>
                  <span class="font-mono font-weight-bold text-primary">{{ vpdCalculated.toFixed(3) }} un/día ({{ Number(item?.promedio_calculado ?? 0).toFixed(1) }} un/mes)</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span>• <strong>ROP Calculado:</strong></span>
                  <span class="font-mono font-weight-bold text-info">{{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }} un</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span>• <strong>Stock Físico / Tránsito:</strong></span>
                  <span class="font-mono font-weight-medium">{{ item?.stock_fisico ?? item?.lote_quantity ?? 0 }} un | {{ item?.stock_transito ?? item?.totalQuantityInAutoOrder ?? 0 }} un</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span>• <strong>Stock Efectivo Total:</strong></span>
                  <span class="font-mono font-weight-bold">{{ Number(item?.stock_efectivo || 0).toFixed(1) }} un</span>
                </div>
              </div>
            </VCard>

            <!-- FASE 3: PARTICIPACIÓN COMERCIAL (IPO) -->
            <VCard 
              variant="outlined" 
              class="bg-white rounded pa-2 border-s-lg"
              :class="isLeader ? 'border-success' : 'border-info'"
            >
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-xs font-weight-black text-slate-800 text-uppercase" style="color: #0f172a;">
                  👑 FASE 3: Participación Comercial (IPO)
                </span>
                <VChip
                  v-if="isLeader"
                  color="success"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black"
                  style="height: 18px;"
                >
                  ⭐ BEST SELLER
                </VChip>
              </div>
              <div class="d-flex flex-column ga-1 text-xs text-high-emphasis">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Demanda Proyectada Liga:</strong></span>
                  <span class="font-mono font-weight-bold">{{ Number(item?.ventas_totales_liga ?? 0).toFixed(1) }} un</span>
                </div>
                <div class="d-flex justify-space-between align-center">
                  <span>• <strong>Índice de Posición (IPO):</strong></span>
                  <span class="font-mono font-weight-bold text-subtitle-2 text-primary">{{ Number(item?.ipo || 0).toFixed(1) }}%</span>
                </div>
              </div>
            </VCard>

          </VCol>

          <!-- COLUMNA DERECHA: Cascada, Eficiencia Financiera y Resultado -->
          <VCol cols="12" md="6" class="d-flex flex-column ga-2">
            
            <!-- FASE 4: CASCADA Y PRE-ASIGNACIÓN PRIORITARIA -->
            <VCard variant="outlined" class="bg-white rounded pa-2 border-s-lg" :class="faltanteDirecto > 0 || Number(item?.presupuesto_disponible_liga || 0) > 0 ? 'border-indigo' : 'border-success'">
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-xs font-weight-black text-slate-800 text-uppercase" style="color: #0f172a;">
                  ⚡ FASE 4: Cascada de Reposición
                </span>
                <VChip v-if="faltanteDirecto <= 0 && Number(item?.presupuesto_disponible_liga || 0) <= 0" color="success" size="x-small" variant="tonal" class="font-weight-bold" style="height: 18px;">
                  LIGA CUBIERTA
                </VChip>
              </div>

              <!-- Caso: Liga / Producto Cubierto -->
              <div v-if="faltanteDirecto <= 0 && Number(item?.presupuesto_disponible_liga || 0) <= 0" class="d-flex flex-column ga-1 text-xs">
                <div class="pa-2 bg-success-lighten-5 rounded border border-success text-success-darken-3 font-weight-medium text-super-xs">
                  ✓ El stock actual (<strong>{{ Number(item?.stock_efectivo || 0).toFixed(1) }} un</strong>) cubre el ROP (<strong>{{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }} un</strong>). Sin déficit en la Liga.
                </div>
              </div>

              <!-- Caso: Hay Faltante y Corre Cascada -->
              <div v-else class="d-flex flex-column ga-1 text-xs text-high-emphasis">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Faltante Individual (ROP - Stock):</strong></span>
                  <strong class="text-error font-mono">{{ faltanteDirecto.toFixed(2) }} un</strong>
                </div>
                <div v-if="item?.presupuesto_disponible_liga !== undefined" class="d-flex justify-space-between">
                  <span>• <strong>Presupuesto Faltante Liga:</strong></span>
                  <span class="font-mono font-weight-bold">{{ Number(item?.presupuesto_disponible_liga || 0).toFixed(2) }} un</span>
                </div>
                <div v-if="Number(item?.cuota_participacion_ipo || 0) > 0" class="d-flex justify-space-between">
                  <span>• <strong>Cuota IPO ({{ item?.cuota_participacion_ipo }}%):</strong></span>
                  <span class="font-mono font-weight-bold text-info">+{{ Number(item?.asignacion_cascada || 0).toFixed(2) }} un</span>
                </div>
                <div v-if="Number(item?.pre_asignado_bs || 0) > 0" class="mt-1 pa-1-5 px-2 bg-success-lighten-5 rounded border border-success text-success-darken-3 font-weight-bold text-super-xs">
                  ✓ Pre-asignación Bloqueada: +{{ Number(item?.pre_asignado_bs || 0).toFixed(1) }} un reservada por Best Seller/Quiebre.
                </div>
              </div>
            </VCard>

            <!-- FASE 4.4: EFICIENCIA FINANCIERA (si aplica) -->
            <VCard 
              v-if="Number(item?.ajuste_financiero || 0) < 0 || Number(item?.rescate_best_seller || 0) > 0"
              variant="outlined" 
              class="bg-white rounded pa-2 border-s-lg"
              :class="Number(item?.ajuste_financiero || 0) < 0 ? 'border-error' : 'border-success'"
            >
              <div class="text-xs font-weight-black text-slate-800 text-uppercase mb-1" style="color: #0f172a;">
                📌 FASE 4.4: Eficiencia Financiera
              </div>
              <div class="text-super-xs text-high-emphasis">
                <template v-if="Number(item?.ajuste_financiero || 0) < 0">
                  <div class="text-error font-weight-bold">• Penalización de Rentabilidad:</div>
                  <div class="text-disabled">
                    Producto lento (ROP &lt; 1.0 e IPO &lt; 20%). Se liberaron {{ Math.abs(item.ajuste_financiero) }} un para el líder de la liga.
                  </div>
                </template>
                <template v-if="Number(item?.rescate_best_seller || 0) > 0">
                  <div class="text-success font-weight-bold">• Bonificación de Líder:</div>
                  <div class="text-disabled">
                    Por ser SKU líder, recibe +{{ item.rescate_best_seller }} un recuperadas de productos de baja rotación.
                  </div>
                </template>
              </div>
            </VCard>

            <!-- FASE 5: RESULTADO FINAL -->
            <VCard variant="outlined" class="bg-white rounded pa-2 border-s-lg border-success flex-grow-1 d-flex flex-column justify-space-between">
              <div class="text-xs font-weight-black text-slate-800 text-uppercase mb-1" style="color: #0f172a;">
                📦 FASE 5: Sugerido Final y Resultado
              </div>
              
              <div class="text-xs text-high-emphasis mb-1">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Reparto de Liga:</strong></span>
                  <span class="font-mono font-weight-bold">{{ Number(item?.solicitar || 0) }} un</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span>• <strong>Regla de Lote Mínimo:</strong></span>
                  <span class="text-disabled">Aplicada</span>
                </div>
              </div>

              <div class="pa-2 rounded bg-success-lighten-5 border border-success text-center">
                <span class="text-super-xs font-weight-bold text-success text-uppercase d-block">Resultado Final Sugerido</span>
                <span class="text-h6 font-weight-black text-success font-mono">
                  {{ Number(item?.solicitar || 0) > 0 ? '+' : '' }}{{ Number(item?.solicitar || 0) }} UNIDADES
                </span>
              </div>
            </VCard>

          </VCol>
        </VRow>
      </VCardText>
      
      <!-- Acciones Inferiores Fijas con Botón al 100% -->
      <VCardActions class="pa-2 bg-surface border-t">
        <VBtn block variant="flat" color="primary" class="font-weight-bold" @click="$emit('update:modelValue', false)">
          Cerrar Auditoría
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  item: {
    type: Object,
    default: () => ({}),
  },
});

defineEmits(['update:modelValue']);

// Días de reposición y parámetros
const leadTimeDays = computed(() => Number(props.item?.lead_time_days ?? 7));
const bufferDays = computed(() => Number(props.item?.buffer_days ?? 7));
const totalCoverageDays = computed(() => leadTimeDays.value + bufferDays.value);

// VPD Calculada
const vpdCalculated = computed(() => {
  if (props.item?.vdr_sanada !== undefined && Number(props.item.vdr_sanada) > 0) {
    return Number(props.item.vdr_sanada);
  }
  const prom = Number(props.item?.promedio_calculado ?? 0);
  return prom > 0 ? (prom / 30) : 0;
});

// Quiebre y Estado
const hasQuiebreAlert = computed(() => {
  return Number(props.item?.dias_quiebre ?? 0) > 0 || 
         Number(props.item?.dias_con_stock_m1 ?? 30) < 5 ||
         Boolean(props.item?.is_quiebre_cronico_sanado);
});

// Best Seller: solo si tiene ventas reales en la liga (> 0) y el IPO es >= 35% o es_best_seller_liga es true
const isLeader = computed(() => {
  const ventasLiga = Number(props.item?.ventas_totales_liga ?? 0);
  if (ventasLiga <= 0) return false;
  return Number(props.item?.ipo ?? 0) >= 35 || Boolean(props.item?.es_best_seller_liga);
});

// Faltante Directo
const faltanteDirecto = computed(() => {
  const rop = Number(props.item?.rop_calculado ?? props.item?.rop ?? 0);
  const stock = Number(props.item?.stock_efectivo ?? 0);
  return Math.max(0, rop - stock);
});

// Stock Pasivo
const stockPasivo = computed(() => {
  const stock = Number(props.item?.stock_efectivo ?? 0);
  const objetivo = Number(props.item?.demanda_ponderada ?? props.item?.promedio_calculado ?? 0);
  return Math.max(0, stock - objetivo);
});
</script>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}
.tracking-wide {
  letter-spacing: 0.5px;
}
.text-super-xs {
  font-size: 10px;
  line-height: 1.2;
}
</style>
