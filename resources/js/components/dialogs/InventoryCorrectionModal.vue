<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue", "correction-processed"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const correctedQuantity = ref(0);
const isProcessing = ref(false);

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue && props.product) {
      correctedQuantity.value = props.product.counted_quantity || 0;
    }
  }
);

const quantityDifference = computed(() => {
  return correctedQuantity.value - (props.product?.counted_quantity || 0);
});

const isIncrement = computed(() => {
  return quantityDifference.value > 0;
});

const isReduction = computed(() => {
  return quantityDifference.value < 0;
});

const inventoryChange = computed(() => {
  const originalQty = props.product?.counted_quantity || 0;
  const correctedQty = correctedQuantity.value;

  if (originalQty < 0 && correctedQty > originalQty) {
    return {
      type: "improvement",
      description:
        correctedQty >= 0
          ? "se corrige a cantidad positiva"
          : "se reduce el faltante",
    };
  }

  if (originalQty < 0 && correctedQty < originalQty) {
    return {
      type: "worsening",
      description: "se incrementa el faltante",
    };
  }

  if (originalQty >= 0 && correctedQty > originalQty) {
    return {
      type: "increment",
      description: "se incrementa la cantidad",
    };
  }

  if (originalQty >= 0 && correctedQty < originalQty) {
    return {
      type: "reduction",
      description: "se reduce la cantidad",
    };
  }

  return {
    type: "no-change",
    description: "sin cambios",
  };
});

const getAlertType = computed(() => {
  switch (inventoryChange.value.type) {
    case "improvement":
    case "increment":
      return "success";
    case "worsening":
    case "reduction":
      return "warning";
    default:
      return "info";
  }
});

const getAlertIcon = computed(() => {
  switch (inventoryChange.value.type) {
    case "improvement":
    case "increment":
      return "tabler-trending-up";
    case "worsening":
    case "reduction":
      return "tabler-trending-down";
    default:
      return "tabler-equal";
  }
});

const isValidQuantity = computed(() => {
  return !isNaN(correctedQuantity.value) && correctedQuantity.value !== null;
});

const canSubmit = computed(() => {
  return isValidQuantity.value && !isProcessing.value;
});

const handleSubmit = () => {
  if (!canSubmit.value) return;

  isProcessing.value = true;

  emit("correction-processed", {
    originalQuantity: props.product.counted_quantity,
    correctedQuantity: correctedQuantity.value,
    difference: quantityDifference.value,
  });

  setTimeout(() => {
    isProcessing.value = false;
  }, 100);
};

const handleCancel = () => {
  isVisible.value = false;
  correctedQuantity.value = 0;
};

const formatQuantity = (quantity) => {
  return new Intl.NumberFormat("es-CO").format(quantity);
};
</script>

<template>
  <VDialog v-model="isVisible" max-width="500" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-6 pb-4">
        <span class="text-h6">Corrección de Cantidad</span>
      </VCardTitle>

      <VCardText class="pa-6 pt-0">
        <div v-if="product">
          <div class="mb-6">
            <div class="d-flex align-center gap-3 mb-4">
              <VAvatar
                v-if="product.product?.photo_url"
                size="48"
                variant="tonal"
                rounded
                :image="product.product.photo_url"
              />
              <div class="flex-grow-1">
                <h6 class="text-h6 mb-1">
                  {{ product.product?.name }}
                </h6>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  {{ product.product?.active_ingredient }}
                </p>
              </div>
            </div>
          </div>

          <VAlert type="info" variant="tonal" class="mb-6">
            <div class="d-flex justify-space-between align-center">
              <div>
                <strong>Cantidad contada original:</strong>
                <span
                  class="ml-2 font-weight-medium"
                  :class="{
                    'text-success': product.counted_quantity > 0,
                    'text-error': product.counted_quantity < 0,
                    'text-warning': product.counted_quantity === 0,
                  }"
                >
                  {{ formatQuantity(product.counted_quantity) }}
                  <span class="text-body-2 text-medium-emphasis">
                    {{
                      product.counted_quantity > 0
                        ? "(exceso)"
                        : product.counted_quantity < 0
                        ? "(faltante)"
                        : "(sin diferencia)"
                    }}
                  </span>
                </span>
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Por: {{ product.user?.email }}
              </div>
            </div>
          </VAlert>

          <div class="mb-4">
            <VTextField
              v-model.number="correctedQuantity"
              label="Cantidad corregida"
              type="number"
              variant="outlined"
              :error="!isValidQuantity"
              :error-messages="
                !isValidQuantity ? ['Ingrese una cantidad válida'] : []
              "
              hint="Ingrese la cantidad correcta para este producto"
              persistent-hint
            >
              <template #prepend-inner>
                <VIcon icon="tabler-hash" />
              </template>
            </VTextField>
          </div>
        </div>
      </VCardText>

      <VCardActions class="pa-6 pt-0">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="handleCancel"
          :disabled="isProcessing"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          @click="handleSubmit"
          :disabled="!canSubmit"
          :loading="isProcessing"
        >
          Aplicar Corrección
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
