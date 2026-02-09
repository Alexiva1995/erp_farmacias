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
  buttonsIconOnly: {
    type: Boolean,
    default: false,
  },
  showQuotationInput: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'verify-client', 'reserved-order-cliente', 'load-quotation']);
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

const quotationIdInput = ref('');
const loadQuotation = () => {
  if (quotationIdInput.value?.trim()) {
    emit('load-quotation', quotationIdInput.value.trim());
    quotationIdInput.value = '';
  }
};
</script>
<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow v-if="!props.selectedClient || !props.selectedClient.id">
        <VCol cols="12" :md="props.showQuotationInput ? 6 : 12">
          <div class="d-flex align-center gap-2">
            <AppTextField
              placeholder="Ingrese identificación"
              clearable
              v-model="identificationInput"
              class="flex-grow-1"
              @keyup.enter="handleRealizarPedido"
            />
            <template v-if="showButton">
              <VBtn
                v-if="props.buttonsIconOnly"
                icon
                color="success"
                variant="tonal"
                @click="handleRealizarPedido"
              >
                <VIcon icon="tabler-search" />
              </VBtn>
              <VBtn
                v-else
                color="success"
                prepend-icon="tabler-search"
                variant="tonal"
                @click="handleRealizarPedido"
              >
                {{ buttonText }}
              </VBtn>
              <VBtn
                v-if="showReservedButton"
                v-show="props.buttonsIconOnly"
                icon
                color="warning"
                variant="tonal"
                @click="reservadaPedido"
              >
                <VIcon icon="tabler-archive" />
              </VBtn>
              <VBtn
                v-if="showReservedButton && !props.buttonsIconOnly"
                color="warning"
                prepend-icon="tabler-archive"
                variant="tonal"
                @click="reservadaPedido"
              >
                Reservada
              </VBtn>
            </template>
          </div>
        </VCol>
        <VCol v-if="props.showQuotationInput" cols="12" md="6">
          <AppTextField
            v-model="quotationIdInput"
            placeholder="ID de cotización"
            clearable
            class="flex-grow-1"
            @keyup.enter="loadQuotation"
          >
            <template #append-inner>
              <VBtn
                icon
                variant="text"
                color="primary"
                size="small"
                :disabled="!quotationIdInput?.trim()"
                @click="loadQuotation"
              >
                <VIcon icon="tabler-file-import" />
              </VBtn>
            </template>
          </AppTextField>
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
