<template>
  <VDialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="860">
    <VCard class="pa-0 rounded-xl overflow-hidden elevation-10 border">
      <!-- Encabezado Estilizado Vuexy -->
      <VCardTitle class="pa-0">
        <div class="header-gradient px-5 py-3 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-2">
            <VIcon icon="tabler-calculator" color="primary" size="22" />
          </VAvatar>
          <div class="flex-grow-1 overflow-hidden">
            <h2 class="text-subtitle-1 font-weight-bold text-white leading-tight mb-0 text-uppercase text-truncate" style="max-inline-size: 650px;">
              Auditoría: {{ item?.name || item?.producto || 'Producto' }}
            </h2>
            <div class="d-flex align-center gap-2 mt-0.5">
              <span class="text-caption text-white opacity-90 text-uppercase font-weight-medium text-truncate">
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

      <!-- Cuerpo del Modal: Estructura en 2 Columnas Despejada -->
      <VCardText class="pa-4 bg-grey-lighten-5">
        <VRow dense>
          
          <!-- COLUMNA IZQUIERDA: Demanda, Historial y Posición Comercial -->
          <VCol cols="12" md="6" class="d-flex flex-column ga-3">
            
            <!-- FASE 1: CLUSTERIZACIÓN Y LIGA -->
            <VCard variant="flat" class="bg-white rounded-lg pa-3 border-s-lg border-primary border shadow-xs">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-bold text-primary text-uppercase d-flex align-center gap-1">
                  <VIcon icon="tabler-tag" size="16" /> FASE 1: Clusterización y Liga
                </span>
                <VChip
                  :color="String(item?.tier_name || item?.liga_nombre || '').toLowerCase().includes('econ') ? 'pink' : (item?.liga_color || (String(item?.tier_name || item?.liga_nombre || '').toLowerCase().includes('prem') ? 'success' : 'info'))"
                  size="small"
                  variant="tonal"
                  class="font-weight-bold text-uppercase px-2"
                >
                  LIGA {{ item?.tier_name || item?.liga_nombre || 'N/A' }}
                </VChip>
              </div>
              <div class="d-flex justify-space-between text-caption pt-1 border-t">
                <div>
                  <span class="text-medium-emphasis">Costo Unit.:</span>
                  <strong class="ms-1 text-success font-mono">${{ Number(item?.costo ?? item?.unit_cost ?? 0).toFixed(2) }}</strong>
                </div>
                <div>
                  <span class="text-medium-emphasis">Cobertura:</span>
                  <strong class="ms-1 font-mono">{{ totalCoverageDays }}d ({{ leadTimeDays }}d + {{ bufferDays }}d)</strong>
                </div>
              </div>
            </VCard>

            <!-- FASE 2: HISTORIAL Y SANACIÓN DE DEMANDA -->
            <VCard 
              variant="flat" 
              class="bg-white rounded-lg pa-3 border-s-lg border shadow-xs"
              :class="isQuiebreExtremo ? 'border-warning' : (hasQuiebreAlert ? 'border-warning' : 'border-info')"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-bold text-slate-800 text-uppercase d-flex align-center gap-1" style="color: #0f172a;">
                  <VIcon icon="tabler-chart-bar" size="16" /> FASE 2: Historial y Sanación
                </span>
                <VChip v-if="isQuiebreExtremo" color="warning" size="small" variant="flat" class="font-weight-bold text-white px-2">
                  QUIEBRE (>90D)
                </VChip>
                <VChip v-else-if="item?.is_lote_prueba" color="info" size="small" variant="flat" class="font-weight-bold text-white px-2">
                  LOTE DE PRUEBA
                </VChip>
                <VChip v-else-if="hasQuiebreAlert" color="warning" size="small" variant="tonal" class="font-weight-bold">
                  SANACIÓN APLICADA
                </VChip>
              </div>

              <!-- Cuadrícula / Tabla de Métricas M1, M2, M3 -->
              <div class="border rounded-lg bg-grey-lighten-5 pa-2 mb-3">
                <VRow dense class="text-center font-mono text-caption pb-1">
                  <VCol cols="3" class="text-start font-weight-bold text-medium-emphasis">Métrica</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M1 (0-30d)</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M2 (31-60d)</VCol>
                  <VCol cols="3" class="font-weight-bold text-medium-emphasis">M3 (61-90d)</VCol>
                </VRow>
                <VDivider class="my-1" />
                <VRow dense class="text-center font-mono text-caption py-0.5">
                  <VCol cols="3" class="text-start text-disabled">Ventas</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_30d ?? 0) }} un</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m2 ?? 0) }} un</VCol>
                  <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m3 ?? 0) }} un</VCol>
                </VRow>
                <VRow dense class="text-center font-mono text-caption py-0.5">
                  <VCol cols="3" class="text-start text-disabled">Días Stock</VCol>
                  <VCol cols="3" :class="Number(item?.dias_con_stock_m1 ?? 30) < 5 ? 'text-error font-weight-bold' : ''">
                    {{ Number(item?.dias_con_stock_m1 ?? 30) }}d
                  </VCol>
                  <VCol cols="3">{{ Number(item?.dias_con_stock_m2 ?? 30) }}d</VCol>
                  <VCol cols="3">{{ Number(item?.dias_con_stock_m3 ?? 30) }}d</VCol>
                </VRow>
                <VRow dense class="text-center font-mono text-caption py-0.5 text-medium-emphasis">
                  <VCol cols="3" class="text-start font-weight-medium">Pond.</VCol>
                  <VCol cols="3" class="font-weight-bold text-primary">{{ computedWeights.m1 }}%</VCol>
                  <VCol cols="3" class="font-weight-bold text-primary">{{ computedWeights.m2 }}%</VCol>
                  <VCol cols="3" class="font-weight-bold text-primary">{{ computedWeights.m3 }}%</VCol>
                </VRow>
              </div>

              <!-- Banner de Alerta de Quiebre -->
              <div v-if="isQuiebreExtremo" class="pa-2 bg-amber-lighten-5 border border-amber rounded-lg mb-3 d-flex align-center ga-2 text-caption text-amber-darken-4 font-weight-medium">
                <VIcon icon="tabler-alert-triangle" size="16" color="warning" />
                <span>Sin historial en 90 días (Demanda: 0.0 un/mes). Activado Protocolo de Rescate / Lote de Exposición.</span>
              </div>
              <div v-else-if="item?.is_lote_prueba" class="pa-2 bg-blue-lighten-5 border border-blue rounded-lg mb-3 d-flex align-center ga-2 text-caption text-blue-darken-4 font-weight-medium">
                <VIcon icon="tabler-info-circle" size="16" color="info" />
                <span>Muestra histórica mínima detectada (Días stock ≤ 3d). Modo Lote de Prueba activo (Demanda y sugerido controlados).</span>
              </div>
              <div v-else-if="hasQuiebreAlert" class="pa-2 bg-amber-lighten-5 border border-amber rounded-lg mb-3 d-flex align-center ga-2 text-caption text-amber-darken-4 font-weight-medium">
                <VIcon icon="tabler-alert-triangle" size="16" color="warning" />
                <span>Quiebre detectado ({{ Number(item?.dias_quiebre || 0) }}d). Se recalculó la velocidad diaria por días con stock real.</span>
              </div>

              <!-- Variables de Cálculo -->
              <div class="d-flex flex-column ga-1.5 text-caption text-high-emphasis pt-1 border-t">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Venta Diaria Sanada (VPD):</strong></span>
                  <span class="font-mono font-weight-bold" :class="vpdCalculated > 0 ? 'text-primary' : 'text-disabled'">
                    {{ vpdCalculated.toFixed(3) }} un/día ({{ Number(item?.demanda_sanada_individual ?? item?.promedio_calculado ?? 0).toFixed(1) }} un/mes)
                  </span>
                </div>
                <div class="d-flex justify-space-between">
                  <span>• <strong>ROP Individual (14d):</strong></span>
                  <span class="font-mono font-weight-bold text-info">{{ Number(item?.rop_calculado_individual ?? item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }} un</span>
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
              variant="flat" 
              class="bg-white rounded-lg pa-3 border-s-lg border shadow-xs"
              :class="isLeader ? 'border-success' : 'border-info'"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-bold text-slate-800 text-uppercase d-flex align-center gap-1" style="color: #0f172a;">
                  <VIcon icon="tabler-crown" size="16" /> FASE 3: Participación Comercial (IPO)
                </span>
                <VChip
                  v-if="isLeader"
                  color="success"
                  size="small"
                  variant="flat"
                  class="font-weight-bold"
                >
                  ⭐ BEST SELLER
                </VChip>
              </div>
              <div class="d-flex flex-column ga-1.5 text-caption text-high-emphasis pt-1 border-t">
                <div class="d-flex justify-space-between">
                  <span>• <strong>Demanda Proyectada Liga:</strong></span>
                  <span class="font-mono font-weight-bold">{{ Number(item?.ventas_totales_liga ?? 0).toFixed(1) }} un</span>
                </div>
                <div class="d-flex justify-space-between align-center">
                  <span>• <strong>Índice de Posición (IPO):</strong></span>
                  <span class="font-mono font-weight-bold text-subtitle-2 text-primary">{{ Number(item?.ipo || 0).toFixed(1) }}%</span>
                </div>
                <div v-if="Number(item?.promedio_calculado ?? 0) > 0" class="d-flex justify-space-between align-center text-caption text-medium-emphasis">
                  <span>• <strong>Demanda Cuota Liga:</strong></span>
                  <span class="font-mono font-weight-bold text-slate-700">{{ Number(item?.promedio_calculado ?? 0).toFixed(1) }} un/mes (ROP Liga: {{ Number(item?.rop ?? 0).toFixed(2) }} un)</span>
                </div>
              </div>
            </VCard>

          </VCol>

          <!-- COLUMNA DERECHA: Cascada, Eficiencia Financiera y Resultado -->
          <VCol cols="12" md="6" class="d-flex flex-column ga-3">
            
            <!-- FASE 4: CASCADA Y PRE-ASIGNACIÓN PRIORITARIA -->
            <VCard variant="flat" class="bg-white rounded-lg pa-3 border-s-lg border shadow-xs" :class="faltanteDirecto > 0 || isQuiebreExtremo || Number(item?.presupuesto_disponible_liga || 0) > 0 ? 'border-indigo' : 'border-success'">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-bold text-slate-800 text-uppercase d-flex align-center gap-1" style="color: #0f172a;">
                  <VIcon icon="tabler-bolt" size="16" /> FASE 4: Cascada de Reposición
                </span>
                <VChip v-if="!isQuiebreExtremo && faltanteDirecto <= 0 && Number(item?.presupuesto_disponible_liga || 0) <= 0" color="success" size="small" variant="tonal" class="font-weight-bold">
                  LIGA CUBIERTA
                </VChip>
              </div>

              <!-- Caso: Liga / Producto Cubierto -->
              <div v-if="!isQuiebreExtremo && faltanteDirecto <= 0 && Number(item?.presupuesto_disponible_liga || 0) <= 0" class="d-flex flex-column ga-1 text-caption">
                <div class="pa-2 bg-success-lighten-5 rounded-lg border border-success text-success-darken-3 font-weight-medium">
                  ✓ El stock actual (<strong>{{ Number(item?.stock_efectivo || 0).toFixed(1) }} un</strong>) cubre el ROP (<strong>{{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }} un</strong>). Sin déficit en la Liga.
                </div>
              </div>

              <!-- Caso: Hay Faltante o Quiebre Extremo -->
              <div v-else class="d-flex flex-column ga-1.5 text-caption text-high-emphasis pt-1 border-t">
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
                <div v-if="isQuiebreExtremo">
                  <div v-if="Number(item?.solicitar || 0) > 0" class="mt-2 pa-2 bg-amber-lighten-5 rounded-lg border border-warning text-warning-darken-4 font-weight-bold text-caption">
                    ⚡ Protocolo de Rescate: +1 un asignada como Lote de Exposición (Único SKU o Liga desabastecida).
                  </div>
                  <div v-else class="mt-2 pa-2 bg-info-lighten-5 rounded-lg border border-info text-info-darken-3 font-weight-bold text-caption">
                    ✓ Rescate de Catálogo Omitido: La Liga ya tiene presencia y cobertura en anaquel con producto líder/sustituto.
                  </div>
                </div>
                <div v-else-if="Number(item?.pre_asignado_bs || 0) > 0" class="mt-2 pa-2 bg-success-lighten-5 rounded-lg border border-success text-success-darken-3 font-weight-bold text-caption">
                  ✓ Pre-asignación Bloqueada: +{{ Number(item?.pre_asignado_bs || 0).toFixed(1) }} un reservada por Best Seller/Quiebre.
                </div>
              </div>
            </VCard>

            <!-- FASE 4.4: EFICIENCIA FINANCIERA (si aplica) -->
            <VCard 
              v-if="Number(item?.ajuste_financiero || 0) < 0 || Number(item?.rescate_best_seller || 0) > 0"
              variant="flat" 
              class="bg-white rounded-lg pa-3 border-s-lg border shadow-xs"
              :class="Number(item?.ajuste_financiero || 0) < 0 ? 'border-error' : 'border-success'"
            >
              <div class="text-caption font-weight-bold text-slate-800 text-uppercase mb-1" style="color: #0f172a;">
                📌 FASE 4.4: Eficiencia Financiera
              </div>
              <div class="text-caption text-high-emphasis pt-1 border-t">
                <template v-if="Number(item?.ajuste_financiero || 0) < 0">
                  <div class="text-error font-weight-bold">• Penalización de Rentabilidad:</div>
                  <div class="text-medium-emphasis">
                    Producto lento (ROP &lt; 1.0 e IPO &lt; 20%). Se liberaron {{ Math.abs(item.ajuste_financiero) }} un para el líder de la liga.
                  </div>
                </template>
                <template v-if="Number(item?.rescate_best_seller || 0) > 0">
                  <div class="text-success font-weight-bold">• Bonificación de Líder:</div>
                  <div class="text-medium-emphasis">
                    Por ser SKU líder, recibe +{{ item.rescate_best_seller }} un recuperadas de productos de baja rotación.
                  </div>
                </template>
              </div>
            </VCard>

            <!-- FASE 5: RESULTADO FINAL -->
            <VCard 
              variant="flat" 
              class="bg-white rounded-lg pa-3 border-s-lg border shadow-xs flex-grow-1 d-flex flex-column justify-space-between"
              :class="Number(item?.solicitar || 0) > 0 ? 'border-success' : (Number(item?.solicitar || 0) < 0 ? 'border-warning' : 'border-info')"
            >
              <div>
                <div class="text-caption font-weight-bold text-slate-800 text-uppercase mb-2 d-flex align-center gap-1" style="color: #0f172a;">
                  <VIcon icon="tabler-package" size="16" /> FASE 5: Sugerido Final y Resultado
                </div>
                
                <div class="text-caption text-high-emphasis mb-3 pt-1 border-t">
                  <div class="d-flex justify-space-between mb-1">
                    <span>• <strong>Balance de Inventario:</strong></span>
                    <span class="font-mono font-weight-bold" :class="Number(item?.solicitar || 0) > 0 ? 'text-success' : (Number(item?.solicitar || 0) < 0 ? 'text-warning-darken-3' : 'text-info')">
                      {{ Number(item?.solicitar || 0) > 0 ? '+' + Number(item?.solicitar || 0) + ' un (Déficit)' : (Number(item?.solicitar || 0) < 0 ? Number(item?.solicitar || 0) + ' un (Sobrestock)' : '0 un (Equilibrado)') }}
                    </span>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span>• <strong>Regla de Lote Mínimo:</strong></span>
                    <span class="font-weight-bold" :class="isQuiebreExtremo ? (Number(item?.solicitar || 0) > 0 ? 'text-warning' : 'text-info') : 'text-disabled'">
                      {{ isQuiebreExtremo ? (Number(item?.solicitar || 0) > 0 ? 'Lote de Exposición (+1 un)' : 'Omitido (Liga Cubierta)') : 'Aplicada' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Banner de Sugerido Final -->
              <div 
                class="pa-3 rounded-lg text-center border"
                :class="Number(item?.solicitar || 0) > 0 ? 'bg-success-lighten-5 border-success text-success' : (Number(item?.solicitar || 0) < 0 ? 'bg-amber-lighten-5 border-warning text-warning-darken-4' : 'bg-blue-lighten-5 border-info text-info-darken-3')"
              >
                <span class="text-caption font-weight-bold text-uppercase d-block mb-1">
                  {{ Number(item?.solicitar || 0) > 0 ? 'Resultado Final Sugerido' : (Number(item?.solicitar || 0) < 0 ? 'Sobrestock Detectado' : 'Estado del Inventario') }}
                </span>
                <span class="text-h5 font-weight-black font-mono">
                  {{ Number(item?.solicitar || 0) > 0 ? '+' + Number(item?.solicitar || 0) + ' UNIDADES' : (Number(item?.solicitar || 0) < 0 ? Number(item?.solicitar || 0) + ' UNIDADES' : 'STOCK ÓPTIMO (0 UN)') }}
                </span>
              </div>
            </VCard>

          </VCol>
        </VRow>
      </VCardText>
      
      <!-- Acciones Inferiores -->
      <VCardActions class="pa-3 bg-surface border-t d-flex justify-end">
        <VBtn variant="flat" color="primary" class="font-weight-bold px-6" @click="$emit('update:modelValue', false)">
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

// Ponderaciones dinámicas M1, M2, M3 con fallback bayesiano (50% / 30% / 20%)
const computedWeights = computed(() => {
  const p1 = Number(props.item?.peso_m1);
  const p2 = Number(props.item?.peso_m2);
  const p3 = Number(props.item?.peso_m3);

  // Si ya vienen calculadas y no son todas 0:
  if (!isNaN(p1) && !isNaN(p2) && !isNaN(p3) && (p1 + p2 + p3 > 0)) {
    return {
      m1: Math.round(p1),
      m2: Math.round(p2),
      m3: Math.round(p3),
    };
  }

  // Si vienen en 0 o no definidas, calcular dinámicamente con los días con stock
  const d1 = Number(props.item?.dias_con_stock_m1 ?? 30);
  const d2 = Number(props.item?.dias_con_stock_m2 ?? 30);
  const d3 = Number(props.item?.dias_con_stock_m3 ?? 30);

  const rawW1 = 0.50 * (Math.max(0, d1) / 30);
  const rawW2 = 0.30 * (Math.max(0, d2) / 30);
  const rawW3 = 0.20 * (Math.max(0, d3) / 30);
  const total = rawW1 + rawW2 + rawW3;

  if (total > 0) {
    return {
      m1: Math.round((rawW1 / total) * 100),
      m2: Math.round((rawW2 / total) * 100),
      m3: Math.round((rawW3 / total) * 100),
    };
  }

  // Fallback canónico si no hay días con stock
  return { m1: 50, m2: 30, m3: 20 };
});

// Quiebre Extremo sin Historial
const isQuiebreExtremo = computed(() => {
  if (props.item?.is_quiebre_extremo_sin_historial) return true;
  const v1 = Number(props.item?.ventas_30d ?? 0);
  const v2 = Number(props.item?.ventas_m2 ?? 0);
  const v3 = Number(props.item?.ventas_m3 ?? 0);
  const d1 = Number(props.item?.dias_con_stock_m1 ?? 0);
  const d2 = Number(props.item?.dias_con_stock_m2 ?? 0);
  const d3 = Number(props.item?.dias_con_stock_m3 ?? 0);
  const q90 = Number(props.item?.dias_quiebre ?? 0);
  return (v1 + v2 + v3 === 0) && (d1 + d2 + d3 === 0 || q90 >= 60);
});

// VPD Calculada
const vpdCalculated = computed(() => {
  if (isQuiebreExtremo.value) return 0;
  if (props.item?.vdr_sanada_individual !== undefined && Number(props.item.vdr_sanada_individual) >= 0) {
    return Number(props.item.vdr_sanada_individual);
  }
  if (props.item?.vdr_sanada !== undefined && Number(props.item.vdr_sanada) >= 0) {
    return Number(props.item.vdr_sanada);
  }
  const prom = Number(props.item?.demanda_sanada_individual ?? props.item?.promedio_calculado ?? 0);
  return prom > 0 ? (prom / 30) : 0;
});

// Quiebre y Estado
const hasQuiebreAlert = computed(() => {
  if (isQuiebreExtremo.value) return false;
  return Number(props.item?.dias_quiebre ?? 0) > 0 || 
         Number(props.item?.dias_con_stock_m1 ?? 30) < 5 ||
         Boolean(props.item?.is_quiebre_cronico_sanado);
});

// Best Seller
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
</script>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}
</style>
