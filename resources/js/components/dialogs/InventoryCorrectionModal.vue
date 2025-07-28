<!-- InventoryCorrectionModal.vue -->
<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue", "correction-processed"]);

// Estado del modal
const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

// Estado del formulario
const correctedQuantity = ref(0);
const isProcessing = ref(false);

// Resetear el formulario cuando se abre el modal
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue && props.product) {
      // Inicializar con la cantidad contada actual
      correctedQuantity.value = props.product.counted_quantity || 0;
    }
  }
);

// Calcular la diferencia
const quantityDifference = computed(() => {
  return correctedQuantity.value - (props.product?.counted_quantity || 0);
});

// Determinar si es incremento o reducción basado en la diferencia real
const isIncrement = computed(() => {
  return quantityDifference.value > 0;
});

const isReduction = computed(() => {
  return quantityDifference.value < 0;
});

// Determinar el tipo de cambio en el inventario
const inventoryChange = computed(() => {
  const originalQty = props.product?.counted_quantity || 0;
  const correctedQty = correctedQuantity.value;

  // Si la cantidad original era negativa y la corregida es menos negativa o positiva
  if (originalQty < 0 && correctedQty > originalQty) {
    return {
      type: "improvement",
      description:
        correctedQty >= 0
          ? "se corrige a cantidad positiva"
          : "se reduce el faltante",
    };
  }

  // Si la cantidad original era negativa y la corregida es más negativa
  if (originalQty < 0 && correctedQty < originalQty) {
    return {
      type: "worsening",
      description: "se incrementa el faltante",
    };
  }

  // Si la cantidad original era positiva y la corregida es mayor
  if (originalQty >= 0 && correctedQty > originalQty) {
    return {
      type: "increment",
      description: "se incrementa la cantidad",
    };
  }

  // Si la cantidad original era positiva y la corregida es menor
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

// Obtener el color del alert basado en el tipo de cambio
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

// Obtener el ícono apropiado
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

// Validaciones
const isValidQuantity = computed(() => {
  return !isNaN(correctedQuantity.value) && correctedQuantity.value !== null;
});

const canSubmit = computed(() => {
  return isValidQuantity.value && !isProcessing.value;
});

// Métodos
const handleSubmit = () => {
  if (!canSubmit.value) return;

  isProcessing.value = true;

  emit("correction-processed", {
    originalQuantity: props.product.counted_quantity,
    correctedQuantity: correctedQuantity.value,
    difference: quantityDifference.value,
  });

  // El componente padre manejará el loading, así que lo reseteamos después de emitir
  setTimeout(() => {
    isProcessing.value = false;
  }, 100);
};

const handleCancel = () => {
  isVisible.value = false;
  correctedQuantity.value = 0;
};

// Formatear números para mostrar
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
          <!-- Información del producto -->
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

          <!-- Información del conteo -->
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

          <!-- Input para corrección -->
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

          <!-- Mostrar diferencia si hay cambios
          <div v-if="quantityDifference !== 0" class="mb-4">
            <VAlert :type="getAlertType" variant="tonal">
              <div class="d-flex align-center gap-2">
                <VIcon :icon="getAlertIcon" />
                <div class="flex-grow-1">
                  <div class="mb-1">
                    <strong>Cambio:</strong>
                    {{ inventoryChange.description }}
                  </div>
                  <div class="text-body-2">
                    <span class="text-medium-emphasis">Diferencia:</span>
                    <span
                      :class="{
                        'text-success': quantityDifference > 0,
                        'text-error': quantityDifference < 0,
                      }"
                      class="font-weight-medium ml-1"
                    >
                      {{ quantityDifference > 0 ? "+" : ""
                      }}{{ formatQuantity(quantityDifference) }}
                    </span>
                    {{
                      Math.abs(quantityDifference) === 1 ? "unidad" : "unidades"
                    }}
                  </div>
                </div>
              </div>
            </VAlert>
          </div> -->
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
