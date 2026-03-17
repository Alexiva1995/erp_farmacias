<script setup>
import { computed, defineProps, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  buttonText: { type: String, default: 'Realizar pedido' },
  showButton: { type: Boolean, default: true },
  showReservedButton: { type: Boolean, default: true },
  selectedClient: { type: Object, default: null },
  buttonsIconOnly: { type: Boolean, default: false },
  showQuotationInput: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'verify-client', 'reserved-order-cliente', 'load-quotation']);
const identificationInput = ref(props.modelValue);

watch(() => props.modelValue, (newVal) => {
  identificationInput.value = newVal;
}, { immediate: true });

watch(identificationInput, (newVal) => {
  emit('update:modelValue', newVal);
});

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
  <VCard variant="flat" border class="mb-6 rounded-xl overflow-hidden glass-card shadow-sm">
    <VCardText class="pa-5">
      <VRow v-if="!props.selectedClient || !props.selectedClient.id" align="center">
        <VCol cols="12" :md="props.showQuotationInput ? 6 : 12">
          <div class="d-flex align-center gap-3">
            <AppTextField
              placeholder="Identificación del cliente..."
              clearable
              hide-details
              v-model="identificationInput"
              class="flex-grow-1 font-weight-black"
              prepend-inner-icon="tabler-id"
              @keyup.enter="handleRealizarPedido"
            />
            <template v-if="showButton">
              <VBtn
                v-if="props.buttonsIconOnly"
                icon="tabler-search"
                color="success"
                variant="tonal"
                class="rounded-lg"
                @click="handleRealizarPedido"
              />
              <VBtn
                v-else
                color="success"
                prepend-icon="tabler-search"
                variant="tonal"
                class="rounded-lg font-weight-black"
                @click="handleRealizarPedido"
              >
                {{ buttonText }}
              </VBtn>
              
              <VBtn
                v-if="showReservedButton"
                :icon="props.buttonsIconOnly ? 'tabler-archive' : undefined"
                :color="props.buttonsIconOnly ? 'warning' : 'warning'"
                variant="tonal"
                class="rounded-lg font-weight-black"
                @click="reservadaPedido"
              >
                <VIcon v-if="props.buttonsIconOnly" icon="tabler-archive" />
                <template v-else>
                  <VIcon start icon="tabler-archive" />
                  Reservada
                </template>
              </VBtn>
            </template>
          </div>
        </VCol>
        
        <VCol v-if="props.showQuotationInput" cols="12" md="6">
          <AppTextField
            v-model="quotationIdInput"
            placeholder="ID de cotización..."
            clearable
            hide-details
            class="flex-grow-1 font-weight-black"
            prepend-inner-icon="tabler-file-invoice"
            @keyup.enter="loadQuotation"
          >
            <template #append-inner>
              <VBtn
                icon="tabler-file-import"
                variant="text"
                color="primary"
                size="small"
                :disabled="!quotationIdInput?.trim()"
                @click="loadQuotation"
              />
            </template>
          </AppTextField>
        </VCol>
      </VRow>

      <VRow v-else no-gutters>
        <VCol cols="12">
          <div class="client-alert-premium d-flex align-center pa-4 rounded-xl border border-primary-lighten-4">
            <VAvatar color="primary" variant="tonal" size="48" class="me-4 rounded-lg">
              <VIcon icon="tabler-user" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-subtitle-1 font-weight-950 text-primary uppercase leading-none">
                {{ props.selectedClient.name }} {{ props.selectedClient.last_name }}
              </span>
              <span class="text-caption font-weight-bold text-medium-emphasis uppercase mt-1">
                {{ props.selectedClient.identification_type }} {{ props.selectedClient.identification }}
              </span>
            </div>
            <VSpacer />
            <VChip color="primary" variant="flat" size="small" class="font-weight-black px-4 shadow-sm">
              CLIENTE VERIFICADO
            </VChip>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.glass-card {
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 80%) !important;
}

.client-alert-premium {
  position: relative;
  background: rgba(var(--v-theme-primary), 0.05);
  overflow: hidden;
}

.leading-none {
  line-height: 1 !important;
}

.gap-3 { gap: 12px !important; }

.text-subtitle-1 {
  letter-spacing: 0.5px;
}
</style>
