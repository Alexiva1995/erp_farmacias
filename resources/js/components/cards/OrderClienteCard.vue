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

const handleSearch = () => {
  const value = identificationInput.value?.trim();
  if (!value) {
    emit('verify-client', ''); // Para disparar la advertencia
    return;
  }
  
  // Si parece una cotización (por ejemplo, valor numérico pero nosotros decidimos según el backend)
  // Por ahora emitimos un evento unificado o probamos ambos.
  // El usuario dice "que sea cedula o también la cotización".
  emit('identify-and-start', value);
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
  <VCard variant="flat" border class="mb-4 rounded-xl overflow-hidden glass-card shadow-sm">
    <VCardText class="pa-3">
      <VRow v-if="!props.selectedClient || !props.selectedClient.id" align="center" justify="center">
        <VCol cols="12" md="8">
          <div class="d-flex align-center gap-3">
            <AppTextField
              placeholder="Cédula del Cliente o ID de Cotización..."
              clearable
              hide-details
              v-model="identificationInput"
              class="flex-grow-1 font-weight-black custom-input-start"
              prepend-inner-icon="tabler-scan"
              @keyup.enter="handleSearch"
            />
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              class="rounded-lg font-weight-bold px-6 shadow-sm"
              @click="handleSearch"
            >
              <VIcon start icon="tabler-rocket" size="18" />
              EMPEZAR
            </VBtn>
            
            <VBtn
              v-if="showReservedButton"
              icon="tabler-archive"
              color="warning"
              variant="tonal"
              height="44"
              width="44"
              class="rounded-lg"
              @click="reservadaPedido"
            />
          </div>
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
  overflow: hidden;
  background: rgba(var(--v-theme-primary), 0.05);
}

.leading-none {
  line-height: 1 !important;
}

.gap-3 { gap: 12px !important; }

.text-subtitle-1 {
  letter-spacing: 0.5px;
}

:deep(.custom-input-start .v-field__input) {
  font-size: 0.95rem !important;
  padding-block: 8px;
}
</style>
