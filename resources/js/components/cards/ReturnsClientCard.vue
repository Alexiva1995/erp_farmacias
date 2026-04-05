<script setup>
// Buscador de clientes/órdenes para devoluciones
import AppFilterBase from "@/components/AppFilterBase.vue";
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
  },
);

const updateIdentification = (value) => {
  identificationInput.value = value;
  emit("update:modelValue", value);
  if (!value) {
    emit("clear-search");
  }
};

const handleSearchOrder = () => {
  emit("search-order", identificationInput.value);
};
</script>

<template>
  <AppFilterBase
    :search="identificationInput"
    :show-advanced="false"
    search-placeholder="Buscar por cliente o n° orden..."
    class="py-1"
    @update:search="updateIdentification"
    @clear="emit('clear-search')"
  >
    <template #search-append>
      <VBtn
        icon
        variant="tonal"
        color="primary"
        size="38"
        class="rounded-circle shadow-sm"
        @click="handleSearchOrder"
      >
        <VIcon icon="tabler-search" size="20" />
        <VTooltip activator="parent" location="top">Buscar Pedidos</VTooltip>
      </VBtn>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 8%) !important;
}
</style>
