<script setup>
import axios from '@/plugins/axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true
  },
  product: {
    type: Object,
    default: null
  }
});

const emits = defineEmits(['update:modelValue', 'count-processed']);

const scannedBarcode = ref('');
const barcodeMatched = ref(false);
const countedQuantity = ref(0);
const notes = ref('');
const barcodeError = ref('');
const successMessage = ref('');
const countError = ref('');
const barcodeInput = ref(null);
const MIN_BARCODE_LENGTH = 10;


watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    scannedBarcode.value = '';
    barcodeMatched.value = false;
    countedQuantity.value = props.product?.stock || 0;;
    barcodeError.value = '';
    successMessage.value = '';
    countError.value = '';
    nextTick(() => {
      if (barcodeInput.value) {
        barcodeInput.value.focus();
      }
    });
  }
});

const closeModal = () => {
  emits('update:modelValue', false);
};
const handleBarcodeInput = () => {
    barcodeError.value = '';
    successMessage.value = '';

    if (scannedBarcode.value.length >= MIN_BARCODE_LENGTH) {
        validateBarcode();
    } else {
        barcodeMatched.value = false;
        countedQuantity.value = 0; 
    }
  
};

const validateBarcode = async () => {
    barcodeError.value = '';
    successMessage.value = '';
    countError.value = '';
    if (!props.product || !scannedBarcode.value) {
    return;
  }

  try {
    const response = await axios.post(`/adjustments/${props.product.id}/validate-barcode`, {
        barcode: scannedBarcode.value
    });

    barcodeMatched.value = true;
    successMessage.value = response.data.message;

  } catch (error) {
    barcodeMatched.value = false;
    if (error.response && error.response.data && error.response.data.message) {
      barcodeError.value = error.response.data.message;
    } else {
      barcodeError.value = 'Error al validar el código de barras. Inténtalo de nuevo.';
    }
    console.error('Error al validar código de barras:', error);
  }

};

const processCount = async () => {
  countError.value = '';
  successMessage.value = '';
  if (!props.product || !barcodeMatched.value || countedQuantity.value < 0) {
    countError.value = 'Por favor, valida el producto y proporciona una cantidad contada válida.';
    return;
  }

  try {
    const response = await axios.post('/adjustments/process-count', {
      product_id: props.product.id,
      counted_quantity: countedQuantity.value,
    });
    successMessage.value = response.data.message;

    emits('count-processed', {
      product_id: props.product.id,
      product_updated: response.data.product_updated,
      details: response.data.details
    });

    setTimeout(() => {
      closeModal();
    }, 1500);

  } catch (error) {
    if (error.response && error.response.data && error.response.data.message) {
      countError.value = error.response.data.message;
    } else {
      countError.value = 'Error al procesar el conteo de inventario.';
    }
    console.error('Error al procesar conteo:', error);
  }
};

</script>
<template>
 <VDialog
    :model-value="props.modelValue"
    max-width="500px"
    @update:model-value="$emit('update:modelValue', $event)"
  >
  <VCard>
      <VCardTitle class="d-flex align-center">
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <p class="font-weight-bold text-h6 px-4 pt-4">{{ product?.name }}</p>
      <VCardText>
        <VAlert v-if="barcodeError" type="error" dense dismissible>{{ barcodeError }}</VAlert>
        <VTextField 
        v-model="scannedBarcode" 
        label="Escanear Código de Barras" 
        variant="outlined" 
        :disabled="!product"
          autofocus
          @input="handleBarcodeInput" ref="barcodeInput"
          class="mt-2 mb-4"
        ></VTextField>


        <VTextField
            v-model.number="countedQuantity"
            label="Cantidad Contada"
            type="number"
            :disabled="!barcodeMatched" min="0"
             @keyup.enter="processCount"
        ></VTextField>

      </VCardText>

      <VCardActions class="p-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="closeModal">Cancelar</VBtn>
        <VBtn color="primary" variant="flat" @click="processCount">Guardar Cambios</VBtn>
      </VCardActions>
  </VCard>
  </VDialog>
</template>
