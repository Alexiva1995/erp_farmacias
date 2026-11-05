<script setup>
import { computed } from "vue";
import { formatAmountOnly } from "@/utils/currencyFormatter";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  previewData: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  isConfirming: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const isDialogVisible = computed({
  get() {
    return props.modelValue;
  },
  set(val) {
    emit("update:modelValue", val);
  },
});

const closeDialog = () => {
  if (props.isConfirming) return;
  isDialogVisible.value = false;
};

const handleConfirm = () => {
  if (!props.previewData?.order_id || props.isConfirming) return;
  emit("confirm", props.previewData.order_id);
};

const formatBs = (val) => {
  const num = Number(val) || 0;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
};
</script>

<template>
  <VDialog
    v-model="isDialogVisible"
    max-width="640"
    scrollable
    persistent
  >
    <VCard class="rounded-lg overflow-hidden">
      <!-- Encabezado -->
      <VCardTitle class="pa-4 bg-primary text-white d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-file-invoice" size="24" color="white" />
          <div>
            <div class="text-subtitle-1 font-weight-bold text-white line-height-1">
              Previa de Comprobante Fiscal
            </div>
            <div class="text-caption text-white opacity-80 mt-0.5">
              Orden #{{ previewData?.order_id ?? "—" }} · Valores calculados en Bolívares (Bs)
            </div>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          :disabled="props.isConfirming"
          @click="closeDialog"
        />
      </VCardTitle>

      <VCardText class="pa-4">
        <!-- Estado de Carga -->
        <div v-if="props.loading" class="py-6">
          <VSkeletonLoader type="table-heading, list-item-two-line, table-row-divider@3, actions" />
        </div>

        <div v-else-if="previewData" class="d-flex flex-column gap-4">
          <!-- Datos del Contribuyente / Cliente -->
          <VCard variant="tonal" color="primary" class="pa-3 rounded-lg">
            <VRow dense>
              <VCol cols="12" sm="7">
                <div class="text-caption text-medium-emphasis">Cliente / Razón Social:</div>
                <div class="text-body-2 font-weight-bold text-high-emphasis">
                  {{ previewData.client_name }}
                </div>
              </VCol>
              <VCol cols="12" sm="5">
                <div class="text-caption text-medium-emphasis">RIF / Cédula:</div>
                <div class="text-body-2 font-weight-bold text-high-emphasis">
                  {{ previewData.client_identification }}
                </div>
              </VCol>
              <VCol cols="12" sm="7" class="mt-1">
                <div class="text-caption text-medium-emphasis">Dirección Fiscal:</div>
                <div class="text-caption text-high-emphasis text-truncate">
                  {{ previewData.client_address || "Sin dirección fiscal registrada" }}
                </div>
              </VCol>
              <VCol cols="12" sm="5" class="mt-1">
                <div class="text-caption text-medium-emphasis">Tasa de Cambio Oficial:</div>
                <div class="text-body-2 font-weight-bold text-primary">
                  {{ formatBs(previewData.exchange_rate) }} Bs / USD
                </div>
              </VCol>
            </VRow>
          </VCard>

          <!-- Tabla de Renglones Fiscales en Bs -->
          <div>
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
              Detalle de Renglones Fiscales (Bs)
            </div>
            <VTable density="compact" class="border rounded-lg overflow-hidden">
              <thead>
                <tr class="bg-light">
                  <th class="text-left font-weight-bold text-caption">Producto / Servicio</th>
                  <th class="text-center font-weight-bold text-caption">Cant.</th>
                  <th class="text-end font-weight-bold text-caption">Precio Unit. (Bs)</th>
                  <th class="text-center font-weight-bold text-caption">Condición</th>
                  <th class="text-end font-weight-bold text-caption">Total (Bs)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in previewData.items" :key="idx">
                  <td class="text-caption font-weight-medium">
                    {{ item.name }}
                  </td>
                  <td class="text-center text-caption">
                    {{ item.quantity }}
                  </td>
                  <td class="text-end text-caption">
                    {{ formatBs(item.price_bs) }}
                  </td>
                  <td class="text-center">
                    <VChip
                      size="x-small"
                      :color="item.is_taxable ? 'primary' : 'secondary'"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ item.is_taxable ? 'G 16%' : 'Exento (E)' }}
                    </VChip>
                  </td>
                  <td class="text-end text-caption font-weight-bold">
                    {{ formatBs(item.total_amount) }}
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <VDivider />

          <!-- Resumen Fiscal SENIAT -->
          <VCard variant="outlined" class="rounded-lg pa-3 bg-light">
            <div class="text-caption font-weight-black text-uppercase text-primary mb-2">
              Desglose Fiscal (SENIAT)
            </div>
            <div class="d-flex justify-space-between align-center py-1">
              <span class="text-caption text-medium-emphasis">Monto Exento (E):</span>
              <span class="text-body-2 font-weight-medium">{{ formatBs(previewData.exempt_amount_bs) }} Bs</span>
            </div>
            <div class="d-flex justify-space-between align-center py-1">
              <span class="text-caption text-medium-emphasis">Base Imponible (G 16%):</span>
              <span class="text-body-2 font-weight-medium">{{ formatBs(previewData.taxable_amount_bs) }} Bs</span>
            </div>
            <div class="d-flex justify-space-between align-center py-1">
              <span class="text-caption text-medium-emphasis">IVA (16%):</span>
              <span class="text-body-2 font-weight-medium text-primary">{{ formatBs(previewData.iva_amount_bs) }} Bs</span>
            </div>
            <div class="d-flex justify-space-between align-center py-1 border-t">
              <span class="text-caption font-weight-bold">Subtotal:</span>
              <span class="text-body-2 font-weight-bold">{{ formatBs(previewData.subtotal_bs) }} Bs</span>
            </div>
            <div v-if="previewData.spe || previewData.spe_surcharge_amount_bs > 0" class="d-flex justify-space-between align-center py-1 text-error">
              <span class="text-caption font-weight-bold">IGTF (3%) Percibido Divisas:</span>
              <span class="text-body-2 font-weight-bold">+ {{ formatBs(previewData.spe_surcharge_amount_bs) }} Bs</span>
            </div>
            <VDivider class="my-1.5" />
            <div class="d-flex justify-space-between align-center">
              <span class="text-subtitle-2 font-weight-black text-primary">TOTAL FACTURA FISCAL:</span>
              <span class="text-h6 font-weight-black text-primary">{{ formatBs(previewData.total_amount_bs) }} Bs</span>
            </div>
          </VCard>
        </div>

        <div v-else class="text-center py-8 text-medium-emphasis text-caption">
          No hay datos de previa disponibles para esta orden.
        </div>
      </VCardText>

      <!-- Botones de Acción -->
      <VCardActions class="pa-4 bg-light border-t d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="props.isConfirming"
          @click="closeDialog"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-printer"
          :loading="props.isConfirming"
          :disabled="!previewData || props.loading || props.isConfirming"
          @click="handleConfirm"
        >
          Emitir e Imprimir Factura Fiscal
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
