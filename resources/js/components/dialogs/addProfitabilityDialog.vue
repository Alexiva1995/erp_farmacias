<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  dialog: { type: Boolean, required: true },
  percentage: { type: Number, default: 0 },
});

const emit = defineEmits(["refresh", "close-modal", "update:dialog"]);

const localPercentage = ref(0);
const loading = ref(false);

// Sincronizar el valor inicial si viene por props
watch(
  () => props.percentage,
  (val) => {
    localPercentage.value = val;
  },
  { immediate: true },
);

async function storeProfitability() {
  const data = {
    default_profitability_percentage: localPercentage.value,
  };

  loading.value = true;
  try {
    await axios.post("/finances/profitability/store", data);
    toast.success(
      "Rentabilidad asignada correctamente. Se actualizaron los precios de venta de los productos no bloqueados.",
    );
    emit("refresh");
    emit("close-modal");
  } catch (error) {
    console.error("Error en la solicitud:", error);
    const message =
      error.response?.data?.message ||
      error.response?.data?.errors?.default_profitability_percentage?.[0] ||
      "Error al asignar rentabilidad.";
    toast.error(message);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <VDialog
    :model-value="props.dialog"
    max-width="500px"
    persistent
    @update:model-value="emit('close-modal')"
  >
    <VCard class="d-flex flex-column">
      <!-- Header -->
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon icon="tabler-percentage" size="24" color="white" class="me-2" />
        <span class="text-h5 font-weight-bold text-white"
          >Asignar rentabilidad</span
        >

        <VSpacer />
        <VBtn
          icon
          variant="text"
          color="white"
          size="small"
          @click="emit('close-modal')"
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="localPercentage"
              label="Porcentaje de Rentabilidad *"
              placeholder="Ej: 25"
              type="number"
              suffix="%"
              autofocus
              variant="outlined"
              density="compact"
            />
            <p class="text-xs text-medium-emphasis mt-2 d-flex align-center">
              <VIcon icon="tabler-info-circle" size="14" class="me-1" />
              Se actualizarán los precios de venta de los productos no
              bloqueados.
            </p>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          :disabled="loading"
          @click="emit('close-modal')"
          class="flex-grow-1"
        >
          Cancelar
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          :loading="loading"
          :disabled="loading || !localPercentage"
          @click="storeProfitability"
          class="flex-grow-1"
        >
          Guardar Rentabilidad
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
