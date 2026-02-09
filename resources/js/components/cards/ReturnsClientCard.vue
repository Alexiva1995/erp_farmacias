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
  <VCard class="mb-6">
    <VCardText>
      <div class="d-flex align-center gap-2">
        <AppTextField
          placeholder="Ingrese identificación, N° Orden"
          clearable
          class="flex-grow-1"
          :model-value="identificationInput"
          @update:model-value="updateIdentification"
          @keyup.enter="handleSearchOrder"
        />
        <VBtn
          icon
          color="success"
          variant="tonal"
          @click="handleSearchOrder"
        >
          <VIcon icon="tabler-search" />
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>
