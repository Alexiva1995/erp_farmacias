<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const props = defineProps({
  startDate: { type: [String, null], default: null },
  endDate: { type: [String, null], default: null },
});

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
  "clear",
  "generated",
]);

const handleManualPayment = async () => {
  const result = await Swal.fire({
    title: "¿Toca pagar el bono de alimentación?",
    text: "El pago será proporcional a los días trabajados si el empleado inició recientemente.",
    icon: "question",
    showDenyButton: true,
    showCancelButton: true,
    confirmButtonText: "Sí, con bono",
    denyButtonText: "No, sin bono",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#3085d6",
    denyButtonColor: "#d33",
    cancelButtonColor: "#6c757d",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const denyButton = Swal.getDenyButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      if (confirmButton) {
        confirmButton.style.flex = "1";
        confirmButton.style.width = "auto";
      }
      if (denyButton) {
        denyButton.style.flex = "1";
        denyButton.style.width = "auto";
      }
      if (cancelButton) {
        cancelButton.style.flex = "1";
        cancelButton.style.width = "auto";
      }
    },
  });

  if (!result.isConfirmed && !result.isDenied) return;

  const payFoodVoucher = result.isConfirmed;

  try {
    const { data } = await axios.post("/finances/payslips", {
      pay_food_voucher: payFoodVoucher,
    });

    toast.success(data.message);
    emit("generated");
    emit("clear");
  } catch (error) {
    toast.error("Error al generar la nómina manual");
    console.error(error);
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
      <VBtn color="primary" @click="handleManualPayment">
        Pagar Nómina Manual
      </VBtn>
    </VCardActions>
  </VCard>
</template>
