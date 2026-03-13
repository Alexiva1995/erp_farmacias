<script setup>
import axios from "@/plugins/axios";
import { formatNumber } from "@/utils/formatters";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  countRecord: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "verify-no-discrepancy", "verify-with-discrepancy"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const isLoading = ref(false);
const isProcessing = ref(false);
const currentStock = ref(null);
const newCountedQuantity = ref(null);
const loadError = ref(null);

watch(
  () => props.modelValue,
  async (isOpening) => {
    if (isOpening && props.countRecord) {
      newCountedQuantity.value = null;
      loadError.value = null;
      currentStock.value = null;
      await loadCurrentStock();
    }
  }
);

const loadCurrentStock = async () => {
  if (!props.countRecord?.product_id) return;
  isLoading.value = true;
  try {
    const response = await axios.get(`/products/${props.countRecord.product_id}/stock`);
    currentStock.value = response.data.stock ?? 0;
  } catch (e) {
    loadError.value = "No se pudo cargar el stock actual.";
    currentStock.value = 0;
  } finally {
    isLoading.value = false;
  }
};

const difference = computed(() => {
  if (newCountedQuantity.value === null || currentStock.value === null) return null;
  return newCountedQuantity.value - currentStock.value;
});

const differenceColor = computed(() => {
  if (difference.value === null) return "secondary";
  if (difference.value === 0) return "success";
  return difference.value > 0 ? "info" : "error";
});

const differenceIcon = computed(() => {
  if (difference.value === null) return "tabler-minus";
  if (difference.value === 0) return "tabler-shield-check";
  return difference.value > 0 ? "tabler-trending-up" : "tabler-trending-down";
});

const differenceText = computed(() => {
  if (difference.value === null) return "";
  if (difference.value === 0) return "Stock Correcto";
  const absDiff = Math.abs(difference.value);
  return difference.value > 0 ? `Sobran ${absDiff} unidades` : `Faltan ${absDiff} unidades`;
});

const canVerify = computed(() => {
  return newCountedQuantity.value !== null && newCountedQuantity.value >= 0 && !isProcessing.value && !isLoading.value;
});

const handleVerify = () => {
  if (!canVerify.value) return;
  isProcessing.value = true;

  if (difference.value === 0) {
    emit("verify-no-discrepancy", { countRecord: props.countRecord });
  } else {
    emit("verify-with-discrepancy", {
      countRecord: props.countRecord,
      newCountedQuantity: newCountedQuantity.value,
      currentStock: currentStock.value,
    });
  }

  isProcessing.value = false;
  isVisible.value = false;
};

const handleClose = () => {
  isVisible.value = false;
};
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="550"
    persistent
    class="premium-dialog"
  >
    <VCard class="detail-dialog-card overflow-hidden rounded-xl border-0 elevation-24">
      <!-- Cabecera Premium Estilo Trazabilidad -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-lg">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-clipboard-check" color="primary" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">Verificar Conteo</h2>
              <span class="text-caption text-white opacity-75 letter-spacing-1 uppercase font-weight-bold" style="font-size: 0.65rem;">
                Validación de inventario físico
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="handleClose">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- Loader Cargando -->
        <div v-if="!countRecord || isLoading" class="d-flex flex-column align-center justify-center py-12">
          <VProgressCircular indeterminate color="primary" size="64" width="6" />
          <p class="mt-4 text-medium-emphasis font-weight-medium uppercase letter-spacing-1 text-xs">Sincronizando stock...</p>
        </div>

        <div v-else class="d-flex flex-column gap-4">
          <VCard variant="flat" class="border pa-5 bg-white elevation-1 rounded-xl">
            <div class="d-flex flex-column min-width-0">
              <div class="d-flex align-center gap-2 mb-2">
                <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black uppercase">
                  ID: {{ countRecord.product?.id || countRecord.product_id }}
                </VChip>
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Referencia</span>
              </div>
              <h3 class="text-h5 font-weight-black text-high-emphasis leading-tight uppercase truncate-2-lines mb-2">
                {{ countRecord.product?.name }}
              </h3>
              <div class="d-flex align-center gap-1 opacity-80">
                <VIcon icon="tabler-user-edit" size="14" class="text-primary" />
                <span class="text-super-xs font-weight-black text-medium-emphasis uppercase truncate letter-spacing-05">
                  Contado por {{ countRecord.user?.username || 'Sistema' }}
                </span>
              </div>
            </div>
          </VCard>

          <!-- Banner Comparación Estilo Trazabilidad -->
          <VCard variant="flat" class="border overflow-hidden elevation-1 rounded-xl bg-white">
            <div class="bg-primary-lighten-5 pa-3 border-b d-flex align-center">
              <VIcon icon="tabler-arrows-left-right" size="20" class="text-primary me-2" />
              <span class="text-subtitle-2 font-weight-black text-uppercase">Comparación de Stock</span>
            </div>
            
            <div class="pa-4 stock-impact d-flex justify-space-around align-center">
              <div class="text-center">
                <span class="text-overline font-weight-black text-disabled leading-none mb-2 d-xl-block">Stock Sistema</span>
                <p class="text-h5 font-weight-black mb-0 text-high-emphasis">{{ formatNumber(currentStock) }}</p>
                <span class="text-super-xs font-weight-bold text-disabled">UNDS</span>
              </div>
              
              <VIcon icon="tabler-arrow-narrow-right" color="primary" size="32" class="opacity-50" />

              <div class="text-center">
                <span class="text-overline font-weight-black text-disabled leading-none mb-2 d-xl-block">Conteo Operador</span>
                <p class="text-h5 font-weight-black mb-0 text-warning">{{ formatNumber(countRecord.system_quantity + countRecord.discrepancy) }}</p>
                <span class="text-super-xs font-weight-bold text-disabled">UNDS</span>
              </div>
            </div>
          </VCard>

          <!-- Sección de Re-conteo -->
          <VCard variant="flat" class="pa-6 rounded-xl border-dashed-2 bg-white text-center shadow-sm">
            <div class="mb-4">
              <div class="text-xs font-weight-black text-high-emphasis leading-none mb-1 uppercase letter-spacing-1">Re-conteo de Confirmación</div>
              <div class="text-super-xs font-weight-bold text-disabled uppercase">Determine la cantidad física definitiva</div>
            </div>

            <div class="huge-input-wrapper mx-auto mb-2">
              <AppTextField
                v-model.number="newCountedQuantity"
                type="number"
                min="0"
                placeholder="0"
                variant="plain"
                class="ultra-huge-input-text"
                autofocus
                @wheel="$event.target.blur()"
              />
            </div>

            <!-- Feedback de Diferencia -->
            <VExpandTransition>
              <div v-if="difference !== null" class="mt-4 pt-4 border-t border-opacity-10">
                <div 
                  class="d-flex align-center justify-center gap-4 pa-4 rounded-xl elevation-1 transition-all bg-white border"
                  :class="[`border-${differenceColor} border-opacity-30`]"
                >
                  <div class="icon-circle shadow-sm" :class="`bg-${differenceColor}`">
                    <VIcon :icon="differenceIcon" size="20" color="white" />
                  </div>
                  <div class="text-start flex-grow-1">
                    <div class="text-sm font-weight-black uppercase leading-tight" :class="`text-${differenceColor}`">
                      {{ differenceText }}
                    </div>
                    <div class="text-super-xs font-weight-bold text-medium-emphasis uppercase letter-spacing-05">
                      {{ difference === 0 ? 'Los niveles coinciden perfectamente' : 'Se requiere distribución de lotes' }}
                    </div>
                  </div>
                </div>
              </div>
            </VExpandTransition>
          </VCard>

          <VAlert
            v-if="loadError"
            type="error"
            variant="tonal"
            density="compact"
            icon="tabler-alert-triangle"
            class="mt-2 rounded-xl font-weight-black text-xs uppercase"
          >
            {{ loadError }}
          </VAlert>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <div class="d-flex flex-column gap-3 w-100">
          <VBtn
            :color="difference === 0 ? 'success' : 'primary'"
            variant="flat"
            size="large"
            block
            height="48"
            class="font-weight-black rounded-lg shadow-lg elevation-2"
            :disabled="!canVerify"
            :loading="isProcessing"
            @click="handleVerify"
          >
            <VIcon :icon="difference === 0 ? 'tabler-circle-check' : 'tabler-adjustments-horizontal'" class="me-2" />
            {{ difference === 0 ? "CONFIRMAR SIN AJUSTE" : "IR A AJUSTE DE LOTES" }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            size="large"
            block
            height="44"
            class="font-weight-black rounded-lg"
            @click="handleClose"
            :disabled="isProcessing"
          >
            CANCELAR
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.stock-impact {
  background-color: rgba(var(--v-theme-primary), 0.02);
}

.ultra-huge-input-text :deep(input) {
  block-size: auto;
  inline-size: 100%;
  border: none;
  background: transparent;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 3rem !important;
  font-weight: 900 !important;
  line-height: 1;
  outline: none;
  text-align: center !important;
}

.ultra-huge-input-text :deep(.v-field__input) {
  padding: 0 !important;
}

.header-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 60px;
  inline-size: 60px;
}

.icon-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  block-size: 40px;
  inline-size: 40px;
}

.border-dashed-2 {
  border: 2px dashed rgba(var(--v-border-color), 15%) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1.5px !important; }
.letter-spacing-05 { letter-spacing: 0.5px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.shadow-lg {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 10%) !important;
}

.transition-all {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.animate-pulse {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.5;
    transform: scale(1.1);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
