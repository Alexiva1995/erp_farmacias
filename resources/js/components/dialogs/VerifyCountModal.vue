<script setup>
import axios from "@/plugins/axios";
import { formatNumber } from "@/utils/formatters";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  countRecord: { type: Object, default: null },
});

const emit = defineEmits([
  "update:modelValue",
  "verify-no-discrepancy",
  "verify-with-discrepancy",
]);

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
  },
);

const loadCurrentStock = async () => {
  if (!props.countRecord?.product_id) return;
  isLoading.value = true;
  try {
    const response = await axios.get(
      `/products/${props.countRecord.product_id}/stock`,
    );
    currentStock.value = response.data.stock ?? 0;
  } catch (e) {
    loadError.value = "No se pudo cargar el stock actual.";
    currentStock.value = 0;
  } finally {
    isLoading.value = false;
  }
};

const difference = computed(() => {
  if (newCountedQuantity.value === null || currentStock.value === null)
    return null;
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
  return difference.value > 0
    ? `Sobran ${absDiff} unidades`
    : `Faltan ${absDiff} unidades`;
});

const canVerify = computed(() => {
  return (
    newCountedQuantity.value !== null &&
    newCountedQuantity.value >= 0 &&
    !isProcessing.value &&
    !isLoading.value
  );
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
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-clipboard-check"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Verificar Conteo
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Validación Física de Inventario • Barrio Sucre
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="handleClose"
            :disabled="isProcessing"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-4">
        <!-- Loader Cargando -->
        <div
          v-if="!countRecord || isLoading"
          class="d-flex flex-column align-center justify-center py-10"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="40"
            width="4"
          />
          <p class="mt-4 text-xs font-weight-black uppercase text-disabled letter-spacing-1">
            Sincronizando Stock Actual...
          </p>
        </div>

        <template v-else>
          <!-- Perfil del Producto Premium -->
          <VCard
            variant="flat"
            class="pa-5 bg-white rounded-xl border shadow-sm"
          >
            <div class="d-flex align-center justify-space-between mb-4">
              <VChip
                size="small"
                color="primary"
                variant="flat"
                class="font-weight-black px-3 rounded-lg"
              >
                ID: {{ countRecord.product?.id || countRecord.product_id }}
              </VChip>
              <div class="d-flex align-center gap-2 text-disabled leading-none">
                <VIcon
                  icon="tabler-user-check"
                  size="16"
                />
                <span class="text-super-xs font-weight-black uppercase letter-spacing-1">
                  Responsable: {{ countRecord.user?.username || "Sistema" }}
                </span>
              </div>
            </div>
            <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase mb-1">
              {{ countRecord.product?.name }}
            </h3>
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-medicine-syrup"
                size="14"
                color="disabled"
              />
              <p class="text-xs text-disabled font-weight-bold mb-0 uppercase letter-spacing-05">
                {{ countRecord.product?.active_ingredient || "Sin principio activo registrado" }}
              </p>
            </div>
          </VCard>

          <!-- Comparativa de Stock Premium -->
          <VCard
            variant="flat"
            class="pa-5 bg-white rounded-xl border shadow-sm"
          >
            <div class="d-flex align-center gap-2 mb-6">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Auditoría de Existencias</span>
            </div>

            <div class="d-flex justify-space-around align-center">
              <div class="text-center">
                <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-2 letter-spacing-1">Stock Sistema</span>
                <VAvatar
                  color="grey-lighten-4"
                  size="64"
                  class="rounded-xl border mb-2"
                >
                  <span class="text-h5 font-weight-black text-high-emphasis">{{ formatNumber(currentStock) }}</span>
                </VAvatar>
              </div>

              <div class="d-flex flex-column align-center">
                <VIcon
                  icon="tabler-transfer-in"
                  color="primary"
                  size="24"
                  class="opacity-40"
                />
                <span class="text-super-xs font-weight-black text-primary opacity-50 uppercase mt-1">Comparar</span>
              </div>

              <div class="text-center">
                <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-2 letter-spacing-1">Conteo Operador</span>
                <VAvatar
                  color="warning"
                  variant="tonal"
                  size="64"
                  class="rounded-xl border-warning border-opacity-25 mb-2"
                >
                  <span class="text-h5 font-weight-black text-warning">{{ formatNumber(countRecord.system_quantity + countRecord.discrepancy) }}</span>
                </VAvatar>
              </div>
            </div>
          </VCard>

          <!-- Sección de Re-conteo Premium -->
          <VCard
            variant="flat"
            class="pa-6 rounded-xl border-dashed-2 bg-white text-center shadow-sm"
          >
            <div class="d-flex align-center justify-center gap-2 mb-4">
              <VAvatar
                color="primary"
                size="28"
                variant="tonal"
                class="rounded-lg"
              >
                <VIcon
                  icon="tabler-edit"
                  size="16"
                />
              </VAvatar>
              <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Confirmación de Conteo Definitivo</span>
            </div>

            <div class="d-flex justify-center align-center py-2">
              <VTextField
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
              <div
                v-if="difference !== null"
                class="mt-4 pt-4 border-t border-dashed d-flex flex-column align-center gap-2 animate__animated animate__fadeIn"
              >
                <div class="d-flex align-center gap-2 px-4 py-1 rounded-pill bg-opacity-10" :class="`bg-${differenceColor}`">
                  <VIcon
                    :icon="differenceIcon"
                    size="18"
                    :color="differenceColor"
                  />
                  <span
                    class="text-xs font-weight-black uppercase letter-spacing-1"
                    :class="`text-${differenceColor}`"
                  >
                    {{ differenceText }}
                  </span>
                </div>
                <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">
                  {{ difference === 0 ? "✓ Conteo sin discrepancias detectadas" : "⚠ Se generará un ajuste de inventario automático" }}
                </span>
              </div>
            </VExpandTransition>
          </VCard>

          <VAlert
            v-if="loadError"
            type="error"
            variant="flat"
            density="comfortable"
            icon="tabler-alert-triangle"
            class="rounded-xl font-weight-black text-xs shadow-sm mt-2"
          >
            {{ loadError }}
          </VAlert>
        </template>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="handleClose"
              :disabled="isProcessing"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              :color="difference === 0 ? 'success' : 'primary'"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :disabled="!canVerify"
              :loading="isProcessing"
              @click="handleVerify"
            >
              <VIcon
                start
                :icon="difference === 0 ? 'tabler-circle-check' : 'tabler-adjustments-alt'"
                size="18"
              />
              {{ difference === 0 ? "Confirmar" : "Validar y Ajustar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.2) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.ultra-huge-input-text :deep(input) {
  border: none;
  background: transparent;
  block-size: auto;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 2.5rem !important;
  font-weight: 950 !important;
  inline-size: 100%;
  line-height: 1;
  outline: none;
  text-align: center !important;
}

.ultra-huge-input-text :deep(.v-field__input) {
  padding: 0 !important;
}

.italic {
  font-style: italic;
}
</style>
