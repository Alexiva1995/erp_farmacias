<script setup>
import axios from "@/plugins/axios";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  countRecord: { type: Object, default: null }, // El registro de conteo pendiente completo
});

const emit = defineEmits(["update:modelValue", "verify-no-discrepancy", "verify-with-discrepancy"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const isLoading = ref(false);
const isProcessing = ref(false);
const currentStock = ref(null); // Suma real de lotes del producto
const newCountedQuantity = ref(null);
const loadError = ref(null);

// Cargar stock real cuando se abre el modal
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
    loadError.value = "No se pudo cargar el stock actual del producto.";
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
  if (difference.value === null) return "default";
  if (difference.value === 0) return "success";
  return difference.value > 0 ? "info" : "error";
});

const differenceIcon = computed(() => {
  if (difference.value === null) return "tabler-minus";
  if (difference.value === 0) return "tabler-circle-check";
  return difference.value > 0 ? "tabler-arrow-up-circle" : "tabler-arrow-down-circle";
});

const differenceText = computed(() => {
  if (difference.value === null) return "—";
  if (difference.value === 0) return "Sin diferencia";
  const abs = Math.abs(difference.value);
  return difference.value > 0 ? `Sobran ${abs} unidades` : `Faltan ${abs} unidades`;
});

const canVerify = computed(() => {
  return newCountedQuantity.value !== null && newCountedQuantity.value >= 0 && !isProcessing.value && !isLoading.value;
});

const handleVerify = () => {
  if (!canVerify.value) return;
  isProcessing.value = true;

  if (difference.value === 0) {
    // Sin discrepancia → aprobar con ajuste 0
    emit("verify-no-discrepancy", { countRecord: props.countRecord });
  } else {
    // Con discrepancia → abrir modal de lotes con la cantidad contada
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

const formatNumber = (n) => (n !== null && n !== undefined ? new Intl.NumberFormat("es-CO").format(n) : "—");
</script>

<template>
  <VDialog v-model="isVisible" max-width="520" persistent>
    <VCard>
      <!-- Header -->
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary flex-grow-0">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-clipboard-check" size="22" color="white" />
          <span class="text-h6 text-white">Verificar Conteo</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="handleClose">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-6">
        <div v-if="!countRecord" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <div v-else>
          <!-- Producto -->
          <div class="d-flex align-center gap-3 mb-5">
            <VAvatar
              v-if="countRecord.product?.photo_url"
              size="48"
              variant="tonal"
              color="primary"
              rounded
              :image="countRecord.product.photo_url"
            />
            <VAvatar v-else size="48" variant="tonal" color="primary" rounded>
              <VIcon icon="tabler-pill" size="24" />
            </VAvatar>
            <div>
              <p class="text-h6 font-weight-semibold mb-0">{{ countRecord.product?.name }}</p>
              <p class="text-caption text-medium-emphasis mb-0">
                Contado por: {{ countRecord.user?.employee_name || countRecord.user?.email || "—" }}
              </p>
            </div>
          </div>

          <!-- Cards de stock -->
          <VRow class="mb-5" dense>
            <VCol cols="6">
              <VCard variant="tonal" color="primary" class="pa-3 rounded-lg h-100">
                <div class="d-flex align-center gap-2 mb-1">
                  <VIcon icon="tabler-database" size="18" />
                  <span class="text-caption text-medium-emphasis">Stock actual en sistema</span>
                </div>
                <div v-if="isLoading" class="d-flex align-center gap-2">
                  <VProgressCircular indeterminate size="20" width="2" />
                  <span class="text-caption">Cargando...</span>
                </div>
                <p v-else class="text-h5 font-weight-bold mb-0">{{ formatNumber(currentStock) }}</p>
              </VCard>
            </VCol>
            <VCol cols="6">
              <VCard variant="tonal" color="warning" class="pa-3 rounded-lg h-100">
                <div class="d-flex align-center gap-2 mb-1">
                  <VIcon icon="tabler-scan" size="18" />
                  <span class="text-caption text-medium-emphasis">Lo que contó el usuario</span>
                </div>
                <p class="text-h5 font-weight-bold mb-0">
                  {{ formatNumber(countRecord.system_quantity + countRecord.discrepancy) }}
                </p>
                <p class="text-caption text-medium-emphasis mb-0">
                  Diferencia registrada:
                  <strong :class="countRecord.discrepancy > 0 ? 'text-success' : 'text-error'">
                    {{ countRecord.discrepancy > 0 ? "+" : "" }}{{ countRecord.discrepancy }}
                  </strong>
                </p>
              </VCard>
            </VCol>
          </VRow>

          <VDivider class="mb-5" />

          <!-- Campo de reconteo -->
          <p class="text-body-1 font-weight-medium mb-2">
            ¿Cuántas unidades hay físicamente ahora?
          </p>
          <p class="text-caption text-medium-emphasis mb-3">
            Cuente físicamente el producto y confirme la cantidad. Si coincide con el sistema no se hará ningún ajuste.
          </p>

          <AppTextField
            v-model.number="newCountedQuantity"
            label="Cantidad contada físicamente"
            type="number"
            min="0"
            placeholder="Ingrese la cantidad"
            prepend-inner-icon="tabler-calculator"
            autofocus
          />

          <!-- Resultado en tiempo real -->
          <VAlert
            v-if="difference !== null"
            :type="difference === 0 ? 'success' : difference > 0 ? 'info' : 'warning'"
            variant="tonal"
            class="mt-4"
          >
            <div class="d-flex align-center gap-2">
              <VIcon :icon="differenceIcon" size="20" />
              <div>
                <strong>{{ differenceText }}</strong>
                <span v-if="difference !== 0" class="d-block text-caption mt-1">
                  Se abrirá el ajuste de lotes para aplicar la corrección de
                  <strong>{{ Math.abs(difference) }} unidades</strong>.
                </span>
                <span v-else class="d-block text-caption mt-1">
                  Se registrará un movimiento de verificación sin ajuste al inventario.
                </span>
              </div>
            </div>
          </VAlert>

          <VAlert
            v-if="loadError"
            type="error"
            variant="tonal"
            class="mt-4"
          >
            {{ loadError }}
          </VAlert>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VBtn
          color="secondary"
          variant="outlined"
          prepend-icon="tabler-x"
          @click="handleClose"
          :disabled="isProcessing"
          class="flex-grow-1"
        >
          Cancelar
        </VBtn>
        <VBtn
          :color="difference === 0 ? 'success' : 'primary'"
          variant="flat"
          :prepend-icon="difference === 0 ? 'tabler-circle-check' : 'tabler-adjustments'"
          :disabled="!canVerify"
          :loading="isProcessing"
          @click="handleVerify"
          class="flex-grow-1"
        >
          {{ difference === 0 ? "Sin ajuste" : "Ajustar lotes" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
