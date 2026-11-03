<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { reactive, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  report: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const isSaving = ref(false);
const autoRecalculate = ref(true);

const form = reactive({
  first_invoice_number: "",
  last_invoice_number: "",
  recalculate: true,
  exempt_amount: 0,
  base_16_amount: 0,
  iva_amount: 0,
  igtf_amount: 0,
  total_amount: 0,
});

watch(
  () => props.report,
  (newReport) => {
    if (newReport) {
      form.first_invoice_number = newReport.first_invoice_number || "";
      form.last_invoice_number = newReport.last_invoice_number || "";
      form.exempt_amount = Number(newReport.exempt_amount) || 0;
      form.base_16_amount = Number(newReport.base_16_amount) || 0;
      form.iva_amount = Number(newReport.iva_amount) || 0;
      form.igtf_amount = Number(newReport.igtf_amount) || 0;
      form.total_amount = Number(newReport.total_amount) || 0;
      autoRecalculate.value = true;
      form.recalculate = true;
    }
  },
  { immediate: true }
);

const handleSave = async () => {
  if (!props.report?.id) return;

  if (!form.first_invoice_number || !form.last_invoice_number) {
    toast.error("Debes indicar la factura inicial y la factura final.");
    return;
  }

  isSaving.value = true;
  form.recalculate = autoRecalculate.value;

  try {
    const response = await axios.put(`/fiscal/z-reports/${props.report.id}/range`, form);
    const updated = response.data?.data;
    toast.success("Rango de facturas actualizado y Libro de Ventas recalculado.");
    emit("saved", updated);
    emit("update:modelValue", false);
  } catch (err) {
    console.error("Error al actualizar rango de Reporte Z:", err);
    toast.error(err.response?.data?.message || "No se pudo actualizar el rango del Reporte Z.");
  } finally {
    isSaving.value = false;
  }
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-xl overflow-hidden">
      <!-- Encabezado con Icono -->
      <VCardTitle class="d-flex align-center justify-space-between pa-4 bg-primary text-white">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-adjustments-alt" size="24" color="white" />
          <span class="text-h6 font-weight-bold text-white">
            Editar Rango de Facturas (Z {{ props.report?.report_number }})
          </span>
        </div>
        <VBtn icon="tabler-x" variant="text" color="white" size="small" @click="emit('update:modelValue', false)" />
      </VCardTitle>

      <VCardText class="pa-4 pt-5">
        <VAlert variant="tonal" color="info" density="compact" class="mb-4 rounded-lg">
          <div class="text-caption">
            Al guardar, el sistema consultará las ventas fiscales emitidas en este rango, recalculará los montos del Reporte Z y actualizará automáticamente la fila vinculada en el <strong>Libro de Ventas</strong> abierto.
          </div>
        </VAlert>

        <VRow density="comfortable">
          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.first_invoice_number"
              label="Factura Inicial"
              placeholder="Ej. 14584"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-arrow-bar-to-right"
              required
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.last_invoice_number"
              label="Factura Final"
              placeholder="Ej. 14592"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-arrow-bar-to-left"
              required
            />
          </VCol>

          <VCol cols="12">
            <VSwitch
              v-model="autoRecalculate"
              color="primary"
              label="Recalcular montos automáticamente según las facturas del rango"
              density="compact"
              hide-details
            />
          </VCol>

          <!-- Montos Manuales si no recalcula automáticamente -->
          <template v-if="!autoRecalculate">
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.exempt_amount"
                type="number"
                step="0.01"
                label="Ventas Exentas"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.base_16_amount"
                type="number"
                step="0.01"
                label="Base Imponible (16%)"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.iva_amount"
                type="number"
                step="0.01"
                label="IVA (16%)"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.igtf_amount"
                type="number"
                step="0.01"
                label="IGTF (3%)"
                variant="outlined"
                density="compact"
              />
            </VCol>
          </template>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4 d-flex justify-end gap-2 bg-background">
        <VBtn variant="text" color="secondary" @click="emit('update:modelValue', false)">
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :loading="isSaving"
          prepend-icon="tabler-check"
          @click="handleSave"
        >
          Guardar y Recalcular
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
