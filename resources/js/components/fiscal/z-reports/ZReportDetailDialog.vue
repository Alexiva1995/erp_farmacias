<script setup>
import DialogCloseBtn from "@core/components/DialogCloseBtn.vue";
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  report: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const showPhotoPreview = ref(false);

const resolveImageUrl = (item) => {
  if (!item) return null;
  if (item.image_url) return item.image_url;
  if (item.image_path) {
    return item.image_path.startsWith("http") ? item.image_path : `/storage/${item.image_path}`;
  }
  return null;
};

const hasDiscrepancies = computed(() => {
  return props.report?.status === "DISCREPANCIA" || (props.report?.discrepancies && props.report.discrepancies.length > 0);
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const printTicket = () => {
  window.print();
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="560"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <DialogCloseBtn @click="emit('update:modelValue', false)" />

    <VCard class="rounded-xl overflow-hidden">
      <!-- Encabezado Estándar del Sistema -->
      <VCardItem class="pb-3 border-b">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-receipt" color="primary" size="26" />
          <div>
            <VCardTitle class="text-h6 font-weight-bold">
              Reporte Z {{ props.report?.report_number_padded || `Z${String(props.report?.report_number || '').padStart(6, '0')}` }}
            </VCardTitle>
            <VCardSubtitle class="text-caption">
              Corte fiscal diario y desglose de operaciones
            </VCardSubtitle>
          </div>
        </div>
      </VCardItem>

      <VCardText class="pa-4 bg-background">
        <!-- Banner de Discrepancias si existen -->
        <VAlert
          v-if="hasDiscrepancies"
          type="error"
          variant="tonal"
          density="comfortable"
          class="mb-4 rounded-lg"
          icon="tabler-alert-triangle"
        >
          <div class="font-weight-black mb-1">
            Discrepancia detectada con la Foto del Reporte Z
          </div>
          <div v-if="props.report?.ai_verification_notes" class="text-caption mb-2">
            {{ props.report.ai_verification_notes }}
          </div>

          <!-- Tabla Comparativa de Discrepancias -->
          <div v-if="props.report?.discrepancies && props.report.discrepancies.length > 0" class="discrepancy-table-container bg-surface rounded pa-2 border">
            <div class="text-caption font-weight-bold text-error mb-1">
              Detalle de campos con diferencias:
            </div>
            <VTable density="compact" class="text-xs bg-transparent">
              <thead>
                <tr>
                  <th class="text-start font-weight-bold">Concepto</th>
                  <th class="text-end font-weight-bold">Sistema</th>
                  <th class="text-end font-weight-bold text-error">Foto Z</th>
                  <th class="text-end font-weight-bold">Diferencia</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(disc, idx) in props.report.discrepancies" :key="idx">
                  <td class="font-weight-medium">{{ disc.label || disc.field }}</td>
                  <td class="text-end">Bs. {{ formatCurrency(disc.system_value) }}</td>
                  <td class="text-end font-weight-black text-error">Bs. {{ formatCurrency(disc.photo_value) }}</td>
                  <td class="text-end font-weight-bold" :class="Number(disc.diff) < 0 ? 'text-error' : 'text-success'">
                    Bs. {{ formatCurrency(disc.diff) }}
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VAlert>

        <!-- Banner de Validación Exitosa si comprobado -->
        <VAlert
          v-else-if="props.report?.status === 'COMPROBADO'"
          type="info"
          variant="tonal"
          density="comfortable"
          class="mb-4 rounded-lg"
          icon="tabler-check"
        >
          <div class="font-weight-bold">
            Verificado exitosamente con IA
          </div>
          <div class="text-caption">
            Todos los montos coinciden exactamente con la foto física del Reporte Z.
          </div>
        </VAlert>

        <!-- Ticket Térmico Z -->
        <div class="fiscal-ticket pa-4 rounded-lg bg-surface border mb-4">
          <div class="text-center mb-3">
            <div class="font-weight-black text-uppercase text-body-1">
              FARMACIA BARRIO SUCRE 2024, C.A.
            </div>
            <div class="text-caption text-disabled">RIF: J-50474660-6</div>
            <div class="text-caption text-disabled">CORTE FISCAL DIARIO</div>
            <div class="text-h6 font-weight-black text-primary mt-1">
              REPORTE Z N° {{ props.report?.report_number_padded || `Z${String(props.report?.report_number || '').padStart(6, '0')}` }}
            </div>
          </div>

          <VDivider class="border-dashed my-2" />

          <!-- Datos de Fecha y Auditoría -->
          <div class="d-flex flex-column gap-1 text-caption mb-3">
            <div class="d-flex justify-space-between">
              <span class="text-disabled">FECHA DE EMISIÓN:</span>
              <span class="font-weight-bold">{{ props.report?.report_date }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">HORA DE APERTURA:</span>
              <span>{{ props.report?.opening_time || '00:00:00' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">HORA DE CIERRE:</span>
              <span>{{ props.report?.closing_time || '23:59:59' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">PRIMERA FACTURA:</span>
              <span class="font-weight-medium">{{ props.report?.first_invoice_number || 'N/A' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">ÚLTIMA FACTURA:</span>
              <span class="font-weight-medium">{{ props.report?.last_invoice_number || 'N/A' }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">CANTIDAD DE FACTURAS:</span>
              <span class="font-weight-bold text-info">{{ props.report?.invoices_count || 0 }} DOCS</span>
            </div>
          </div>

          <VDivider class="border-dashed my-2" />

          <!-- Desglose Fiscal -->
          <div class="d-flex flex-column gap-1 text-sm">
            <!-- Exento -->
            <div class="d-flex justify-space-between align-center">
              <span class="font-weight-medium">VENTAS EXENTAS (E):</span>
              <div class="text-end">
                <span :class="{'text-error font-weight-bold': props.report?.discrepancies_map?.exempt_amount}">
                  Bs. {{ formatCurrency(props.report?.exempt_amount) }}
                </span>
                <div v-if="props.report?.discrepancies_map?.exempt_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(props.report.discrepancies_map.exempt_amount.photo_value) }}
                </div>
              </div>
            </div>

            <!-- Base 16% -->
            <div class="d-flex justify-space-between align-center">
              <span class="font-weight-medium">BASE IMPONIBLE (G 16%):</span>
              <div class="text-end">
                <span :class="{'text-error font-weight-bold': props.report?.discrepancies_map?.base_16_amount}">
                  Bs. {{ formatCurrency(props.report?.base_16_amount) }}
                </span>
                <div v-if="props.report?.discrepancies_map?.base_16_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(props.report.discrepancies_map.base_16_amount.photo_value) }}
                </div>
              </div>
            </div>

            <!-- IVA 16% -->
            <div class="d-flex justify-space-between align-center">
              <span class="font-weight-medium text-warning">IMPUESTO IVA (G 16%):</span>
              <div class="text-end">
                <span :class="props.report?.discrepancies_map?.iva_amount ? 'text-error font-weight-bold' : 'text-warning font-weight-bold'">
                  Bs. {{ formatCurrency(props.report?.iva_amount) }}
                </span>
                <div v-if="props.report?.discrepancies_map?.iva_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(props.report.discrepancies_map.iva_amount.photo_value) }}
                </div>
              </div>
            </div>

            <!-- Base IGTF -->
            <div class="d-flex justify-space-between align-center">
              <span class="font-weight-medium">BASE IGTF / SPE:</span>
              <span class="font-weight-bold">Bs. {{ formatCurrency(props.report?.igtf_base_amount) }}</span>
            </div>

            <!-- IGTF -->
            <div class="d-flex justify-space-between align-center">
              <span class="font-weight-medium text-error">IGTF PERCIBIDO (3%):</span>
              <div class="text-end">
                <span class="font-weight-bold text-error">
                  Bs. {{ formatCurrency(props.report?.igtf_amount) }}
                </span>
                <div v-if="props.report?.discrepancies_map?.igtf_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(props.report.discrepancies_map.igtf_amount.photo_value) }}
                </div>
              </div>
            </div>
          </div>

          <VDivider class="border-dashed my-3" />

          <!-- Total Final -->
          <div class="d-flex justify-space-between align-center py-2 px-3 rounded bg-success-tonal">
            <span class="text-subtitle-1 font-weight-black">TOTAL REPORTE Z:</span>
            <div class="text-end">
              <span class="text-h6 font-weight-black text-success">
                Bs. {{ formatCurrency(props.report?.total_amount) }}
              </span>
              <div v-if="props.report?.discrepancies_map?.total_amount" class="text-caption text-error font-weight-black d-flex align-center justify-end gap-1">
                <VIcon icon="tabler-camera" size="12" />
                Bs. {{ formatCurrency(props.report.discrepancies_map.total_amount.photo_value) }}
              </div>
            </div>
          </div>

          <div class="text-center text-super-xs text-disabled mt-3">
            DOCUMENTO FISCAL DE CIERRE DIARIO GENERADO AUTOMÁTICAMENTE
          </div>
        </div>

        <!-- Sección de Foto del Comprobante Fiscal Adjunta -->
        <VCard v-if="resolveImageUrl(props.report)" border variant="flat" class="pa-3 rounded-lg bg-surface">
          <div class="d-flex justify-space-between align-center mb-2">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-photo" size="20" color="primary" />
              <span class="text-subtitle-2 font-weight-bold">Foto del Reporte Z</span>
            </div>
            <div class="d-flex gap-1">
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-eye"
                @click="showPhotoPreview = !showPhotoPreview"
              >
                {{ showPhotoPreview ? 'Ocultar' : 'Ver Foto' }}
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                color="success"
                prepend-icon="tabler-download"
                :href="resolveImageUrl(props.report)"
                target="_blank"
                :download="`reporte_z_${props.report?.report_number}.jpg`"
              >
                Descargar
              </VBtn>
            </div>
          </div>

          <VExpandTransition>
            <div v-if="showPhotoPreview" class="text-center mt-2 border rounded overflow-hidden">
              <img
                :src="resolveImageUrl(props.report)"
                alt="Foto del Reporte Z"
                class="img-fluid rounded max-h-400"
                style="max-width: 100%; object-fit: contain;"
              />
            </div>
          </VExpandTransition>
        </VCard>
      </VCardText>

      <VCardActions class="pa-4 pt-0">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="ps-0 pe-1">
            <VBtn
              block
              variant="outlined"
              color="secondary"
              @click="emit('update:modelValue', false)"
            >
              Cerrar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pe-0 ps-1">
            <VBtn
              block
              variant="flat"
              color="primary"
              prepend-icon="tabler-printer"
              @click="printTicket"
            >
              Imprimir Ticket
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.fiscal-ticket {
  font-family: monospace;
}

.text-super-xs {
  font-size: 0.65rem;
}

.bg-success-tonal {
  background-color: rgba(var(--v-theme-success), 0.12);
}

.max-h-400 {
  max-height: 400px;
}

.discrepancy-table-container table th,
.discrepancy-table-container table td {
  padding: 4px 8px !important;
  font-size: 0.75rem !important;
}
</style>

