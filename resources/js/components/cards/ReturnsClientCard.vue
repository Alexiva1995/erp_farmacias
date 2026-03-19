<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue", "search-order", "clear-search"]);
const identificationInput = ref(props.modelValue);

watch(
  () => props.modelValue,
  (newValue) => {
    identificationInput.value = newValue;
  }
);

const updateIdentification = (value) => {
  identificationInput.value = value;
  emit("update:modelValue", value);
  if (!value) {
    emit("clear-search"); // Emite un evento específico cuando el input se vacía
  }
};

const handleSearchOrder = () => {
  emit("search-order", identificationInput.value);
};
</script>
<template>
  <VCard class="mb-6 elevation-1 border-0 rounded-lg overflow-hidden">
    <VCardText class="pa-4">
      <div class="d-flex align-end gap-3">
        <AppTextField
          label="BÚSQUEDA DE CLIENTE / ORDEN"
          placeholder="INGRESE IDENTIFICACIÓN O N° ORDEN..."
          clearable
          density="compact"
          prepend-inner-icon="tabler-search"
          class="flex-grow-1 premium-input"
          :model-value="identificationInput"
          @update:model-value="updateIdentification"
          @keyup.enter="handleSearchOrder"
        />
        <VBtn
          color="primary"
          variant="flat"
          height="38"
          class="font-weight-black rounded-lg text-xs shadow-sm"
          @click="handleSearchOrder"
        >
          <VIcon icon="tabler-search" class="me-1" size="18" />
          BUSCAR
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-input :deep(.v-field__input) {
  font-size: 0.8rem !important;
  font-weight: 600;
  text-transform: uppercase;
}

.premium-input :deep(.v-label) {
  font-size: 0.7rem !important;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.text-xs { font-size: 0.75rem !important; }

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 8%) !important;
}
</style>
