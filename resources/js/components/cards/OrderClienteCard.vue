<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  buttonText: {
    type: String,
    default: 'Realizar pedido',
  },
  showButton: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['update:modelValue', 'verify-client', 'reserved-order-cliente']);
const identificationInput = ref(props.modelValue);

watch(() => props.modelValue, (newVal) => {
  identificationInput.value = newVal;
}, { immediate: true });

watch(identificationInput, (newVal) => {
  emit('update:modelValue', newVal);
});

const updateIdentification = (value) => {
  emit('update:modelValue', value);
};

const handleRealizarPedido = () => {
  emit('verify-client', identificationInput.value);
};

const reservadaPedido = () => {
  emit('reserved-order-cliente');
};
</script>
<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" :md="showButton ? 9 : 12">
          <AppTextField
            placeholder="Ingrese identificación"
            clearable
            v-model="identificationInput"
            @keyup.enter="handleRealizarPedido"
          />
        </VCol>
        <VCol v-if="showButton" cols="12" md="3">
         


          <VRow>

  <VCol v-if="showButton" cols="6" md="6">
    <VBtn
      color="success"
      prepend-icon="tabler-search"
      block
      @click="handleRealizarPedido"
    >
      {{ buttonText }}
    </VBtn>
  </VCol>

  <VCol v-if="showButton" cols="6" md="6">
    <VBtn
      color="warning"
      prepend-icon="tabler-archive"
      block
      @click="reservadaPedido"
    >
      Reservada
    </VBtn>
  </VCol>
</VRow>

        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
