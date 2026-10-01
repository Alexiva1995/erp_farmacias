<template>
  <VDialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="850">
    <VCard class="pa-0 rounded-lg">
      <!-- Encabezado -->
      <VCardItem class="bg-grey-darken-4 text-white px-4 py-3 d-flex justify-space-between align-center">
        <div class="d-flex align-center ga-3">
          <VIcon icon="tabler-terminal-2" color="primary" />
          <span class="text-subtitle-1 font-weight-bold text-uppercase">
            Auditoría de Decisión: {{ item?.name || item?.producto }} (ID: {{ item?.product_id ?? item?.id }})
          </span>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" @click="$emit('update:modelValue', false)"></VBtn>
      </VCardItem>

      <VDivider />

      <!-- Contenido Estilo Terminal -->
      <VCardText class="pa-0 bg-black text-green-lighten-1" style="font-family: 'Consolas', 'Courier New', monospace; font-size: 13px; max-height: 75vh; overflow-y: auto;">
        <div class="pa-4">
          <!-- FASE 1 -->
          <div class="mb-4">
            <div class="font-weight-bold text-white mb-1">📌 FASE 1: CLUSTERIZACIÓN Y LIGA</div>
            <div class="pl-3">
              • Grupo: {{ item?.group_name || 'N/A' }} (ID: {{ item?.group_id }}) | Liga: <span :class="item?.liga_color ? `text-${item.liga_color}` : ''">{{ item?.tier_name || item?.liga_nombre || 'N/A' }}</span> (Costo: ${{ Number(item?.costo ?? item?.unit_cost ?? 0).toFixed(2) }})
            </div>
          </div>

          <!-- FASE 2 -->
          <div class="mb-4">
            <div class="font-weight-bold text-white mb-1">📌 FASE 2: SANACIÓN DE DEMANDA Y ROP INDIVIDUAL</div>
            <div class="pl-3">
              • Ventas Totales: {{ (Number(item?.ventas_30d || 0) + Number(item?.ventas_m2 || 0) + Number(item?.ventas_m3 || 0)) }} un | Días con Stock Totales: {{ (Number(item?.dias_con_stock_m1 || 30) + Number(item?.dias_con_stock_m2 || 30) + Number(item?.dias_con_stock_m3 || 30)) }}d (M1: {{ item?.dias_con_stock_m1 || 30 }}d, M2: {{ item?.dias_con_stock_m2 || 30 }}d, M3: {{ item?.dias_con_stock_m3 || 30 }}d)<br>
              <template v-if="Number(item?.dias_quiebre || 0) > 0">
                <span class="text-warning">• ALERTA DE QUIEBRE CRÓNICO: Registra {{ item?.dias_quiebre }} días de quiebre recientes.</span><br>
              </template>
              • Venta Diaria Sanada (VPD): {{ Number(item?.promedio_calculado || 0) > 0 ? (Number(item?.promedio_calculado) / 30).toFixed(3) : '0.000' }} un/día.<br>
              • ROP Calculado ({{ Number(item?.lead_time_days || 7) + Number(item?.buffer_days || 7) }} días): {{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }} unidades.<br>
              • Stock Efectivo: {{ Number(item?.stock_efectivo || 0).toFixed(1) }} un (Físico: {{ item?.stock_fisico || item?.lote_quantity || 0 }}, Tránsito: {{ item?.stock_transito || item?.totalQuantityInAutoOrder || 0 }}).<br>
              • Stock Útil (Capado al ROP): min({{ Number(item?.stock_efectivo || 0).toFixed(1) }}, {{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }}) = {{ Number(item?.stock_util || 0).toFixed(1) }} unidades.
            </div>
          </div>

          <!-- FASE 3 -->
          <div class="mb-4">
            <div class="font-weight-bold text-white mb-1">📌 FASE 3: PARTICIPACIÓN DE CATEGORÍA (IPO)</div>
            <div class="pl-3">
              • IPO Individual: {{ Number(item?.ipo || 0).toFixed(1) }}% 
              <span v-if="Number(item?.ipo || 0) >= 35" class="text-success font-weight-bold">-> [LÍDER DE LIGA DE ALTA ROTACIÓN]</span>
            </div>
          </div>

          <!-- FASE 4 -->
          <div class="mb-4">
            <div class="font-weight-bold text-white mb-1">📌 FASE 4: PRE-ASIGNACIÓN PRIORITARIA (FASE 4.1 BEST SELLER)</div>
            <div class="pl-3">
              <template v-if="Number(item?.pre_asignado_bs || 0) > 0 || (Number(item?.rop_calculado ?? item?.rop ?? 0) > Number(item?.stock_efectivo || 0))">
                • Faltante Individual Directo: ROP ({{ Number(item?.rop_calculado ?? item?.rop ?? 0).toFixed(2) }}) - Stock Efectivo ({{ Number(item?.stock_efectivo || 0).toFixed(1) }}) = {{ Math.max(0, Number(item?.rop_calculado ?? item?.rop ?? 0) - Number(item?.stock_efectivo || 0)).toFixed(2) }} un.<br>
                <template v-if="Number(item?.pre_asignado_bs || 0) > 0">
                  <span class="text-success">• Condición Cumplida: El sistema rescató este producto por tener alto IPO o quiebre reciente.</span><br>
                  • Pre-asignación Bloqueada: +{{ item?.pre_asignado_bs }} unidad(es) reservada(s) de inmediato.<br>
                </template>
                <template v-else>
                  • Condición No Cumplida: No calificó para rescate inmediato o no tiene faltante grave.<br>
                </template>
              </template>
              <template v-else>
                • No presenta faltante directo. Stock Efectivo cubre el ROP.
              </template>
            </div>
          </div>

          <!-- FASE 4.4 -->
          <template v-if="Number(item?.ajuste_financiero || 0) < 0 || Number(item?.rescate_best_seller || 0) > 0">
            <div class="mb-4">
              <div class="font-weight-bold text-white mb-1">📌 FASE 4.4: EFICIENCIA FINANCIERA</div>
              <div class="pl-3">
                <template v-if="Number(item?.ajuste_financiero || 0) < 0">
                  <span class="text-error font-weight-bold">• PENALIZACIÓN DE RENTABILIDAD APLICADA:</span><br>
                  • Motivo: ROP &lt; 1.0 e IPO &lt; 20%. No se puede sobrestockear un producto lento.<br>
                  • Ajuste: Sugerido topado a 1 unidad. Se restaron {{ Math.abs(item.ajuste_financiero) }} unidades y se liberaron para el líder de liga.
                </template>
                <template v-if="Number(item?.rescate_best_seller || 0) > 0">
                  <span class="text-success font-weight-bold">• BONIFICACIÓN DE LÍDER APLICADA:</span><br>
                  • Motivo: Es el producto de mayor IPO en la liga.<br>
                  • Ajuste: Recibe +{{ item.rescate_best_seller }} unidad(es) expropiada(s) del presupuesto sobrante de productos lentos de bajo IPO.
                </template>
              </div>
            </div>
          </template>

          <!-- FASE 5 -->
          <div class="mb-4">
            <div class="font-weight-bold text-white mb-1">📌 FASE 5: SUGERIDO FINAL</div>
            <div class="pl-3">
              • Resultado de Reparto de Liga (Cascada): {{ Number(item?.solicitar || 0) }} un.<br>
              • Regla de Empaque / Lote Mínimo: Sin ajuste ({{ Number(item?.solicitar || 0) }} un).<br>
              <span class="text-info font-weight-bold text-h6 mt-2 d-block">• RESULTADO FINAL SUGERIDO: {{ Number(item?.solicitar || 0) > 0 ? '+' : '' }}{{ Number(item?.solicitar || 0) }} UNIDAD(ES)</span>
            </div>
          </div>
        </div>
      </VCardText>
      
      <VCardActions class="pa-3 bg-grey-darken-4 justify-end">
        <VBtn variant="tonal" color="primary" @click="$emit('update:modelValue', false)">Cerrar Auditoría</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup>
defineProps({
  modelValue: {
    type: Boolean,
    required: true
  },
  item: {
    type: Object,
    default: () => ({})
  }
});

defineEmits(['update:modelValue']);
</script>

<style scoped>
/* Estilo terminal para la ventana de auditoría */
</style>
