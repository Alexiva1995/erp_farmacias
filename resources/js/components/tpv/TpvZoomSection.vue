<script setup>
import { ref, computed, watch } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  exchangeRates: {
    type: Object,
    default: () => ({}),
  },
  selectedDisplayCurrency: {
    type: String,
    default: "USD",
  },
  hasOpenOrder: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["zoomItemAdded", "openBuysModal"]);

// ─── Estado Reactivo ──────────────────────────────────────────────────────────
const description = ref("");
const amountNet = ref("");
const selectedCurrency = ref(props.selectedDisplayCurrency || "USD");
const isSubmitting = ref(false);
const autoOpenCheckout = ref(false);

watch(
  () => props.selectedDisplayCurrency,
  (newCurr) => {
    if (newCurr && !amountNet.value) {
      selectedCurrency.value = newCurr;
    }
  }
);

const currencyOptions = [
  { title: "Dólares (USD)", value: "USD" },
  { title: "Bolívares (BS)", value: "BS" },
  { title: "Pesos Colombianos (COP)", value: "COP" },
];

// ─── Tasas de Cambio ─────────────────────────────────────────────────────────
const rateBs = computed(() => {
  const usdRates = props.exchangeRates?.["USD"];
  return parseFloat(usdRates?.["BS"] || 1);
});

const rateCop = computed(() => {
  const usdRates = props.exchangeRates?.["USD"];
  return parseFloat(usdRates?.["COP"] || 1);
});

// ─── Cálculos en Vivo (Monto sin IVA + IVA 16% + Total) ─────────────────────
const TAX_PERCENTAGE = 0.16;

const netValue = computed(() => {
  const val = parseFloat(amountNet.value);
  return isNaN(val) || val < 0 ? 0 : val;
});

const ivaValue = computed(() => {
  return netValue.value * TAX_PERCENTAGE;
});

const totalValue = computed(() => {
  return netValue.value + ivaValue.value;
});

// Conversión a USD para cálculo de equivalencias
const totalValueInUsd = computed(() => {
  if (selectedCurrency.value === "USD") return totalValue.value;
  if (selectedCurrency.value === "BS") return rateBs.value > 0 ? totalValue.value / rateBs.value : totalValue.value;
  if (selectedCurrency.value === "COP") return rateCop.value > 0 ? totalValue.value / rateCop.value : totalValue.value;
  return totalValue.value;
});

// Equivalencias simultáneas en las 3 monedas
const equivalentAmounts = computed(() => {
  const usd = totalValueInUsd.value;
  return {
    usd: usd,
    bs: usd * rateBs.value,
    cop: usd * rateCop.value,
  };
});

// Validación básica de formulario
const isFormValid = computed(() => {
  return description.value.trim().length > 0 && netValue.value > 0;
});

// ─── Enviar a la Orden y Facturación ─────────────────────────────────────────
const handleAddZoomToOrder = async (openCheckoutImmediately = false) => {
  if (!isFormValid.value) {
    toast.warning("Por favor ingrese la descripción y un monto sin IVA válido mayor a 0.");
    return;
  }

  isSubmitting.value = true;
  autoOpenCheckout.value = openCheckoutImmediately;

  try {
    const chosenCurrency = selectedCurrency.value;
    const payload = {
      description: description.value.trim(),
      amount_net: netValue.value,
      currency: chosenCurrency,
    };

    const response = await axios.post("/tpv/zoom/add-to-order", payload);

    toast.success(response.data?.message || "Servicio Zoom agregado a la orden correctamente.");

    // Notificar al componente padre para sincronizar la orden y moneda escogida
    emit("zoomItemAdded", {
      ...(response.data?.data || {}),
      selected_currency: chosenCurrency,
    });

    // Si el usuario eligió facturar/cobrar inmediatamente
    if (openCheckoutImmediately) {
      emit("openBuysModal");
    }

    // Limpiar formulario
    description.value = "";
    amountNet.value = "";
  } catch (error) {
    console.error("Error al facturar servicio Zoom:", error);
    const errorMsg =
      error.response?.data?.message ||
      error.response?.data?.error ||
      "No se pudo agregar el servicio Zoom a la orden.";
    toast.error(errorMsg);
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <VCard variant="flat" border class="rounded-lg mb-6 overflow-hidden elevation-1">
    <!-- Encabezado de la Sección Zoom -->
    <VCardItem class="pa-4 border-b bg-surface">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center gap-3">
          <div class="zoom-badge-icon d-flex align-center justify-center rounded-lg">
            <VIcon icon="tabler-truck-delivery" size="24" color="white" />
          </div>
          <div>
            <h2 class="text-subtitle-1 font-weight-black text-uppercase text-high-emphasis leading-none mb-1">
              Ingreso Envíos Zoom
            </h2>
            <span class="text-caption text-medium-emphasis">
              Registro de encomiendas y servicios Zoom (Documento no fiscal)
            </span>
          </div>
        </div>

        <VChip
          color="success"
          variant="tonal"
          size="small"
          class="font-weight-black text-uppercase letter-spacing-1 px-3"
        >
          <VIcon start icon="tabler-file-off" size="14" />
          No Genera Factura Fiscal
        </VChip>
      </div>
    </VCardItem>

    <VCardText class="pa-4">
      <VRow>
        <!-- Columna Izquierda: Formulario de Carga -->
        <VCol cols="12" md="7">
          <VCard variant="outlined" class="pa-4 rounded-lg bg-grey-lighten-5 border-dashed h-100">
            <span class="text-subtitle-2 font-weight-black text-uppercase text-primary d-block mb-3">
              Datos del Envío
            </span>

            <VRow dense>
              <!-- Campo Descripción -->
              <VCol cols="12">
                <label class="text-caption font-weight-bold text-high-emphasis d-block mb-1">
                  Descripción / Número de Guía Zoom <span class="text-error">*</span>
                </label>
                <VTextField
                  v-model="description"
                  placeholder="Ej: Guía Zoom 123456789 - Envío Caracas"
                  density="compact"
                  variant="outlined"
                  bg-color="surface"
                  prepend-inner-icon="tabler-file-description"
                  hide-details="auto"
                  :disabled="isSubmitting"
                  class="mb-3"
                  @keyup.enter="handleAddZoomToOrder(false)"
                />
              </VCol>

              <!-- Campo Monto sin IVA -->
              <VCol cols="12" sm="7">
                <label class="text-caption font-weight-bold text-high-emphasis d-block mb-1">
                  Monto sin IVA (Base Imponible) <span class="text-error">*</span>
                </label>
                <VTextField
                  v-model="amountNet"
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="0.00"
                  density="compact"
                  variant="outlined"
                  bg-color="surface"
                  prepend-inner-icon="tabler-currency-dollar"
                  hide-details="auto"
                  :disabled="isSubmitting"
                  class="mb-3"
                  @keyup.enter="handleAddZoomToOrder(false)"
                />
              </VCol>

              <!-- Selector de Moneda de Entrada -->
              <VCol cols="12" sm="5">
                <label class="text-caption font-weight-bold text-high-emphasis d-block mb-1">
                  Moneda del Monto
                </label>
                <VSelect
                  v-model="selectedCurrency"
                  :items="currencyOptions"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  variant="outlined"
                  bg-color="surface"
                  hide-details
                  :disabled="isSubmitting"
                  class="mb-3"
                />
              </VCol>
            </VRow>

            <!-- Acciones -->
            <div class="d-flex align-center gap-2 mt-4 flex-wrap">
              <VBtn
                color="primary"
                variant="flat"
                height="44"
                class="flex-grow-1 font-weight-bold text-none rounded-lg"
                :loading="isSubmitting && !autoOpenCheckout"
                :disabled="!isFormValid || isSubmitting"
                @click="handleAddZoomToOrder(false)"
              >
                <VIcon start icon="tabler-shopping-cart-plus" size="18" />
                <span>Agregar a la Orden</span>
              </VBtn>

              <VBtn
                color="success"
                variant="flat"
                height="44"
                class="flex-grow-1 font-weight-bold text-none rounded-lg"
                :loading="isSubmitting && autoOpenCheckout"
                :disabled="!isFormValid || isSubmitting"
                @click="handleAddZoomToOrder(true)"
              >
                <VIcon start icon="tabler-file-dollar" size="18" />
                <span>INGRESO ZOOM</span>
              </VBtn>
            </div>
          </VCard>
        </VCol>

        <!-- Columna Derecha: Desglose Tributario y Equivalencias -->
        <VCol cols="12" md="5">
          <VCard variant="outlined" class="pa-4 rounded-lg bg-surface border h-100 d-flex flex-column justify-space-between">
            <div>
              <div class="d-flex align-center justify-space-between mb-3">
                <span class="text-subtitle-2 font-weight-black text-uppercase text-medium-emphasis">
                  Resumen de Ingreso
                </span>
                <VChip size="x-small" color="success" variant="tonal" class="font-weight-bold">
                  Ingreso Zoom
                </VChip>
              </div>

              <!-- Tabla de Desglose -->
              <div class="d-flex flex-column gap-2 mb-4">
                <div class="d-flex justify-space-between align-center py-1 border-b">
                  <span class="text-caption text-medium-emphasis font-weight-bold text-uppercase">Monto Neto (Sin IVA):</span>
                  <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                    {{ formatCurrency(netValue, selectedCurrency) }}
                  </span>
                </div>

                <div class="d-flex justify-space-between align-center py-1 border-b">
                  <span class="text-caption text-medium-emphasis font-weight-bold text-uppercase">IVA Calculado (16%):</span>
                  <span class="text-subtitle-2 font-weight-bold text-warning">
                    + {{ formatCurrency(ivaValue, selectedCurrency) }}
                  </span>
                </div>

                <div class="d-flex justify-space-between align-center py-2 bg-primary-subtle rounded-lg px-2 mt-1">
                  <span class="text-subtitle-2 font-weight-black text-primary text-uppercase">Total a Ingresar:</span>
                  <span class="text-h6 font-weight-black text-primary">
                    {{ formatCurrency(totalValue, selectedCurrency) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Resumen Multimoneda -->
            <div class="border-t pt-3">
              <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1 d-block mb-2">
                Equivalencias al Cambio del Día
              </span>
              <div class="d-flex justify-space-between align-center text-caption font-weight-bold py-1">
                <span class="text-medium-emphasis">Total en Dólares (USD):</span>
                <span class="text-high-emphasis">{{ formatCurrency(equivalentAmounts.usd, "USD") }}</span>
              </div>
              <div class="d-flex justify-space-between align-center text-caption font-weight-bold py-1">
                <span class="text-medium-emphasis">Total en Bolívares (BS):</span>
                <span class="text-high-emphasis">{{ formatCurrency(equivalentAmounts.bs, "BS") }}</span>
              </div>
              <div class="d-flex justify-space-between align-center text-caption font-weight-bold py-1">
                <span class="text-medium-emphasis">Total en Pesos (COP):</span>
                <span class="text-high-emphasis">{{ formatCurrency(equivalentAmounts.cop, "COP") }}</span>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.zoom-badge-icon {
  inline-size: 40px;
  block-size: 40px;
  background: linear-gradient(135deg, #f57c00 0%, #e65100 100%);
  box-shadow: 0 4px 10px rgba(230, 81, 0, 0.3);
}

.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.gap-2 {
  gap: 8px !important;
}

.gap-3 {
  gap: 12px !important;
}
</style>
