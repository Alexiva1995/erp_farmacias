<script setup>
import { computed, ref, watch } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  invoice: { type: Object, default: null },
  invoices: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "generated"]);

const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const checkingNumber = ref(false);
const submitting = ref(false);
const isFirstIslr = ref(false);
const correlativeNumber = ref("");
const manualNumber = ref("");

const form = ref({
  concept_description: "SERVICIOS",
  retention_percentage: 2.0,
  subtrahend: 0.0,
  date: new Date().toISOString().substring(0, 10),
});

const activeInvoices = computed(() => {
  if (props.invoice) return [props.invoice];
  if (Array.isArray(props.invoices) && props.invoices.length > 0) return props.invoices;
  return [];
});

const supplierName = computed(() => {
  const first = activeInvoices.value[0];
  return first?.supplier?.name || first?.supplier?.social_reason || "Proveedor";
});

const totalTaxableBase = computed(() => {
  return activeInvoices.value.reduce((acc, inv) => acc + (Number(inv.taxable_base) || 0), 0);
});

const calculatedWithheld = computed(() => {
  const base = totalTaxableBase.value;
  const perc = Number(form.value.retention_percentage) || 0;
  const sub = Number(form.value.subtrahend) || 0;
  const val = (base * (perc / 100)) - sub;
  return val > 0 ? val : 0;
});

const formatCurrency = (val) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val || 0);
};

const checkNextNumber = async () => {
  checkingNumber.value = true;
  try {
    const res = await axios.get("/retentions/islr/next-number");
    const data = res.data?.data || {};
    isFirstIslr.value = Boolean(data.is_first);
    correlativeNumber.value = data.next_number || "";
    if (isFirstIslr.value) {
      manualNumber.value = "";
    } else {
      manualNumber.value = correlativeNumber.value;
    }
  } catch (e) {
    console.error("Error al consultar correlativo ISLR:", e);
    isFirstIslr.value = true;
    manualNumber.value = "";
  } finally {
    checkingNumber.value = false;
  }
};

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      form.value.concept_description = "SERVICIOS";
      form.value.retention_percentage = 2.0;
      form.value.subtrahend = 0.0;
      form.value.date = new Date().toISOString().substring(0, 10);
      checkNextNumber();
    }
  }
);

const handleGenerate = async () => {
  if (!form.value.concept_description || form.value.concept_description.trim() === "") {
    toast.error("Debe ingresar el concepto o descripción de la retención.");
    return;
  }

  if (isFirstIslr.value && (!manualNumber.value || manualNumber.value.trim() === "")) {
    toast.error("Por ser el primer comprobante ISLR, debe especificar el número de comprobante inicial.");
    return;
  }

  const ids = activeInvoices.value.map((i) => i.id);
  if (ids.length === 0) {
    toast.error("No hay facturas seleccionadas para la retención.");
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      invoice_ids: ids,
      concept_description: form.value.concept_description.trim(),
      retention_percentage: Number(form.value.retention_percentage),
      subtrahend: Number(form.value.subtrahend) || 0,
      date: form.value.date,
      starting_number: manualNumber.value ? manualNumber.value.trim() : null,
    };

    const res = await axios.post("/retentions/islr/generate", payload);
    toast.success(res.data?.message || "Retención ISLR generada con éxito.");
    isOpen.value = false;
    emit("generated", res.data?.retention_id);
  } catch (error) {
    console.error("Error al generar retención ISLR:", error);
    toast.error(error.response?.data?.message || "Error al procesar la retención de ISLR.");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <VDialog v-model="isOpen" max-width="640" persistent>
    <VCard class="rounded-lg">
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar color="warning" variant="tonal" rounded size="40" class="me-2">
            <VIcon icon="tabler-receipt-tax" size="24" />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          Generar Comprobante de Retención ISLR
        </VCardTitle>
        <VCardSubtitle class="text-caption">
          {{ supplierName }} — {{ activeInvoices.length }} factura(s) seleccionada(s)
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <div v-if="checkingNumber" class="text-center py-6">
          <VProgressCircular indeterminate color="primary" />
          <div class="text-caption mt-2">Consultando correlativo fiscal...</div>
        </div>

        <div v-else class="d-flex flex-column gap-3">
          <!-- Alerta de Primera Retención -->
          <VAlert
            v-if="isFirstIslr"
            color="info"
            variant="tonal"
            icon="tabler-info-circle"
            density="compact"
            class="text-caption"
          >
            <strong>Primer Comprobante de ISLR:</strong> No se encontraron comprobantes previos en el sistema. Por favor ingrese el número inicial (ej: <code>0000000144</code>). Los siguientes comprobantes serán correlativos automáticos.
          </VAlert>

          <VRow density="compact">
            <!-- N° Comprobante -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model="manualNumber"
                label="N° de Comprobante *"
                placeholder="Ej: 0000000144"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-hash"
                :hint="isFirstIslr ? 'Requerido para iniciar la secuencia' : 'Consecutivo autocalculado'"
                persistent-hint
              />
            </VCol>

            <!-- Fecha de Emisión -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model="form.date"
                label="Fecha de Emisión *"
                type="date"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-calendar"
              />
            </VCol>

            <!-- Concepto / Descripción -->
            <VCol cols="12">
              <VTextField
                v-model="form.concept_description"
                label="Concepto / Descripción *"
                placeholder="Ej: SERVICIOS, HONORARIOS, FLETES..."
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-file-description"
              />
            </VCol>

            <!-- % Retención -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.retention_percentage"
                label="% Retención *"
                type="number"
                step="0.01"
                min="0"
                max="100"
                suffix="%"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-percentage"
              />
            </VCol>

            <!-- Sustraendo -->
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.subtrahend"
                label="Sustraendo (Bs)"
                type="number"
                step="0.01"
                min="0"
                variant="outlined"
                density="compact"
                prepend-inner-icon="tabler-minus"
              />
            </VCol>
          </VRow>

          <!-- Tarjeta de Resumen de Cálculo -->
          <VCard variant="tonal" color="warning" class="pa-3 rounded-lg mt-2">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">Base Imponible Total:</span>
              <span class="text-subtitle-2 font-weight-bold">Bs {{ formatCurrency(totalTaxableBase) }}</span>
            </div>
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">Tasa Aplicada:</span>
              <span class="text-subtitle-2 font-weight-bold">{{ form.retention_percentage }}%</span>
            </div>
            <div v-if="form.subtrahend > 0" class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">Sustraendo:</span>
              <span class="text-subtitle-2 font-weight-bold">- Bs {{ formatCurrency(form.subtrahend) }}</span>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between align-center">
              <span class="text-body-2 font-weight-bold text-high-emphasis">Total a Retener (ISLR):</span>
              <span class="text-h6 font-weight-bold text-warning">Bs {{ formatCurrency(calculatedWithheld) }}</span>
            </div>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="submitting"
          @click="isOpen = false"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="warning"
          variant="flat"
          prepend-icon="tabler-check"
          :loading="submitting"
          :disabled="checkingNumber"
          @click="handleGenerate"
        >
          Generar Comprobante ISLR
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
