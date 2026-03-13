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
    max-width="500"
    persistent
    :fullscreen="$vuetify.display.xs"
    transition="dialog-bottom-transition"
    class="premium-dialog"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Compacta Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="32" class="me-3 elevation-1">
              <VIcon icon="tabler-clipboard-check" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0">Verificar Conteo</h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem;">
                Validación física
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="handleClose">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-2 pa-sm-3 bg-light d-flex flex-column gap-2">
        <!-- Loader Cargando -->
        <div v-if="!countRecord || isLoading" class="d-flex flex-column align-center justify-center py-6">
          <VProgressCircular indeterminate color="primary" size="32" width="3" />
          <p class="mt-2 text-super-xs font-weight-medium uppercase opacity-60">Sincronizando...</p>
        </div>

        <template v-else>
          <!-- Perfil del Producto Premium -->
          <VCard variant="flat" class="border pa-4 mb-3 bg-white elevation-1 rounded-lg">
            <div class="d-flex align-center gap-3 mb-3">
              <VChip size="small" color="primary" variant="flat" class="font-weight-black px-3">
                ID: {{ countRecord.product?.id || countRecord.product_id }}
              </VChip>
              <div class="d-flex align-center gap-1 text-disabled">
                <VIcon icon="tabler-user" size="14" />
                <span class="text-xs font-weight-bold uppercase">{{ countRecord.user?.username || 'Sistema' }}</span>
              </div>
            </div>
            <h3 class="text-subtitle-1 font-weight-black text-high-emphasis leading-tight uppercase mb-1">
              {{ countRecord.product?.name }}
            </h3>
            <p class="text-xs text-disabled font-weight-medium mb-0 uppercase">
              {{ countRecord.product?.active_ingredient || 'Sin principio activo registrado' }}
            </p>
          </VCard>

          <!-- Comparativa de Stock Premium -->
          <VCard variant="flat" class="border pa-4 mb-3 bg-white elevation-1 rounded-lg">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary"></div>
              <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Resumen de Impacto</span>
            </div>
            
            <div class="d-flex justify-space-around align-center pb-2">
              <div class="text-center">
                <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1">Stock Sistema</span>
                <p class="text-h5 font-weight-black mb-0 text-high-emphasis">{{ formatNumber(currentStock) }}</p>
              </div>
              
              <div class="d-flex flex-column align-center px-2">
                <VIcon icon="tabler-arrows-left-right" color="primary" size="24" class="opacity-40 mb-1" />
                <span class="text-super-xs font-weight-black text-primary opacity-50">VS</span>
              </div>

              <div class="text-center">
                <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1">Conteo Operador</span>
                <p class="text-h5 font-weight-black mb-0 text-warning">{{ formatNumber(countRecord.system_quantity + countRecord.discrepancy) }}</p>
              </div>
            </div>
          </VCard>

          <!-- Sección de Re-conteo Premium -->
          <VCard variant="flat" class="pa-5 rounded-lg border-dashed-2 bg-white text-center shadow-sm d-flex flex-column gap-3 mb-4">
            <div class="d-flex align-center justify-center gap-2">
              <VIcon icon="tabler-edit" color="primary" size="20" />
              <span class="text-subtitle-2 font-weight-black text-primary uppercase letter-spacing-1">Re-conteo Definitivo</span>
            </div>
            
            <div class="d-flex justify-center align-center py-2">
              <AppTextField
                id="recounter-quantity-input"
                v-model.number="newCountedQuantity"
                type="number"
                min="0"
                placeholder="0"
                variant="plain"
                class="ultra-huge-input-text h-auto font-weight-950"
                density="compact"
                hide-details
                autofocus
                @keyup.enter="handleVerify"
              />
            </div>
            
            <VExpandTransition>
              <div v-if="difference !== null" class="mt-2 pt-3 border-t border-opacity-10 d-flex flex-column align-center gap-1">
                <div class="d-flex align-center gap-2">
                  <VIcon :icon="differenceIcon" size="16" :color="differenceColor" />
                  <span class="text-xs font-weight-black uppercase" :class="`text-${differenceColor}`">
                    {{ differenceText }}
                  </span>
                </div>
                <span class="text-super-xs font-weight-bold text-disabled uppercase">
                  ({{ difference === 0 ? 'Sin discrepancias' : 'Se generará ajuste de stock' }})
                </span>
              </div>
            </VExpandTransition>
          </VCard>

          <VAlert
            v-if="loadError"
            type="error"
            variant="tonal"
            density="compact"
            icon="tabler-alert-triangle"
            class="rounded-lg font-weight-black text-xs py-1"
          >
            {{ loadError }}
          </VAlert>
        </template>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <div class="d-flex flex-column gap-3 w-100">
          <VBtn
            :color="difference === 0 ? 'success' : 'primary'"
            variant="flat"
            size="large"
            block
            height="52"
            class="font-weight-black rounded-lg shadow-primary text-button uppercase"
            :disabled="!canVerify"
            :loading="isProcessing"
            @click="handleVerify"
          >
            <VIcon :icon="difference === 0 ? 'tabler-circle-check' : 'tabler-adjustments-horizontal'" size="18" class="me-2" />
            {{ difference === 0 ? "CONFIRMAR VALIDACIÓN" : "CONFIRMAR E IR A AJUSTE" }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            size="large"
            block
            height="46"
            class="font-weight-black rounded-lg text-button uppercase"
            @click="handleClose"
            :disabled="isProcessing"
          >
            CANCELAR PROCESO
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
  border: none;
  background: transparent;
  block-size: auto;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 2rem !important;
  font-weight: 950 !important;
  inline-size: 100%;
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
  block-size: 32px;
  inline-size: 32px;
}

.border-dashed-1 {
  border: 1px dashed rgba(var(--v-border-color), 20%) !important;
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
