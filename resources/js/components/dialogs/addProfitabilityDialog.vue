<script setup>
import axios from "@/plugins/axios";
import { ref, watch } from "vue";

const props = defineProps({
  dialog: { type: Boolean, required: true },
  percentage: { type: Number, default: 0 },
});

const emit = defineEmits(["refresh", "close-modal", "update:dialog"]);

const localPercentage = ref(0);

// Sincronizar el valor inicial si viene por props
watch(
  () => props.percentage,
  (val) => {
    localPercentage.value = val;
  },
  { immediate: true }
);

async function storeProfitability() {
  let data = {
    default_profitability_percentage: localPercentage.value,
  };

  console.log(data);

  try {
    const response = await axios.post("/finances/profitability/store", data);

    console.log("Éxito:", response.data);
    emit("refresh");
    emit("close-modal");
  } catch (error) {
    console.error("Error en la solicitud:", error);
    if (error.response) {
      console.error("Datos del error:", error.response.data);
    }
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
        <VBtn color="secondary" variant="outlined" @click="emit('close-modal')">
          Cancelar
        </VBtn>

        <VBtn color="primary" variant="elevated" @click="storeProfitability">
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
