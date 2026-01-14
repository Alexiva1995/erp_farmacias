<script setup>
import { computed, nextTick, ref, watch } from "vue";
import Swal from "sweetalert2";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue", "save"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const barcodeInput = ref("");
const countedQuantity = ref("");
const isScanning = ref(false);
const barcodeError = ref("");
const allowWithoutBarcode = ref(false);

const canSave = computed(() => {
  const isQuantityValid =
    countedQuantity.value !== "" && !isNaN(Number(countedQuantity.value)) && Number(countedQuantity.value) >= 0;
  
  // Si se permite sin código de barras, solo necesita cantidad válida
  if (allowWithoutBarcode.value) {
    return isQuantityValid;
  }
  
  // Si no, requiere código de barras válido
  return (
    barcodeInput.value.trim() !== "" && isQuantityValid && !barcodeError.value
  );
});

const resetForm = () => {
  barcodeInput.value = "";
  countedQuantity.value = "";
  barcodeError.value = "";
  isScanning.value = false;
  allowWithoutBarcode.value = false;
};

const handleCancel = () => {
  isVisible.value = false;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      nextTick(() => {
        const barcodeInputElement = document.querySelector("#barcode-input");
        if (barcodeInputElement) {
          barcodeInputElement.focus();
        }
      });
    } else {
      resetForm();
    }
  }
);

watch(barcodeInput, (newBarcode) => {
  if (!newBarcode.trim()) {
    barcodeError.value = "";
    return;
  }

  // Si se permite sin código de barras, no validar
  if (allowWithoutBarcode.value) {
    barcodeError.value = "";
    return;
  }

  if (props.product.barcode && newBarcode.trim() !== props.product.barcode) {
    barcodeError.value = "El código de barras no coincide con este producto";
  } else {
    barcodeError.value = "";
  }
});

watch(allowWithoutBarcode, (newValue) => {
  if (newValue) {
    barcodeError.value = "";
    barcodeInput.value = "";
    // Enfocar el campo de cantidad cuando se activa el checkbox
    nextTick(() => {
      const quantityInput = document.querySelector("#quantity-input");
      if (quantityInput) {
        quantityInput.focus();
      }
    });
  }
});

const handleBarcodeEnter = () => {
  if (!barcodeError.value && barcodeInput.value.trim()) {
    nextTick(() => {
      const quantityInput = document.querySelector("#quantity-input");
      if (quantityInput) {
        quantityInput.focus();
      }
    });
  }
};

const handleSave = async () => {
  if (!canSave.value) return;

  const quantity = Number(countedQuantity.value);

  const result = await Swal.fire({
    title: "Confirmar Conteo",
    text: `Confirma que está contando la cantidad de ${quantity} ${quantity === 1 ? 'unidad' : 'unidades'}`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Confirmar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  });

  if (!result.isConfirmed) return;

  const countData = {
    barcode: allowWithoutBarcode.value ? null : barcodeInput.value.trim(),
    countedQuantity: quantity,
    allowWithoutBarcode: allowWithoutBarcode.value,
  };

  emit("save", countData);
};

const startScanning = () => {
  isScanning.value = true;
  setTimeout(() => {
    isScanning.value = false;
    if (props.product.barcode) {
      barcodeInput.value = props.product.barcode;
    }
  }, 2000);
};
</script>

<template>
  <VDialog v-model="isVisible" max-width="500" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-6 pb-4 bg-primary">
        <VIcon icon="tabler-scan" size="24" color="white" />
        <span class="text-h6 text-white">Conteo de Inventario</span>
        <VSpacer />
        <VBtn icon variant="text" color="white" size="small" @click="handleCancel">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div class="mb-6">
          <div class="d-flex flex-column gap-2">
            <span class="text-h6 font-weight-medium">{{ product.name }}</span>
            <span class="text-body-2 text-disabled">{{
              product.active_ingredient
            }}</span>
            <span
              v-if="product.laboratory"
              class="text-body-2 text-medium-emphasis"
            >
              {{ product.laboratory.name }}
            </span>
          </div>
        </div>

        <VForm @submit.prevent="handleSave">
          <div class="mb-4">
            <VCard variant="tonal" color="info" class="pa-3 mb-4">
              <VCheckbox
                v-model="allowWithoutBarcode"
                color="primary"
                hide-details
              >
                <template #label>
                  <div class="d-flex align-center gap-2">
                    <VIcon icon="tabler-barcode-off" size="20" color="primary" />
                    <span class="text-body-2 font-weight-medium">
                      Permitir conteo sin código de barras
                    </span>
                  </div>
                </template>
              </VCheckbox>
              <div v-if="allowWithoutBarcode" class="text-caption text-medium-emphasis mt-2 ms-8">
                <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                Los productos sin código de barras podrán ser contados directamente
              </div>
            </VCard>
            
            <AppTextField
              id="barcode-input"
              v-model="barcodeInput"
              label="Código de Barras"
              placeholder="Escanea o ingresa el código de barras"
              :error-messages="barcodeError"
              :disabled="allowWithoutBarcode"
              @keyup.enter="handleBarcodeEnter"
            >
              <template #append-inner>
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  color="primary"
                  :loading="isScanning"
                  :disabled="allowWithoutBarcode"
                  @click="startScanning"
                >
                  <VIcon icon="tabler-camera" />
                </VBtn>
              </template>
            </AppTextField>
          </div>

          <div class="mb-4">
            <AppTextField
              id="quantity-input"
              v-model="countedQuantity"
              label="Cantidad Contada"
              type="number"
              min="0"
              placeholder="Ingresa la cantidad contada"
              :disabled="!allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
            />
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6">
        <VRow class="w-100">
          <VCol cols="6">
            <VBtn
              color="secondary"
              variant="outlined"
              block
              @click="handleCancel"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              color="primary"
              variant="flat"
              block
              :disabled="!canSave"
              @click="handleSave"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
