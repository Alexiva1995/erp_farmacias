<script setup>
import { ref, watch, computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  retention: { type: Object, default: null },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const { mobile } = useDisplay();

// Estado local editable del comprobante y sus facturas
const form = ref({
  number: "",
  date: "",
  invoices: [],
});
const saving = ref(false);

// Sincroniza el formulario cuando abre el diálogo con la retención
watch(
  () => [props.modelValue, props.retention],
  ([open, ret]) => {
    if (open && ret) {
      form.value = {
        number: ret.number ?? "",
        date: ret.date ?? "",
        invoices: (ret.invoices ?? []).map((inv) => ({
          id: inv.id,
          invoice_number: inv.invoice_number ?? "",
          control_number: inv.control_number ?? "",
          created_invoice_date: inv.created_invoice_date ?? "",
          taxable_base: inv.taxable_base ?? 0,
          tax_amount: inv.tax_amount ?? 0,
        })),
      };
    }
  },
  { immediate: true }
);

const hasChanges = computed(() => {
  if (!props.retention) return false;
  return true; // Siempre permitir guardar mientras el diálogo esté abierto
});

const removeInvoice = (idx) => {
  form.value.invoices.splice(idx, 1);
};

const closeDialog = () => {
  if (saving.value) return;
  emit("update:modelValue", false);
};

const handleSave = () => {
  emit("saved", {
    number: form.value.number,
    date: form.value.date,
    invoices: form.value.invoices.map((inv) => ({
      id: inv.id,
      invoice_number: inv.invoice_number,
      control_number: inv.control_number,
    })),
  });
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    max-width="900"
    persistent
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-edit" size="22" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Editar Retención
            </h2>
            <span
              class="text-white opacity-75 uppercase font-weight-bold"
              style="font-size: 0.6rem; letter-spacing: 0.05em;"
            >
              Comprobante #{{ props.retention?.number }}
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            :disabled="props.loading"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <!-- Formulario principal -->
      <VCardText class="pa-5 overflow-y-auto" style="max-height: 68vh;">
        <!-- Datos generales del comprobante -->
        <VCard variant="flat" class="pa-4 rounded-lg elevation-1 mb-5 border">
          <div class="text-caption text-uppercase font-weight-black text-medium-emphasis mb-3 d-flex align-center gap-2">
            <VIcon icon="tabler-file-description" size="14" />
            Datos del Comprobante
          </div>
          <VRow dense>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="form.number"
                label="Número de Comprobante"
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-hash"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="form.date"
                label="Fecha Fiscal de Emisión"
                density="compact"
                hide-details
                :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar"
              />
            </VCol>
          </VRow>
        </VCard>

        <!-- Tabla editable de facturas -->
        <VCard variant="flat" class="pa-4 rounded-lg elevation-1 border">
          <div class="text-caption text-uppercase font-weight-black text-medium-emphasis mb-3 d-flex align-center gap-2">
            <VIcon icon="tabler-file-invoice" size="14" />
            Facturas Vinculadas
            <VChip size="x-small" color="primary" variant="tonal" class="ms-1">{{ form.invoices.length }}</VChip>
          </div>

          <div v-if="form.invoices.length === 0" class="text-center pa-6">
            <VIcon icon="tabler-inbox" size="48" color="disabled" class="mb-2" />
            <div class="text-body-2 text-disabled">No hay facturas vinculadas a este comprobante.</div>
          </div>

          <template v-else>
            <!-- Cabecera -->
            <VRow
              dense
              class="mb-1 px-2"
              no-gutters
            >
              <VCol cols="2">
                <span class="text-super-xs text-uppercase font-weight-black text-disabled">Fecha Factura</span>
              </VCol>
              <VCol cols="3">
                <span class="text-super-xs text-uppercase font-weight-black text-disabled">N° Factura</span>
              </VCol>
              <VCol cols="2">
                <span class="text-super-xs text-uppercase font-weight-black text-disabled">N° Control</span>
              </VCol>
              <VCol cols="2" class="text-right">
                <span class="text-super-xs text-uppercase font-weight-black text-disabled">Base Imponible</span>
              </VCol>
              <VCol cols="2" class="text-right">
                <span class="text-super-xs text-uppercase font-weight-black text-disabled">IVA</span>
              </VCol>
              <VCol cols="1" class="text-center">
                <span class="text-super-xs text-uppercase font-weight-black text-disabled">Acción</span>
              </VCol>
            </VRow>

            <VDivider class="mb-2" />

            <!-- Filas editables -->
            <VRow
              v-for="(inv, idx) in form.invoices"
              :key="inv.id"
              dense
              no-gutters
              align="center"
              class="invoice-row rounded-lg px-2 py-1 mb-1"
            >
              <VCol cols="2">
                <span class="text-caption text-medium-emphasis">
                  {{ inv.created_invoice_date ?? "—" }}
                </span>
              </VCol>
              <VCol cols="3" class="pe-2">
                <VTextField
                  v-model="form.invoices[idx].invoice_number"
                  density="compact"
                  variant="outlined"
                  hide-details
                  placeholder="N° Factura"
                  class="text-caption"
                />
              </VCol>
              <VCol cols="2" class="pe-2">
                <VTextField
                  v-model="form.invoices[idx].control_number"
                  density="compact"
                  variant="outlined"
                  hide-details
                  placeholder="N° Control"
                  class="text-caption"
                />
              </VCol>
              <VCol cols="2" class="text-right pe-1">
                <span class="text-caption font-weight-medium">
                  {{ Number(inv.taxable_base).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                </span>
              </VCol>
              <VCol cols="2" class="text-right pe-1">
                <span class="text-caption font-weight-medium text-warning">
                  {{ Number(inv.tax_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                </span>
              </VCol>
              <VCol cols="1" class="text-center">
                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  size="x-small"
                  title="Quitar / desvincular factura"
                  @click="removeInvoice(idx)"
                />
              </VCol>
            </VRow>
          </template>
        </VCard>
      </VCardText>

      <!-- Acciones -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="46"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              :disabled="props.loading"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="46"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="props.loading"
              :disabled="props.loading"
              @click="handleSave"
            >
              <VIcon start icon="tabler-device-floppy" size="18" class="me-2" />
              Guardar Cambios
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.invoice-row {
  transition: background-color 0.15s ease;
}

.invoice-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.text-super-xs {
  font-size: 0.6rem !important;
  letter-spacing: 0.06em !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.35) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.leading-none {
  line-height: 1 !important;
}
</style>
