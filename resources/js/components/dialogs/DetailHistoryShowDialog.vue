<script setup>
import { VNodeRenderer } from "@layouts/components/VNodeRenderer";
import { themeConfig } from "@themeConfig";
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  historyName: { type: String, default: "" },
  details: { type: Array, default: () => [] },
  historyId: { type: Number },
  histories: { type: Object, default: () => ({}) },
  user: { type: Object, default: () => ({}) },
});

// Calcular totales de la tabla
const tableIvaTotal = computed(() => {
  return props.details.reduce((sum, detail) => {
    return sum + parseFloat(detail.iva_amount || 0);
  }, 0);
});

const tableSubtotal = computed(() => {
  return props.details.reduce((sum, detail) => {
    return sum + parseFloat(detail.total_amount || 0);
  }, 0);
});

const tableTotalFinal = computed(() => {
  return tableSubtotal.value + tableIvaTotal.value;
});

const emit = defineEmits(["update:modelValue"]);
const onCancel = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    @update:model-value="onCancel"
  >
    <VCard>
      <VCardItem class="justify-center">
        <div class="app-logo">
          <VNodeRenderer :nodes="themeConfig.app.logo" />
          <h1 class="app-logo-title">
            {{ themeConfig.app.title }}
          </h1>
        </div>
        <!-- Destalles del Historial Fiscal: {{ props.historyName }} -->
      </VCardItem>

      <VCardText class="text-center">
        <VRow>
          <VCol cols="6" class="text-start">
            <div class="text-h5 mb-2">
              Factura N°: {{ props.histories?.fiscal_id || "N/A" }}
            </div>
            <div class="text-h5 mb-2">Vendedor:</div>
            <div class="text-h5 mb-2">Cliente:</div>
            <div class="text-h5 mb-2">Cédula o RIF:</div>
          </VCol>
          <VCol cols="6" class="text-end">
            <div class="text-h5 mb-2">
              Fecha:
              {{
                props.histories?.invoice_date
                  ? props.histories.invoice_date.split("T")[0]
                  : "N/A"
              }}
            </div>
            <div class="text-h5 mb-2">{{ props.user.username || "N/A" }}</div>
            <div class="text-h5 mb-2">
              {{ props.histories.business_name || "N/A" }}
            </div>
            <div class="text-h5 mb-2">
              {{ props.histories.identification || "N/A" }}
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardText>
        <VTable density="compact">
          <thead>
            <tr>
              <th class="text-left">Nombre del producto</th>
              <th class="text-left">Cantidad</th>
              <th class="text-left">Exento</th>
              <th class="text-left">IVA</th>
              <th class="text-left">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="detail in details" :key="detail.id">
              <td>{{ detail.product_name }}</td>
              <td>{{ detail.quantity }}</td>
              <td>${{ parseFloat(detail.exempt_amount).toFixed(2) }}</td>
              <td>${{ parseFloat(detail.iva_amount).toFixed(2) }}</td>
              <td>${{ parseFloat(detail.total_amount).toFixed(2) }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>

      <VCardText class="text-center">
        <VRow>
          <VCol cols="6" class="text-start">
            <div class="text-h5 mb-2">Subtotal:</div>
            <div class="text-h5 mb-2">Iva:</div>
            <div class="text-h5 mb-2">Monto Total:</div>
          </VCol>
          <VCol cols="6" class="text-end">
            <div class="text-h5 mb-2">${{ tableSubtotal.toFixed(2) }}</div>
            <div class="text-h5 mb-2">${{ tableIvaTotal.toFixed(2) }}</div>
            <div class="text-h5 mb-2">${{ tableTotalFinal.toFixed(2) }}</div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="onCancel"
          >Cerrar</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.app-logo :deep(svg),
.app-logo img {
  width: 64px !important;
  height: 64px !important;
}
</style>
