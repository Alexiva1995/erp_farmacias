<script setup>
import { computed, ref } from "vue";

// Datos ficticios de ejemplo
const debitoFiscal = ref(150000);
const creditoFiscal = ref(85000);

// Cálculo automático del IVA a pagar (Débito Fiscal - Crédito Fiscal)
const ivaAPagar = computed(() => {
  return debitoFiscal.value - creditoFiscal.value;
});

// Función para formatear moneda colombiana
const formatCurrency = (amount) => {
  const formatter = new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
  return formatter.format(amount);
};

// Determinar color y estado según el resultado
const getIvaStatus = computed(() => {
  if (ivaAPagar.value > 0) {
    return {
      color: "error",
      icon: "tabler-trending-up",
      message: "A pagar",
      chipColor: "error",
    };
  } else if (ivaAPagar.value < 0) {
    return {
      color: "success",
      icon: "tabler-trending-down",
      message: "A favor",
      chipColor: "success",
    };
  } else {
    return {
      color: "info",
      icon: "tabler-equal",
      message: "Equilibrado",
      chipColor: "info",
    };
  }
});
</script>

<template>
  <div>
    <!-- Card de Cálculo IVA Fiscal -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center">
        <VIcon icon="tabler-calculator" class="me-2" />
        Cálculo IVA Fiscal
        <VSpacer />
        <VChip :color="getIvaStatus.chipColor" size="small" variant="tonal">
          <VIcon :icon="getIvaStatus.icon" size="14" class="me-1" />
          {{ getIvaStatus.message }}
        </VChip>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VRow>
          <!-- Débito Fiscal -->
          <VCol cols="12" md="4">
            <VCard variant="tonal" color="warning">
              <VCardText class="text-center">
                <div class="d-flex align-center justify-center mb-2">
                  <VIcon icon="tabler-arrow-up-circle" size="28" class="me-2" />
                  <span class="text-h6 font-weight-bold">Débito Fiscal</span>
                </div>
                <div class="text-h4 font-weight-bold text-warning-darken-2">
                  {{ formatCurrency(debitoFiscal) }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1">
                  IVA cobrado en ventas
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Crédito Fiscal -->
          <VCol cols="12" md="4">
            <VCard variant="tonal" color="info">
              <VCardText class="text-center">
                <div class="d-flex align-center justify-center mb-2">
                  <VIcon
                    icon="tabler-arrow-down-circle"
                    size="28"
                    class="me-2"
                  />
                  <span class="text-h6 font-weight-bold">Crédito Fiscal</span>
                </div>
                <div class="text-h4 font-weight-bold text-info-darken-2">
                  {{ formatCurrency(creditoFiscal) }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1">
                  IVA pagado en compras
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- IVA a Pagar -->
          <VCol cols="12" md="4">
            <VCard
              variant="tonal"
              :color="getIvaStatus.color"
              class="position-relative"
            >
              <VCardText class="text-center">
                <div class="d-flex align-center justify-center mb-2">
                  <VIcon :icon="getIvaStatus.icon" size="28" class="me-2" />
                  <span class="text-h6 font-weight-bold">IVA a Pagar</span>
                </div>
                <div
                  class="text-h4 font-weight-bold"
                  :class="{
                    'text-error-darken-2': ivaAPagar > 0,
                    'text-success-darken-2': ivaAPagar < 0,
                    'text-info-darken-2': ivaAPagar === 0,
                  }"
                >
                  {{ formatCurrency(Math.abs(ivaAPagar)) }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1">
                  {{ getIvaStatus.message }}
                </div>
              </VCardText>

              <!-- Badge para valores negativos -->
              <div
                v-if="ivaAPagar < 0"
                class="position-absolute"
                style="top: 10px; right: 10px"
              >
                <VChip color="success" size="x-small" variant="flat">
                  Saldo a favor
                </VChip>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Detalles del cálculo -->
        <VRow class="mt-4">
          <VCol cols="12">
            <VAlert type="info" variant="tonal" class="mb-0">
              <template #title>
                <div class="d-flex align-center">
                  <VIcon icon="tabler-info-circle" class="me-2" />
                  Fórmula de Cálculo
                </div>
              </template>

              <div
                class="d-flex align-center justify-center flex-wrap ga-2 mt-2"
              >
                <VChip color="warning" variant="outlined" size="small">
                  Débito Fiscal: {{ formatCurrency(debitoFiscal) }}
                </VChip>
                <VIcon icon="tabler-minus" size="20" />
                <VChip color="info" variant="outlined" size="small">
                  Crédito Fiscal: {{ formatCurrency(creditoFiscal) }}
                </VChip>
                <VIcon icon="tabler-equal" size="20" />
                <VChip :color="getIvaStatus.color" variant="flat" size="small">
                  {{ formatCurrency(ivaAPagar) }}
                </VChip>
              </div>

              <div class="text-center mt-3 text-body-2">
                <template v-if="ivaAPagar > 0">
                  <strong>Resultado:</strong> Debe pagar
                  {{ formatCurrency(ivaAPagar) }} de IVA a la DIAN
                </template>
                <template v-else-if="ivaAPagar < 0">
                  <strong>Resultado:</strong> Tiene un saldo a favor de
                  {{ formatCurrency(Math.abs(ivaAPagar)) }}
                </template>
                <template v-else>
                  <strong>Resultado:</strong> El débito y crédito fiscal están
                  equilibrados
                </template>
              </div>
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
