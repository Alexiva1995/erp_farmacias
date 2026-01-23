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
  { immediate: true }
);

async function storeProfitability() {
  const data = {
    default_profitability_percentage: localPercentage.value,
  };

  loading.value = true;
  try {
    await axios.post("/finances/profitability/store", data);
    toast.success("Rentabilidad asignada correctamente. Se actualizaron los precios de venta de los productos no bloqueados.");
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
    @update:model-value="emit('close-modal')"
  >
    <VCard title="Asignar rentabilidad">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="localPercentage"
              label="Porcentaje de Rentabilidad"
              placeholder="Ej: 25"
              type="number"
              suffix="%"
              autofocus
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          :disabled="loading"
          @click="emit('close-modal')"
        >
          Cancelar
        </VBtn>

        <VBtn
          color="primary"
          variant="elevated"
          :loading="loading"
          :disabled="loading"
          @click="storeProfitability"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
