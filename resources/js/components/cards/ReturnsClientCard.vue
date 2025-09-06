<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue', 'search-order']);
const identificationInput = ref(props.modelValue);

watch(() => props.modelValue, (newValue) => {
  identificationInput.value = newValue;
});

const updateIdentification = (value) => {
  emit('update:modelValue', value);
};

const handleSearchOrder = () => {
  emit('search-order', identificationInput.value);
};

</script>
<template>
 <VCard class="mb-6">
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
      <VBtn color="primary" @click="handleSearchOrder">
        Buscar pedido
      </VBtn>
    </VCardActions>
  </VCard>
</template>
