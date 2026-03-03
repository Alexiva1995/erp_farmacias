<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref } from "vue";

const props = defineProps({
  startDate: { type: [String, null], default: null },
  endDate: { type: [String, null], default: null },
});

const isGenerating = ref(false);

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
  "clear",
  "generated",
]);

const handleManualPayment = async () => {
  isGenerating.value = true;
  try {
    const { data } = await axios.post("/finances/payslips");

    toast.success(data.message);
    emit("generated");
    emit("clear");
  } catch (error) {
    toast.error("Error al generar la nómina manual");
    console.error(error);
  } finally {
    isGenerating.value = false;
  }
};
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" md="6">
          <AppDateTimePicker
            :model-value="props.startDate"
            label="Desde"
            placeholder="Fecha Desde"
            clearable
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol cols="12" md="6">
          <AppDateTimePicker
            :model-value="props.endDate"
            label="Hasta"
            placeholder="Fecha Hasta"
            clearable
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
      <VBtn 
        color="primary" 
        :loading="isGenerating"
        :disabled="isGenerating"
        @click="handleManualPayment"
      >
        Pagar Nómina Manual
      </VBtn>
    </VCardActions>
  </VCard>
</template>
