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
  <VCard title="No hay orden en proceso" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12">
          <AppTextField
            placeholder="Ingrese identificación"
            clearable
            class="flex-grow-1"
            :model-value="identificationInput"
            @update:model-value="updateIdentification"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VSpacer />
      <VBtn color="primary" prepend-icon="tabler-plus" @click="handleRealizarPedido">
        Realizar pedido
      </VBtn>
    </VCardActions>
  </VCard>
</template>
