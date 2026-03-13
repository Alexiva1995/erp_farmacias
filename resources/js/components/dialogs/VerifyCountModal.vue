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
        <div class="header-gradient pa-5 d-flex align-center justify-space-between text-white">
          <div class="d-flex align-center gap-3">
            <div class="header-icon-box shadow-sm">
              <VIcon icon="tabler-clipboard-check" size="24" />
            </div>
            <div>
              <div class="text-h6 font-weight-black leading-tight">Verificar Conteo</div>
              <div class="text-super-xs font-weight-bold uppercase opacity-80">Validación de inventario físico</div>
            </div>
          </div>
          <IconBtn color="white" variant="tonal" size="small" @click="handleClose">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 pt-5 bg-surface">
        <div v-if="!countRecord || isLoading" class="text-center py-10">
          <VProgressCircular indeterminate color="primary" size="50" width="4">
            <template #default>
              <VIcon icon="tabler-pill" size="20" class="text-primary" />
            </template>
          </VProgressCircular>
          <div class="mt-4 text-caption font-weight-bold text-disabled uppercase">Cargando datos del producto...</div>
        </div>

        <div v-else>
          <!-- Perfil del Producto Premium -->
          <div class="product-profile-box pa-4 rounded-lg mb-5 border d-flex align-center gap-4">
            <VAvatar
              v-if="countRecord.product?.photo_url"
              size="64"
              variant="tonal"
              rounded="lg"
              class="border elevation-2"
              :image="countRecord.product.photo_url"
            />
            <VAvatar v-else size="64" variant="tonal" border color="primary" rounded="lg">
              <VIcon icon="tabler-pill" size="32" />
            </VAvatar>
            <div class="flex-grow-1 min-width-0">
              <div class="text-primary font-weight-black text-xs mb-1 uppercase letter-spacing-1">
                {{ countRecord.product?.id || countRecord.product_id }}
              </div>
              <h3 class="text-subtitle-1 font-weight-black text-high-emphasis leading-tight uppercase truncate">
                {{ countRecord.product?.name }}
              </h3>
              <div class="d-flex align-center gap-1 mt-1">
                <VIcon icon="tabler-user" size="14" class="text-disabled" />
                <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase truncate">
                  Contado por {{ countRecord.user?.username || 'Sistema' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Cuadrícula de Comparación Responsiva -->
          <div class="d-grid comparison-grid gap-4 mb-5">
            <VCard variant="flat" border class="pa-4 rounded-lg bg-light-primary border-opacity-20 text-center">
              <div class="d-flex align-center justify-center gap-2 mb-2">
                <VIcon icon="tabler-device-desktop-analytics" size="16" class="text-primary" />
                <span class="text-super-xs font-weight-black text-primary uppercase">Stock Sistema</span>
              </div>
              <div class="text-h4 font-weight-black text-primary leading-none">
                {{ formatNumber(currentStock) }}
                <span class="text-xs font-weight-bold opacity-70">UNDS</span>
              </div>
            </VCard>

            <VCard variant="flat" border class="pa-4 rounded-lg bg-light-warning border-opacity-20 text-center">
              <div class="d-flex align-center justify-center gap-2 mb-2">
                <VIcon icon="tabler-clipboard-list" size="16" class="text-warning" />
                <span class="text-super-xs font-weight-black text-warning uppercase">Conteo Operador</span>
              </div>
              <div class="text-h4 font-weight-black text-warning leading-none">
                {{ formatNumber(countRecord.system_quantity + countRecord.discrepancy) }}
                <span class="text-xs font-weight-bold opacity-70">UNDS</span>
              </div>
            </VCard>
          </div>

          <!-- Sección de Re-conteo -->
          <div class="bg-light-surface pa-5 rounded-xl border-dashed-2">
            <div class="text-center mb-4">
              <div class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight mb-1">RE-CONTEO DE VERIFICACIÓN</div>
              <div class="text-xs font-weight-bold text-disabled uppercase">Ingrese la cantidad física final confirmada</div>
            </div>

            <AppTextField
              v-model.number="newCountedQuantity"
              type="number"
              min="0"
              placeholder="0"
              class="huge-input mb-2"
              prepend-inner-icon="tabler-calculator"
              autofocus
              hide-details="auto"
              variant="plain"
            />

            <!-- Feedback de Diferencia -->
            <VExpandTransition>
              <div v-if="difference !== null" class="mt-4 pt-4 border-t border-opacity-10">
                <div 
                  class="d-flex align-center justify-center gap-3 pa-3 rounded-lg"
                  :class="`bg-${differenceColor} bg-opacity-10`"
                >
                  <div class="icon-circle" :class="`bg-${differenceColor}`">
                    <VIcon :icon="differenceIcon" size="18" color="white" />
                  </div>
                  <div class="text-center flex-grow-1">
                    <div class="text-xs font-weight-black uppercase" :class="`text-${differenceColor}`">
                      {{ difference === 0 ? 'Conteo Coincide' : `Diferencia: ${difference > 0 ? '+' : ''}${difference} UNDS` }}
                    </div>
                    <div class="text-super-xs font-weight-bold text-medium-emphasis">
                      {{ difference === 0 ? 'Sin ajustes requeridos' : 'Se activará distribución de lotes' }}
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
            icon="tabler-alert-circle"
            class="mt-4 rounded-lg font-weight-bold text-xs"
          >
            {{ loadError }}
          </VAlert>
        </div>
      </VCardText>

      <VCardActions class="pa-4 pa-sm-6 pt-0">
        <div class="d-flex flex-column gap-3 w-100">
          <VBtn
            :color="difference === 0 ? 'success' : 'primary'"
            variant="flat"
            size="large"
            block
            height="48"
            class="font-weight-black rounded-lg shadow-sm"
            :disabled="!canVerify"
            :loading="isProcessing"
            @click="handleVerify"
          >
            {{ difference === 0 ? "CONFIRMAR SIN AJUSTE" : "IR A AJUSTE DE LOTES" }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            size="large"
            block
            height="48"
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
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-primary-darken-1)));
}

.header-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 20%);
}

.product-profile-box {
  display: flex;
  align-items: center;
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), 12%) !important;
  background-color: rgba(var(--v-theme-surface-variant), 3%);
  gap: 16px;
}

.comparison-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: 1fr 1fr;
}

@media (max-width: 600px) {
  .comparison-grid {
    grid-template-columns: 1fr;
  }
}

.bg-light-primary { background-color: rgba(var(--v-theme-primary), 5%) !important; }
.bg-light-warning { background-color: rgba(var(--v-theme-warning), 5%) !important; }
.bg-light-surface { background-color: rgba(var(--v-theme-on-surface), 2%) !important; }

.huge-input :deep(input) {
  block-size: 60px !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 2.5rem !important;
  font-weight: 900 !important;
  text-align: center !important;
}

.icon-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  block-size: 32px;
  inline-size: 32px;
}

.border-dashed-2 {
  border: 2px dashed rgba(var(--v-border-color), 15%) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.leading-none { line-height: 1 !important; }

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.opacity-80 { opacity: 0.8; }

.border-t {
  border-block-start: 1px solid !important;
}

:deep(.v-btn.v-btn--size-large) {
  font-size: 0.875rem !important;
  letter-spacing: 0.5px !important;
}
</style>
