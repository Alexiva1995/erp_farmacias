<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'verify-client']);
const identificationInput = ref(props.modelValue);

watch(() => props.modelValue, (newValue) => {
  identificationInput.value = newValue;
});

const updateIdentification = (value) => {
  emit('update:modelValue', value);
};


const handleRealizarPedido = () => {
  emit('verify-client', identificationInput.value);
};
</script>
<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" md="9">
          <AppTextField
            placeholder="Ingrese identificación"
            clearable
            :model-value="identificationInput"
            @update:model-value="updateIdentification"
            @keyup.enter="handleRealizarPedido"
          />
        </VCol>
        <VCol cols="12" md="3">
          <VBtn
            color="success"
            prepend-icon="tabler-plus"
            block
            @click="handleRealizarPedido"
          >
            Realizar pedido
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
