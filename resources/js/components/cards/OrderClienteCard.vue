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
  showReservedButton: {
    type: Boolean,
    default: true,
  },
  selectedClient: {
    type: Object,
    default: null,
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
      <VRow v-if="!props.selectedClient || !props.selectedClient.id">
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
            <VCol cols="12" :md="showReservedButton ? 6 : 12">
              <VBtn
                color="success"
                prepend-icon="tabler-search"
                block
                @click="handleRealizarPedido"
              >
                {{ buttonText }}
              </VBtn>
            </VCol>
            <VCol v-if="showReservedButton" cols="12" md="6">
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
      <VRow v-else>
        <VCol cols="12">
          <VAlert icon="tabler-user" color="primary" density="compact">
            {{ props.selectedClient.name }} {{ props.selectedClient.last_name }}
            <span class="text-medium-emphasis ms-2">
              ({{ props.selectedClient.identification_type }}{{ props.selectedClient.identification }})
            </span>
          </VAlert>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
