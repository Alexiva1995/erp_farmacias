<script setup>
import ExchangeRateCard from "@/components/ExchangeRateCard.vue";
import axios from "@/plugins/axios";
import { useBrandingStore } from "@/stores/useBrandingStore";
import Swal from "sweetalert2";
import { computed, ref } from "vue";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

const props = defineProps({
  rates: { type: Object, required: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["refresh"]);

const pesosInput = ref("");
const copcInput = ref("");
const bsCopInput = ref("");
const copsInput = ref("");
const loadingButtons = ref({});

const submitManualRate = async (currencyCode, value) => {
  if (!value) {
    Swal.fire({
      icon: "info",
      title: "Actualización Manual Requerida",
      text: `Ingrese un monto válido para la tasa ${currencyCode}.`,
    });
    return;
  }

  loadingButtons.value[currencyCode] = true;
  try {
    await axios.post("/finances/exchange-rates/store", {
      currency_code: currencyCode,
      rate: parseFloat(value),
    });
    Swal.fire({
      icon: "success",
      title: `Tasa ${currencyCode} actualizada`,
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 3000,
    });
    emit("refresh");
    if (currencyCode === "COP") pesosInput.value = "";
    if (currencyCode === "COPC") copcInput.value = "";
    if (currencyCode === "BS_COP") bsCopInput.value = "";
    if (currencyCode === "COPS") copsInput.value = "";
  } catch (error) {
    console.error(`Error al actualizar tasa manual ${currencyCode}:`, error);
  } finally {
    loadingButtons.value[currencyCode] = false;
  }
};

const updateRate = async (currency) => {
  loadingButtons.value[currency] = true;
  try {
    await axios.post("/finances/exchange-rates/store", {
      currency_code: currency,
      rate: null,
    });
    const sourceText = currency === "BINANCE" ? "Binance P2P" : (currency === "EUR" ? "Euro Oficial" : "BCV");
    Swal.fire({
      icon: "success",
      title: `Tasa ${currency} actualizada (${sourceText})`,
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 3000,
    });
    emit("refresh");
  } catch (error) {
    console.error("Error al actualizar tasa automática:", error);
  } finally {
    loadingButtons.value[currency] = false;
  }
};
</script>

<template>
  <div class="d-flex flex-column gap-6 pa-1">
    <!-- FILA 1: TASAS EN BOLÍVARES (BS) Y EUROS -->
    <div>
      <div class="text-subtitle-1 font-weight-bold text-uppercase text-medium-emphasis mb-3 d-flex align-center gap-2">
        <VIcon icon="tabler-currency-dollar" size="20" color="primary" />
        Tasas en Bolívares (BS) y Divisas Oficiales
      </div>

      <VRow class="match-height ma-0 mx-n1">
        <!-- Skeletons durante la carga inicial -->
        <template v-if="props.loading">
          <VCol v-for="n in 4" :key="n" cols="12" sm="6" md="3" class="pa-1">
            <VSkeletonLoader type="card, article, button" class="rounded-lg shadow-sm h-100" />
          </VCol>
        </template>

        <template v-else>
          <!-- Dólar BCV -->
          <VCol cols="12" sm="6" md="3" class="pa-1">
            <ExchangeRateCard
              title="Dólar BCV"
              badge-text="Tasa Oficial"
              :rate-value="props.rates.BS.rate"
              prefix="Bs."
              :decimals="4"
              color="primary"
              icon="tabler-currency-dollar"
              :date-update="props.rates.BS.dateUpdate"
              :date-color="props.rates.BS.dateColor"
              :loading="!!loadingButtons['BS']"
              btn-text="ACTUALIZAR BCV"
              @update-auto="updateRate('BS')"
            />
          </VCol>

          <!-- Dólar Binance -->
          <VCol cols="12" sm="6" md="3" class="pa-1">
            <ExchangeRateCard
              title="Dólar Binance"
              :badge-text="isRestaurant ? 'Tasa Activa (Rest)' : 'Tasa P2P'"
              :rate-value="props.rates.BINANCE.rate"
              prefix="Bs."
              :decimals="4"
              color="error"
              icon="tabler-currency-bitcoin"
              :date-update="props.rates.BINANCE.dateUpdate"
              :date-color="props.rates.BINANCE.dateColor"
              :is-restaurant-selected="isRestaurant"
              :loading="!!loadingButtons['BINANCE']"
              btn-text="ACTUALIZAR BINANCE"
              @update-auto="updateRate('BINANCE')"
            />
          </VCol>

          <!-- Euro (EUR) -->
          <VCol cols="12" sm="6" md="3" class="pa-1">
            <ExchangeRateCard
              title="Euro BCV"
              badge-text="Tasa Oficial"
              :rate-value="props.rates.EUR.rate"
              prefix="Bs."
              :decimals="4"
              color="warning"
              icon="tabler-currency-euro"
              :date-update="props.rates.EUR.dateUpdate"
              :date-color="props.rates.EUR.dateColor"
              :loading="!!loadingButtons['EUR']"
              btn-text="ACTUALIZAR BCV"
              @update-auto="updateRate('EUR')"
            />
          </VCol>

          <!-- Tasa Frontera (BS_COP) -->
          <VCol cols="12" sm="6" md="3" class="pa-1">
            <ExchangeRateCard
              v-model="bsCopInput"
              title="Tasa Frontera (Bs COP)"
              badge-text="Divisor Frontera"
              :rate-value="props.rates.BS_COP.rate"
              :decimals="2"
              subtext="Divisor de Precio para Pesos"
              color="purple"
              icon="tabler-calculator"
              :date-update="props.rates.BS_COP.dateUpdate"
              :date-color="props.rates.BS_COP.dateColor"
              :is-manual="true"
              :loading="!!loadingButtons['BS_COP']"
              @submit-manual="submitManualRate('BS_COP', bsCopInput)"
            />
          </VCol>
        </template>
      </VRow>
    </div>

    <!-- FILA 2: TASAS EN PESOS COLOMBIANOS (COP) -->
    <div>
      <div class="text-subtitle-1 font-weight-bold text-uppercase text-medium-emphasis mb-3 d-flex align-center gap-2">
        <VIcon icon="tabler-currency-peso" size="20" color="info" />
        Tasas en Pesos Colombianos (COP)
      </div>

      <VRow class="match-height ma-0 mx-n1">
        <!-- Skeletons durante la carga inicial -->
        <template v-if="props.loading">
          <VCol v-for="n in 3" :key="n" cols="12" sm="6" md="4" class="pa-1">
            <VSkeletonLoader type="card, article, button" class="rounded-lg shadow-sm h-100" />
          </VCol>
        </template>

        <template v-else>
          <!-- Peso Colombiano (COP) Venta -->
          <VCol cols="12" sm="6" md="4" class="pa-1">
            <ExchangeRateCard
              v-model="pesosInput"
              title="Peso (COP)"
              badge-text="Tasa Manual"
              :rate-value="props.rates.COP.rate"
              :decimals="2"
              subtext="Tasa Actual de Venta"
              color="info"
              icon="tabler-currency-peso"
              :date-update="props.rates.COP.dateUpdate"
              :date-color="props.rates.COP.dateColor"
              :is-manual="true"
              :loading="!!loadingButtons['COP']"
              @submit-manual="submitManualRate('COP', pesosInput)"
            />
          </VCol>

          <!-- COP Cambio (COPC) Compras -->
          <VCol cols="12" sm="6" md="4" class="pa-1">
            <ExchangeRateCard
              v-model="copcInput"
              title="COP (COPC)"
              badge-text="Cambio Manual"
              :rate-value="props.rates.COPC.rate"
              :decimals="2"
              subtext="Tasa para Compras"
              color="success"
              icon="tabler-arrows-right-left"
              :date-update="props.rates.COPC.dateUpdate"
              :date-color="props.rates.COPC.dateColor"
              :is-manual="true"
              :loading="!!loadingButtons['COPC']"
              @submit-manual="submitManualRate('COPC', copcInput)"
            />
          </VCol>

          <!-- COP Sueldo (COPS) -->
          <VCol cols="12" sm="6" md="4" class="pa-1">
            <ExchangeRateCard
              v-model="copsInput"
              title="COP Sueldo (COPS)"
              badge-text="Tasa Nómina"
              :rate-value="props.rates.COPS.rate"
              :decimals="2"
              subtext="Conversión USD a COP en Nómina"
              color="teal"
              icon="tabler-cash"
              :date-update="props.rates.COPS.dateUpdate"
              :date-color="props.rates.COPS.dateColor"
              :is-manual="true"
              :loading="!!loadingButtons['COPS']"
              @submit-manual="submitManualRate('COPS', copsInput)"
            />
          </VCol>
        </template>
      </VRow>
    </div>
  </div>
</template>

