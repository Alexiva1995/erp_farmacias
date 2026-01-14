<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, default: null },
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
            <div class="d-flex align-center gap-3 mb-3">
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
            <div class="text-body-2 text-medium-emphasis ms-2">
              Por: <span class="font-weight-medium">{{ product.user?.email }}</span>
            </div>
          </div>

          <VAlert type="info" variant="tonal" class="mb-6">
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
          </VAlert>

          <div class="mb-4">
            <AppTextField
              v-model.number="correctedQuantity"
              label="Cantidad Corregida"
              type="number"
              :error-messages="
                !isValidQuantity ? ['Ingrese una cantidad válida'] : []
              "
              placeholder="Ingresa la cantidad corregida"
            />
          </div>
        </div>
        <div v-else class="text-center py-8">
          <VProgressCircular indeterminate color="primary" />
          <p class="text-body-2 text-medium-emphasis mt-4">Cargando información del producto...</p>
        </div>
      </VCardText>

      <VCardActions class="pa-4 px-6">
        <VRow class="w-100">
          <VCol cols="6">
            <VBtn
              color="secondary"
              variant="outlined"
              block
              @click="handleCancel"
              :disabled="isProcessing || !product"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              color="primary"
              variant="flat"
              block
              @click="handleSubmit"
              :disabled="!canSubmit || !product"
              :loading="isProcessing"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
