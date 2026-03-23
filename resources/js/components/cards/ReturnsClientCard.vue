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
  <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-2">
        <!-- Título/Icono -->
        <div class="d-flex align-center gap-2 mr-4">
          <VIcon icon="tabler-arrow-back-up" color="primary" size="24" />
          <span class="text-subtitle-2 font-weight-bold text-uppercase d-none d-sm-inline">Devoluciones</span>
        </div>

        <!-- Buscador Principal -->
        <VCol cols="12" sm="5" md="4" lg="4">
          <AppTextField
            placeholder="BUSCAR CLIENTE O N° ORDEN..."
            clearable
            density="compact"
            prepend-inner-icon="tabler-search"
            hide-details
            :model-value="identificationInput"
            @update:model-value="updateIdentification"
            @keyup.enter="handleSearchOrder"
          />
        </VCol>

        <VSpacer />

        <!-- Acciones (Solo Iconos) -->
        <div class="d-flex align-center gap-1">
          <!-- Botón Buscar -->
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="38"
            @click="handleSearchOrder"
          >
            <VIcon icon="tabler-search" size="20" />
            <VTooltip activator="parent" location="top">Buscar Pedidos</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Búsqueda -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="emit('clear-search')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Búsqueda</VTooltip>
          </VBtn>
        </div>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-input-compact :deep(.v-field__input) {
  display: flex !important;
  align-items: center !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-block-size: 38px !important;
  padding-block: 0 !important;
  text-transform: uppercase;
}

.premium-input-compact :deep(.v-field) {
  border-radius: 8px !important;
  min-block-size: 38px !important;
}

.text-xs { font-size: 0.75rem !important; }

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 8%) !important;
}
</style>
