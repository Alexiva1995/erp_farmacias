<template>
  <VDialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="850">
    <VCard class="pa-0 rounded-lg overflow-hidden elevation-6">
      <!-- Encabezado Principal -->
      <VCardItem class="bg-grey-darken-4 text-white px-5 py-4 d-flex justify-space-between align-center border-b">
        <div class="d-flex align-center ga-3">
          <VAvatar color="primary" variant="tonal" size="36" rounded>
            <VIcon icon="tabler-calculator" size="22" color="primary" />
          </VAvatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold text-uppercase tracking-wide">
              Auditoría de Decisión: {{ item?.name || item?.producto || 'Producto' }}
            </div>
            <div class="text-caption text-grey-lighten-1">
              ID SKU: {{ item?.product_id ?? item?.id }} | Evaluación Integral de Demanda y Reposición Tova DDM
            </div>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="grey-lighten-1" @click="$emit('update:modelValue', false)" />
      </VCardItem>

      <!-- Contenido Principal con Tarjetas de Pasos (Step Cards) -->
      <VCardText class="pa-5 bg-grey-lighten-5 d-flex flex-column ga-4" style="max-height: 75vh; overflow-y: auto;">
        
        <!-- FASE 1: CLUSTERIZACIÓN Y LIGA -->
        <VCard variant="outlined" class="bg-white rounded-lg pa-4 border-s-lg border-primary">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="d-flex align-center ga-2">
              <span class="text-subtitle-2 font-weight-black text-primary text-uppercase">
                🏷️ FASE 1: Clusterización y Liga
              </span>
            </div>
            <VChip
              :color="item?.liga_color || 'primary'"
              size="small"
              variant="flat"
              class="font-weight-black text-uppercase"
            >
              LIGA {{ item?.tier_name || item?.liga_nombre || 'N/A' }}
            </VChip>
          </div>
          <VRow dense class="text-body-2 text-high-emphasis">
            <VCol cols="12" sm="6">
              <span class="text-medium-emphasis">Grupo Terapéutico:</span>
              <strong class="ms-1">{{ item?.group_name || 'Sin Grupo' }}</strong> 
              <span class="text-caption text-disabled ms-1">(ID: {{ item?.group_id || 'N/A' }})</span>
            </VCol>
            <VCol cols="12" sm="6">
              <span class="text-medium-emphasis">Costo Unitario SKU:</span>
              <strong class="ms-1 text-success">${{ Number(item?.costo ?? item?.unit_cost ?? 0).toFixed(2) }}</strong>
            </VCol>
          </VRow>
        </VCard>

        <!-- FASE 2: HISTORIAL Y SANACIÓN DE DEMANDA -->
        <VCard 
          variant="outlined" 
          class="bg-white rounded-lg pa-4 border-s-lg"
          :class="hasQuiebreAlert ? 'border-warning' : 'border-info'"
        >
          <div class="d-flex align-center justify-space-between mb-3">
            <span class="text-subtitle-2 font-weight-black text-slate-800 text-uppercase" style="color: #0f172a;">
              📊 FASE 2: Historial y Sanación de Demanda
            </span>
            <VChip v-if="hasQuiebreAlert" color="warning" size="x-small" variant="tonal" class="font-weight-bold">
              SANACIÓN APLICADA
            </VChip>
          </div>

          <!-- Cuadrícula / Tabla de Métricas M1, M2, M3 -->
          <div class="border rounded bg-grey-lighten-5 pa-2 mb-3">
            <VRow dense class="text-center font-mono text-caption">
              <VCol cols="3" class="text-start font-weight-bold text-medium-emphasis">Métrica</VCol>
              <VCol cols="3" class="font-weight-bold text-medium-emphasis">M1 (0-30d)</VCol>
              <VCol cols="3" class="font-weight-bold text-medium-emphasis">M2 (31-60d)</VCol>
              <VCol cols="3" class="font-weight-bold text-medium-emphasis">M3 (61-90d)</VCol>
            </VRow>
            <VDivider class="my-1" />
            <VRow dense class="text-center font-mono text-body-2">
              <VCol cols="3" class="text-start text-medium-emphasis">Ventas</VCol>
              <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_30d ?? 0) }} un</VCol>
              <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m2 ?? 0) }} un</VCol>
              <VCol cols="3" class="font-weight-bold">{{ Number(item?.ventas_m3 ?? 0) }} un</VCol>
            </VRow>
            <VRow dense class="text-center font-mono text-body-2">
              <VCol cols="3" class="text-start text-medium-emphasis">Días Stock</VCol>
              <VCol cols="3" :class="Number(item?.dias_con_stock_m1 ?? 30) < 5 ? 'text-error font-weight-bold' : ''">
                {{ Number(item?.dias_con_stock_m1 ?? 30) }}d
              </VCol>
              <VCol cols="3">{{ Number(item?.dias_con_stock_m2 ?? 30) }}d</VCol>
              <VCol cols="3">{{ Number(item?.dias_con_stock_m3 ?? 30) }}d</VCol>
            </VRow>
            <VRow dense class="text-center font-mono text-caption text-medium-emphasis">
              <VCol cols="3" class="text-start">Ponderación</VCol>
              <VCol cols="3">{{ item?.peso_m1 !== undefined ? item?.peso_m1 + '%' : (Number(item?.dias_con_stock_m1 ?? 30) < 5 ? '0%' : '50%') }}</VCol>
              <VCol cols="3">{{ item?.peso_m2 !== undefined ? item?.peso_m2 + '%' : '30%' }}</VCol>
              <VCol cols="3">{{ item?.peso_m3 !== undefined ? item?.peso_m3 + '%' : '20%' }}</VCol>
            </VRow>
          </div>

          <!-- Banner de Alerta de Quiebre -->
          <div v-if="hasQuiebreAlert" class="pa-2 bg-amber-lighten-5 border border-amber rounded mb-3 d-flex align-center ga-2 text-caption text-amber-darken-4 font-weight-bold">
            <VIcon icon="tabler-alert-triangle" size="18" color="warning" />
            <span>ALERTA DE QUIEBRE CRÓNICO: Registra {{ Number(item?.dias_quiebre || 0) }} días de quiebre recientes. Se reconstruyó la demanda ignorando los períodos en cero.</span>
          </div>

          <!-- Variables Matemáticas Claras -->
          <div class="d-flex flex-column ga-1 text-body-2 text-high-emphasis">
            <div>
              • <strong>Venta Diaria Sanada (VPD):</strong> 
              <span class="font-mono font-weight-bold ms-1 text-primary">{{ vpdCalculated.toFixed(3) }} un/día</span>
              <span class="text-caption text-medium-emphasis ms-1">({{ Number(item?.promedio_calculado ?? 0).toFixed(2) }} un/mes proyectadas)</span>
            </div>
            <div>
              • <strong>Días de Reposición (Lead Time + Buffer):</strong> 
              <span class="font-mono font-weight-medium ms-1">{{ totalCoverageDays }} días ({{ leadTimeDays }}d Lead Time + {{ bufferDays }}d Buffer)</span>
            </div>
            <div>
              • <strong>ROP Calculado:</strong> 
              <span class="font-mono font-weight-bold ms-1 text-info">{{ vpdCalculated.toFixed(3) }} × {{ totalCoverageDays }} = {{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }} unidades</span>
            </div>
            <div>
              • <strong>Stock Efectivo Disponible:</strong> 
              <span class="font-mono font-weight-bold ms-1">{{ Number(item?.stock_efectivo || 0).toFixed(1) }} un</span>
              <span class="text-caption text-medium-emphasis ms-1">(Físico: {{ item?.stock_fisico ?? item?.lote_quantity ?? 0 }} | Tránsito: {{ item?.stock_transito ?? item?.totalQuantityInAutoOrder ?? 0 }})</span>
            </div>
            <div>
              • <strong>Stock Útil (Capado a la Demanda Objetivo):</strong> 
              <span class="font-mono font-weight-bold ms-1 text-success">min({{ Number(item?.stock_efectivo || 0).toFixed(1) }}, {{ Number(item?.demanda_ponderada ?? item?.promedio_calculado ?? 0).toFixed(2) }}) = {{ Number(item?.stock_util || 0).toFixed(1) }} unidades</span>
            </div>
            <div v-if="stockPasivo > 0" class="text-medium-emphasis text-caption">
              • <em>Stock Sobrante / Pasivo:</em> {{ stockPasivo.toFixed(1) }} un (stock por encima del objetivo).
            </div>
          </div>
        </VCard>

        <!-- FASE 3: PARTICIPACIÓN COMERCIAL (IPO) -->
        <VCard 
          variant="outlined" 
          class="bg-white rounded-lg pa-4 border-s-lg"
          :class="isLeader ? 'border-success' : 'border-info'"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-subtitle-2 font-weight-black text-slate-800 text-uppercase" style="color: #0f172a;">
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
          <div class="d-flex flex-column ga-1 text-body-2 text-high-emphasis">
            <div>
              • <strong>Ventas Totales Liga ({{ item?.tier_name || item?.liga_nombre || 'N/A' }}):</strong> 
              <span class="font-mono font-weight-bold ms-1">{{ Number(item?.ventas_totales_liga ?? 0).toFixed(1) }} un</span>
            </div>
            <div>
              • <strong>Índice de Posición de Inventario (IPO SKU):</strong> 
              <span class="font-mono font-weight-bold text-h6 text-primary ms-1">{{ Number(item?.ipo || 0).toFixed(1) }}%</span>
              <span v-if="isLeader" class="text-caption text-success font-weight-bold ms-2">-> [LÍDER DE ALTA ROTACIÓN]</span>
            </div>
          </div>
        </VCard>

        <!-- FASE 4: CASCADA Y PRE-ASIGNACIÓN PRIORITARIA -->
        <VCard variant="outlined" class="bg-white rounded-lg pa-4 border-s-lg border-indigo">
          <div class="text-subtitle-2 font-weight-black text-slate-800 text-uppercase mb-2" style="color: #0f172a;">
            ⚡ FASE 4: Cascada y Pre-Asignación Prioritaria
          </div>
          <div class="d-flex flex-column ga-1 text-body-2 text-high-emphasis">
            <div>
              • <strong>Faltante Individual Directo:</strong> 
              <span class="font-mono ms-1">
                ROP ({{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }}) - Stock Efectivo ({{ Number(item?.stock_efectivo || 0).toFixed(1) }}) = 
                <strong :class="faltanteDirecto > 0 ? 'text-error' : 'text-success'">{{ faltanteDirecto.toFixed(2) }} un</strong>
              </span>
            </div>
            <div v-if="item?.presupuesto_disponible_liga !== undefined">
              • <strong>Presupuesto Disponible de la Liga:</strong> 
              <span class="font-mono font-weight-bold ms-1">{{ Number(item?.presupuesto_disponible_liga || 0).toFixed(2) }} unidades</span>
            </div>
            <div v-if="item?.cuota_participacion_ipo !== undefined && Number(item?.cuota_participacion_ipo) > 0">
              • <strong>Asignación por Cuota IPO ({{ item?.cuota_participacion_ipo }}%):</strong> 
              <span class="font-mono font-weight-bold text-info ms-1">+{{ Number(item?.asignacion_cascada || 0).toFixed(2) }} unidades</span>
            </div>
            <div v-if="Number(item?.pre_asignado_bs || 0) > 0" class="mt-1">
              <VChip color="success" size="small" variant="tonal" class="font-weight-bold">
                ✓ Pre-asignación Bloqueada: +{{ item?.pre_asignado_bs }} unidad(es) reservada(s) de inmediato por Best Seller/Quiebre.
              </VChip>
            </div>
            <div v-else-if="faltanteDirecto <= 0" class="text-caption text-medium-emphasis">
              • El stock efectivo cubre completamente el punto de reorden individual.
            </div>
          </div>
        </VCard>

        <!-- FASE 4.4: EFICIENCIA FINANCIERA (si aplica) -->
        <VCard 
          v-if="Number(item?.ajuste_financiero || 0) < 0 || Number(item?.rescate_best_seller || 0) > 0"
          variant="outlined" 
          class="bg-white rounded-lg pa-4 border-s-lg"
          :class="Number(item?.ajuste_financiero || 0) < 0 ? 'border-error' : 'border-success'"
        >
          <div class="text-subtitle-2 font-weight-black text-slate-800 text-uppercase mb-2" style="color: #0f172a;">
            📌 FASE 4.4: Eficiencia Financiera
          </div>
          <div class="text-body-2 text-high-emphasis">
            <template v-if="Number(item?.ajuste_financiero || 0) < 0">
              <div class="text-error font-weight-bold">• Penalización de Rentabilidad Aplicada:</div>
              <div class="text-caption text-medium-emphasis">
                Producto lento (ROP &lt; 1.0 e IPO &lt; 20%). Se limitó el pedido para no inmovilizar capital y se liberaron {{ Math.abs(item.ajuste_financiero) }} unidades para el líder de la liga.
              </div>
            </template>
            <template v-if="Number(item?.rescate_best_seller || 0) > 0">
              <div class="text-success font-weight-bold">• Bonificación de Líder Aplicada:</div>
              <div class="text-caption text-medium-emphasis">
                Por ser el SKU líder de la liga, recibe +{{ item.rescate_best_seller }} unidad(es) recuperadas del presupuesto sobrante de productos lentos.
              </div>
            </template>
          </div>
        </VCard>

        <!-- FASE 5: SUGERIDO FINAL -->
        <VCard variant="outlined" class="bg-white rounded-lg pa-4 border-s-lg border-success">
          <div class="text-subtitle-2 font-weight-black text-slate-800 text-uppercase mb-2" style="color: #0f172a;">
            📦 FASE 5: Sugerido Final y Resultado
          </div>
          <VRow dense align="center" justify="space-between">
            <VCol cols="12" sm="7" class="text-body-2 text-high-emphasis">
              <div>• <strong>Resultado de Reparto de Liga:</strong> {{ Number(item?.solicitar || 0) }} un</div>
              <div>• <strong>Regla de Empaque / Lote Mínimo:</strong> Sin ajuste adicional</div>
            </VCol>
            <VCol cols="12" sm="5" class="text-sm-end mt-2 mt-sm-0">
              <div class="pa-2 rounded bg-success-lighten-5 border border-success d-inline-block">
                <span class="text-caption font-weight-bold text-success text-uppercase d-block">Resultado Final Sugerido</span>
                <span class="text-h5 font-weight-black text-success font-mono">
                  {{ Number(item?.solicitar || 0) > 0 ? '+' : '' }}{{ Number(item?.solicitar || 0) }} UNIDAD(ES)
                </span>
              </div>
            </VCol>
          </VRow>
        </VCard>

      </VCardText>
      
      <!-- Acciones Inferiores -->
      <VCardActions class="pa-4 bg-grey-darken-4 justify-end border-t">
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

// Best Seller
const isLeader = computed(() => {
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
</style>
