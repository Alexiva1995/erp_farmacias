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
    class="premium-dialog"
  >
    <VCard class="overflow-hidden rounded-xl border-0 elevation-24">
      <!-- Header Premium con Gradiente -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center justify-space-between text-white shadow-lg">
          <div class="d-flex align-center gap-3">
            <div class="header-icon-box shadow-sm elevation-2">
              <VIcon icon="tabler-clipboard-check" size="24" />
            </div>
            <div>
              <div class="text-h6 font-weight-black leading-tight">Verificar Conteo</div>
              <div class="text-super-xs font-weight-bold uppercase opacity-80 letter-spacing-1">Validación de inventario físico</div>
            </div>
          </div>
          <IconBtn color="white" variant="tonal" size="small" @click="handleClose">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-8 pt-6 bg-surface">
        <div v-if="!countRecord || isLoading" class="text-center py-12">
          <VProgressCircular indeterminate color="primary" size="64" width="6">
            <template #default>
              <VIcon icon="tabler-pill" size="24" class="text-primary animate-pulse" />
            </template>
          </VProgressCircular>
          <div class="mt-6 text-xs font-weight-black text-disabled uppercase letter-spacing-1">Sincronizando stock...</div>
        </div>

        <div v-else>
          <!-- Perfil del Producto Premium -->
          <div class="product-profile-box pa-4 rounded-xl mb-6 border d-flex align-center gap-4 elevation-1 bg-surface-variant bg-opacity-10">
            <VAvatar
              v-if="countRecord.product?.photo_url"
              size="72"
              variant="flat"
              rounded="lg"
              class="border-2 elevation-4"
              :image="countRecord.product.photo_url"
            />
            <VAvatar v-else size="72" variant="tonal" color="primary" rounded="lg" class="border-2 shadow-sm">
              <VIcon icon="tabler-pill" size="36" />
            </VAvatar>
            <div class="flex-grow-1 min-width-0">
              <div class="text-primary font-weight-black text-xs mb-1 uppercase letter-spacing-1 opacity-70">
                ID: {{ countRecord.product?.id || countRecord.product_id }}
              </div>
              <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase truncate">
                {{ countRecord.product?.name }}
              </h3>
              <div class="d-flex align-center gap-1 mt-1 opacity-80">
                <VIcon icon="tabler-user-edit" size="14" class="text-primary" />
                <span class="text-super-xs font-weight-black text-medium-emphasis uppercase truncate letter-spacing-05">
                  Contado por {{ countRecord.user?.username || 'Sistema' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Cuadrícula de Comparación Responsiva -->
          <div class="d-grid comparison-grid gap-4 mb-6">
            <VCard variant="flat" border class="pa-4 rounded-xl bg-light-primary border-primary border-opacity-10 text-center elevation-1">
              <div class="d-flex align-center justify-center gap-2 mb-2">
                <VIcon icon="tabler-database-cog" size="20" class="text-primary" />
                <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1">Stock Sistema</span>
              </div>
              <div class="text-h3 font-weight-black text-primary leading-none">
                {{ formatNumber(currentStock) }}
                <span class="text-xs font-weight-bold opacity-60">UNDS</span>
              </div>
            </VCard>

            <VCard variant="flat" border class="pa-4 rounded-xl bg-light-warning border-warning border-opacity-10 text-center elevation-1">
              <div class="d-flex align-center justify-center gap-2 mb-2">
                <VIcon icon="tabler-user-check" size="20" class="text-warning" />
                <span class="text-super-xs font-weight-black text-warning uppercase letter-spacing-1">Conteo Operador</span>
              </div>
              <div class="text-h3 font-weight-black text-warning leading-none">
                {{ formatNumber(countRecord.system_quantity + countRecord.discrepancy) }}
                <span class="text-xs font-weight-bold opacity-60">UNDS</span>
              </div>
            </VCard>
          </div>

          <!-- Sección de Re-conteo Ultra-Clean -->
          <div class="recount-container pa-6 rounded-xl border-dashed-2 bg-light-surface text-center">
            <div class="mb-4">
              <div class="text-xs font-weight-black text-high-emphasis leading-none mb-1 uppercase letter-spacing-1">Re-conteo de Verificación</div>
              <div class="text-super-xs font-weight-bold text-disabled uppercase">Confirme la cantidad física real</div>
            </div>

            <div class="huge-input-wrapper mx-auto mb-2">
              <input
                v-model.number="newCountedQuantity"
                type="number"
                min="0"
                placeholder="0"
                class="ultra-huge-input"
                autofocus
                @wheel="$event.target.blur()"
              >
            </div>

            <!-- Feedback de Diferencia Animado -->
            <VExpandTransition>
              <div v-if="difference !== null" class="mt-4 pt-4 border-t border-opacity-10">
                <div 
                  class="d-flex align-center justify-center gap-4 pa-4 rounded-xl elevation-2 shadow-sm transition-all"
                  :class="[`bg-${differenceColor} bg-opacity-10`, `border-${differenceColor} border border-opacity-20`]"
                >
                  <div class="icon-circle shadow-sm" :class="`bg-${differenceColor}`">
                    <VIcon :icon="differenceIcon" size="20" color="white" />
                  </div>
                  <div class="text-start flex-grow-1">
                    <div class="text-sm font-weight-black uppercase leading-tight" :class="`text-${differenceColor}`">
                      {{ differenceText }}
                    </div>
                    <div class="text-super-xs font-weight-bold text-medium-emphasis uppercase letter-spacing-05">
                      {{ difference === 0 ? 'Los niveles coinciden perfectamente' : 'Se requiere ajuste en la distribución' }}
                    </div>
                  </div>
                </div>
              </div>
            </VExpandTransition>
          </div>

          <VAlert
            v-if="loadError"
            type="error"
            variant="tonal"
            density="compact"
            icon="tabler-alert-triangle"
            class="mt-4 rounded-xl font-weight-black text-xs uppercase"
          >
            {{ loadError }}
          </VAlert>
        </div>
      </VCardText>

      <VCardActions class="pa-4 pa-sm-8 pt-0">
        <div class="d-flex flex-column gap-3 w-100">
          <VBtn
            :color="difference === 0 ? 'success' : 'primary'"
            variant="flat"
            size="large"
            block
            height="56"
            class="font-weight-black rounded-lg shadow-lg elevation-4 text-button transition-transform"
            :disabled="!canVerify"
            :loading="isProcessing"
            @click="handleVerify"
            @mousedown="$event.currentTarget.style.transform = 'scale(0.98)'"
            @mouseup="$event.currentTarget.style.transform = 'scale(1)'"
          >
            <VIcon :icon="difference === 0 ? 'tabler-circle-check' : 'tabler-adjustments-horizontal'" class="me-2" />
            {{ difference === 0 ? "CONFIRMAR SIN AJUSTE" : "IR A AJUSTE DE LOTES" }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            size="large"
            block
            height="56"
            class="font-weight-black rounded-lg text-button"
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
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-primary-darken-1.header-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 40px;
  inline-size: 40px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 20%);
}

.product-profile-box {
  transition: all 0.3s ease;
}

.comparison-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 600px) {
  .comparison-grid {
    grid-template-columns: 1fr;
  }
}

.bg-light-primary { background-color: rgba(var(--v-theme-primary), 5%) !important; }
.bg-light-warning { background-color: rgba(var(--v-theme-warning), 5%) !important; }
.bg-light-surface { background-color: rgba(var(--v-theme-on-surface), 2%) !important; }

.recount-container {
  transition: all 0.3s ease;
}

.ultra-huge-input {
  inline-size: 100%;
  border: none;
  background: transparent;
  color: rgb(var(--v-theme-primary));
  font-size: 4rem;
  font-weight: 900;
  line-height: 1;
  outline: none;
  text-align: center;
}

.ultra-huge-input::placeholder {
  color: rgba(var(--v-theme-primary), 10%);
}

/* Ocultar flechas nativas */
.ultra-huge-input::-webkit-outer-spin-button,
.ultra-huge-input::-webkit-inner-spin-button {
  margin: 0;
  appearance: none;
}

.icon-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 40px;
  inline-size: 40px;
  border-radius: 50%;
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
wrap;
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
  0% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.1); }
  100% { opacity: 1; transform: scale(1); }
}

:deep(.v-btn.v-btn--size-large) {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
  text-transform: uppercase;
}

.text-button {
  font-size: 0.9rem !important;
}
</style>
