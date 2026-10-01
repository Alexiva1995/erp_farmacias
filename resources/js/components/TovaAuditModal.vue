<template>
  <VDialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="920">
    <VCard class="pa-0 rounded-lg overflow-hidden elevation-8 d-flex flex-column" style="max-height: 90vh;">
      <!-- Encabezado Principal Fijo -->
      <VCardItem class="bg-grey-darken-4 text-white px-5 py-3 d-flex justify-space-between align-center border-b flex-grow-0">
        <div class="d-flex align-center ga-3">
          <VAvatar color="primary" variant="tonal" size="34" rounded>
            <VIcon icon="tabler-calculator" size="20" color="primary" />
          </VAvatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold text-uppercase tracking-wide">
              Auditoría de Decisión: {{ item?.name || item?.producto || 'Producto' }}
            </div>
            <div class="text-caption text-grey-lighten-1">
              ID SKU: {{ item?.product_id ?? item?.id }} | Grupo: {{ item?.group_name || 'Sin Grupo' }} (#{{ item?.group_id || 'N/A' }})
            </div>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="grey-lighten-1" @click="$emit('update:modelValue', false)" />
      </VCardItem>

      <!-- Contenido Principal con Scroll Interno y Layout de 2 Columnas -->
      <VCardText class="pa-4 bg-grey-lighten-5 overflow-y-auto flex-grow-1" style="max-height: calc(90vh - 130px);">
        <VRow dense>
          
          <!-- COLUMNA IZQUIERDA: Demanda, Historial y Posición Comercial -->
          <VCol cols="12" md="6" class="d-flex flex-column ga-3">
            
            <!-- FASE 1: CLUSTERIZACIÓN Y LIGA -->
            <VCard variant="outlined" class="bg-white rounded-lg pa-3 border-s-lg border-primary">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-xs font-weight-black text-primary text-uppercase">
                  🏷️ FASE 1: Clusterización y Liga
                </span>
                <VChip
                  :color="item?.liga_color || (item?.liga_nombre === 'Premium' ? 'success' : (item?.liga_nombre === 'Promedio' ? 'info' : 'pink'))"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black text-uppercase"
                >
                  LIGA {{ item?.tier_name || item?.liga_nombre || 'N/A' }}
                </VChip>
              </div>
              <div class="d-flex justify-space-between text-xs">
                <div>
                  <span class="text-medium-emphasis">Costo Unitario:</span>
                  <strong class="ms-1 text-success">${{ Number(item?.costo ?? item?.unit_cost ?? 0).toFixed(2) }}</strong>
                </div>
                <div>
                  <span class="text-medium-emphasis">Cobertura:</span>
                  <strong class="ms-1">{{ totalCoverageDays }}d ({{ leadTimeDays }}d + {{ bufferDays }}d)</strong>
                </div>
              </div>
            </VCard>

            <!-- FASE 2: HISTORIAL Y SANACIÓN DE DEMANDA -->
            <VCard 
              variant="outlined" 
              class="bg-white rounded-lg pa-3 border-s-lg"
              :class="hasQuiebreAlert ? 'border-warning' : 'border-info'"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-xs font-weight-black text-slate-800 text-uppercase" style="color: #0f172a;">
                  📊 FASE 2: Historial y Sanación
                </span>
                <VChip v-if="hasQuiebreAlert" color="warning" size="x-small" variant="tonal" class="font-weight-bold">
                  SANACIÓN APLICADA
                </VChip>
              </div>

              <!-- Cuadrícula / Tabla de Métricas M1, M2, M3 -->
              <div class="border rounded bg-grey-lighten-5 pa-2 mb-2">
                <VRow dense class="text-center font-mono text-super-xs">
                  <VCol cols="3" class="text-start font-weight-bold text-medium-emphasis">Métrica</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M1 (0-30d)</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M2 (31-60d)</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M3 (61-90d)</VCol>
                </VRow>
                <VDivider class="my-1" />
                <VRow dense class="text-center font-mono text-xs">
                  <VCol cols="3" class="text-start text-medium-emphasis">Ventas</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_30d ?? 0) }} un</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m2 ?? 0) }} un</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m3 ?? 0) }} un</VCol>
                </VRow>
                <VRow dense class="text-center font-mono text-xs">
                  <VCol cols="3" class="text-start text-medium-emphasis">Días Stock</VCol>
                  <VCol cols="3" :class="Number(item?.dias_con_stock_m1 ?? 30) < 5 ? 'text-error font-weight-bold' : ''">
                    {{ Number(item?.dias_con_stock_m1 ?? 30) }}d
                  </VCol>
                  <VCol cols="3">{{ Number(item?.dias_con_stock_m2 ?? 30) }}d</VCol>
                  <VCol cols="3">{{ Number(item?.dias_con_stock_m3 ?? 30) }}d</VCol>
                </VRow>
                <VRow dense class="text-center font-mono text-super-xs text-medium-emphasis">
                  <VCol cols="3" class="text-start">Ponderación</VCol>
                  <VCol cols="3">{{ item?.peso_m1 !== undefined ? item?.peso_m1 + '%' : (Number(item?.dias_con_stock_m1 ?? 30) < 5 ? '0%' : '50%') }}</VCol>
                  <VCol cols="3">{{ item?.peso_m2 !== undefined ? item?.peso_m2 + '%' : '30%' }}</VCol>
                  <VCol cols="3">{{ item?.peso_m3 !== undefined ? item?.peso_m3 + '%' : '20%' }}</VCol>
                </VRow>
              </div>

              <!-- Banner de Alerta de Quiebre -->
              <div v-if="hasQuiebreAlert" class="pa-2 bg-amber-lighten-5 border border-amber rounded mb-2 d-flex align-center ga-1 text-super-xs text-amber-darken-4 font-weight-bold">
                <VIcon icon="tabler-alert-triangle" size="14" color="warning" />
                <span>Quiebre crónico detectado ({{ Number(item?.dias_quiebre || 0) }}d). Se recalculó la velocidad diaria ignorando días en 0.</span>
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
                <div v-if="stockPasivo > 0" class="text-medium-emphasis text-super-xs">
                  • <em>Stock Sobrante/Pasivo:</em> {{ stockPasivo.toFixed(1) }} un por encima de la demanda proyectada.
                </div>
              </div>
            </VCard>

            <!-- FASE 3: PARTICIPACIÓN COMERCIAL (IPO) -->
            <VCard 
              variant="outlined" 
              class="bg-white rounded-lg pa-3 border-s-lg"
              :class="isLeader ? 'border-success' : 'border-info'"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-xs font-weight-black text-slate-800 text-uppercase" style="color: #0f172a;">
                  👑 FASE 3: Participación Comercial (IPO)
                </span>
                <VChip
                  v-if="isLeader"
                  color="success"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black"
                >
                  ⭐ BEST SELLER DE LIGA
                </VChip>
              </div>
              <div class="d-flex flex-column ga-1 text-xs text-high-emphasis">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Ventas Totales Liga ({{ item?.tier_name || item?.liga_nombre || 'N/A' }}):</strong></span>
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
          <VCol cols="12" md="6" class="d-flex flex-column ga-3">
            
            <!-- FASE 4: CASCADA Y PRE-ASIGNACIÓN PRIORITARIA -->
            <VCard variant="outlined" class="bg-white rounded-lg pa-3 border-s-lg border-indigo">
              <div class="text-xs font-weight-black text-slate-800 text-uppercase mb-2" style="color: #0f172a;">
                ⚡ FASE 4: Cascada y Pre-Asignación
              </div>
              <div class="d-flex flex-column ga-1 text-xs text-high-emphasis">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Faltante Individual (ROP - Stock):</strong></span>
                  <strong :class="faltanteDirecto > 0 ? 'text-error' : 'text-success'" class="font-mono">{{ faltanteDirecto.toFixed(2) }} un</strong>
                </div>
                <div v-if="item?.presupuesto_disponible_liga !== undefined" class="d-flex justify-space-between">
                  <span>• <strong>Presupuesto Faltante Liga:</strong></span>
                  <span class="font-mono font-weight-bold">{{ Number(item?.presupuesto_disponible_liga || 0).toFixed(2) }} un</span>
                </div>
                <div v-if="item?.cuota_participacion_ipo !== undefined && Number(item?.cuota_participacion_ipo) > 0" class="d-flex justify-space-between">
                  <span>• <strong>Cuota IPO ({{ item?.cuota_participacion_ipo }}%):</strong></span>
                  <span class="font-mono font-weight-bold text-info">+{{ Number(item?.asignacion_cascada || 0).toFixed(2) }} un</span>
                </div>
                <div v-if="Number(item?.pre_asignado_bs || 0) > 0" class="mt-1">
                  <VChip color="success" size="x-small" variant="tonal" class="font-weight-bold text-wrap text-start">
                    ✓ Pre-asignación Bloqueada: +{{ item?.pre_asignado_bs }} un reservada por Best Seller/Quiebre.
                  </VChip>
                </div>
                <div v-else-if="faltanteDirecto <= 0" class="text-caption text-medium-emphasis mt-1">
                  • El stock actual cubre el punto de reorden individual.
                </div>
              </div>
            </VCard>

            <!-- FASE 4.4: EFICIENCIA FINANCIERA (si aplica) -->
            <VCard 
              v-if="Number(item?.ajuste_financiero || 0) < 0 || Number(item?.rescate_best_seller || 0) > 0"
              variant="outlined" 
              class="bg-white rounded-lg pa-3 border-s-lg"
              :class="Number(item?.ajuste_financiero || 0) < 0 ? 'border-error' : 'border-success'"
            >
              <div class="text-xs font-weight-black text-slate-800 text-uppercase mb-2" style="color: #0f172a;">
                📌 FASE 4.4: Eficiencia Financiera
              </div>
              <div class="text-xs text-high-emphasis">
                <template v-if="Number(item?.ajuste_financiero || 0) < 0">
                  <div class="text-error font-weight-bold">• Penalización de Rentabilidad:</div>
                  <div class="text-caption text-medium-emphasis">
                    Producto lento (ROP &lt; 1.0 e IPO &lt; 20%). Se liberaron {{ Math.abs(item.ajuste_financiero) }} un para el líder de la liga.
                  </div>
                </template>
                <template v-if="Number(item?.rescate_best_seller || 0) > 0">
                  <div class="text-success font-weight-bold">• Bonificación de Líder:</div>
                  <div class="text-caption text-medium-emphasis">
                    Por ser SKU líder, recibe +{{ item.rescate_best_seller }} un recuperadas de productos de baja rotación.
                  </div>
                </template>
              </div>
            </VCard>

            <!-- FASE 5: RESULTADO FINAL -->
            <VCard variant="outlined" class="bg-white rounded-lg pa-3 border-s-lg border-success flex-grow-1 d-flex flex-column justify-space-between">
              <div class="text-xs font-weight-black text-slate-800 text-uppercase mb-2" style="color: #0f172a;">
                📦 FASE 5: Sugerido Final y Resultado
              </div>
              
              <div class="text-xs text-high-emphasis mb-2">
                <div class="d-flex justify-space-between mb-1">
                  <span>• <strong>Reparto de Liga:</strong></span>
                  <span class="font-mono font-weight-bold">{{ Number(item?.solicitar || 0) }} un</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span>• <strong>Regla de Lote Mínimo:</strong></span>
                  <span class="text-medium-emphasis">Aplicada</span>
                </div>
              </div>

              <div class="pa-3 rounded bg-success-lighten-5 border border-success text-center">
                <span class="text-xs font-weight-bold text-success text-uppercase d-block mb-1">Resultado Final Sugerido</span>
                <span class="text-h5 font-weight-black text-success font-mono">
                  {{ Number(item?.solicitar || 0) > 0 ? '+' : '' }}{{ Number(item?.solicitar || 0) }} UNIDADES
                </span>
              </div>
            </VCard>

          </VCol>
        </VRow>
      </VCardText>
      
      <!-- Acciones Inferiores Fijas -->
      <VCardActions class="pa-3 bg-grey-darken-4 justify-end border-t flex-grow-0">
        <VBtn variant="flat" color="primary" class="font-weight-bold px-5" @click="$emit('update:modelValue', false)">
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
.tracking-wide {
  letter-spacing: 0.5px;
}
.text-super-xs {
  font-size: 10px;
  line-height: 1.2;
}
</style>
