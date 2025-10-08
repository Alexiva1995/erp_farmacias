<template>
  <VDialog v-model="isOpen" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <VIcon icon="tabler-currency-dollar" class="me-2" />
          <span>Tasas de Cambio</span>
        </div>
        <VBtn icon variant="text" size="small" @click="closeModal">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <div v-if="loading" class="text-center py-4">
          <VProgressCircular indeterminate color="primary" />
          <p class="mt-2">Cargando tasas de cambio...</p>
        </div>

        <div v-else-if="error" class="text-center py-4">
          <VIcon icon="tabler-alert-circle" size="48" color="error" />
          <p class="mt-2 text-error">{{ error }}</p>
          <VBtn color="primary" @click="fetchExchangeRates" class="mt-2">
            Reintentar
          </VBtn>
        </div>

        <div v-else-if="exchangeRates.length === 0" class="text-center py-4">
          <VIcon icon="tabler-currency-dollar-off" size="48" color="warning" />
          <p class="mt-2">No hay tasas de cambio disponibles</p>
        </div>

        <div v-else>
          <div class="mb-4">
            <h3 class="text-h6 mb-2">Tasas de Cambio Actuales</h3>
            <p class="text-body-2 text-medium-emphasis">
              Última actualización: {{ lastUpdated }}
            </p>
          </div>

          <VTable>
            <thead>
              <tr>
                <th class="text-left">Moneda</th>
                <th class="text-left">Código</th>
                <th class="text-right">Tasa (USD)</th>
                <th class="text-left">Fuente</th>
                <th class="text-left">Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rate in exchangeRates" :key="rate.currency_code">
                <td>
                  <div class="d-flex align-center">
                    <VIcon
                      :icon="getCurrencyIcon(rate.currency_code)"
                      class="me-2"
                      :color="getCurrencyColor(rate.currency_code)"
                    />
                    {{ getCurrencyName(rate.currency_code) }}
                  </div>
                </td>
                <td>
                  <VChip
                    :color="getCurrencyColor(rate.currency_code)"
                    size="small"
                    variant="tonal"
                  >
                    {{ rate.currency_code }}
                  </VChip>
                </td>
                <td class="text-right">
                  <span class="text-h6">
                    {{ formatRate(rate.rate) }}
                  </span>
                </td>
                <td>
                  <VChip
                    :color="getSourceColor(rate.source)"
                    size="small"
                    variant="outlined"
                  >
                    {{ rate.source }}
                  </VChip>
                </td>
                <td>
                  <VChip color="success" size="small" variant="tonal">
                    Activo
                  </VChip>
                </td>
              </tr>
            </tbody>
          </VTable>

          <div class="mt-4 pa-4 bg-grey-lighten-5 rounded">
            <h4 class="text-subtitle-1 mb-2">Información de Conversión</h4>
            <p class="text-body-2 mb-2">
              <strong>BS (Bolívar Venezolano):</strong> {{ getRate("BS") }} BS =
              1 USD
            </p>
            <p class="text-body-2 mb-2">
              <strong>COP (Peso Colombiano):</strong> {{ getRate("COP") }} COP =
              1 USD
            </p>
            <p class="text-body-2">
              <strong>USD (Dólar Americano):</strong> {{ getRate("USD") }} USD =
              1 USD
            </p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="primary" variant="outlined" @click="closeModal">
          Cerrar
        </VBtn>
        <VBtn color="primary" @click="refreshRates" :loading="loading">
          <VIcon icon="tabler-refresh" class="me-1" />
          Actualizar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

// Props
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

// Emits
const emit = defineEmits(["update:modelValue"]);

// Estado reactivo
const loading = ref(false);
const error = ref(null);
const exchangeRates = ref([]);
const lastUpdated = ref("");

// Computed
const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

// Métodos
const fetchExchangeRates = async () => {
  loading.value = true;
  error.value = null;

  try {
    console.log("🔍 Haciendo petición a: /public/exchange-rates");
    const response = await axios.get("/public/exchange-rates");

    console.log("📊 Respuesta recibida:", response);
    console.log("📊 response.data:", response.data);
    console.log("📊 Tipo de response.data:", typeof response.data);
    console.log("📊 Es array:", Array.isArray(response.data));

    if (response.data && Array.isArray(response.data)) {
      exchangeRates.value = response.data;
      lastUpdated.value = new Date().toLocaleString("es-VE");
      console.log(
        "✅ Tasas de cambio cargadas exitosamente:",
        exchangeRates.value
      );
    } else {
      console.error(
        "❌ Formato de respuesta inválido. response.data:",
        response.data
      );
      throw new Error("Formato de respuesta inválido");
    }
  } catch (err) {
    console.error("Error al cargar tasas de cambio:", err);
    error.value =
      err.response?.data?.message ||
      err.message ||
      "Error al cargar las tasas de cambio";
    toast.error("Error al cargar las tasas de cambio");
  } finally {
    loading.value = false;
  }
};

const refreshRates = () => {
  fetchExchangeRates();
};

const closeModal = () => {
  isOpen.value = false;
};

const getCurrencyIcon = (currencyCode) => {
  const icons = {
    BS: "tabler-currency-bolivar",
    COP: "tabler-currency-peso",
    USD: "tabler-currency-dollar",
  };
  return icons[currencyCode] || "tabler-currency";
};

const getCurrencyColor = (currencyCode) => {
  const colors = {
    BS: "error",
    COP: "info",
    USD: "success",
  };
  return colors[currencyCode] || "primary";
};

const getCurrencyName = (currencyCode) => {
  const names = {
    BS: "Bolívar Venezolano",
    COP: "Peso Colombiano",
    USD: "Dólar Americano",
  };
  return names[currencyCode] || currencyCode;
};

const getSourceColor = (source) => {
  const colors = {
    Manual: "primary",
    API: "success",
    Automatic: "info",
  };
  return colors[source] || "secondary";
};

const formatRate = (rate) => {
  return parseFloat(rate).toLocaleString("es-VE", {
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
  });
};

const getRate = (currencyCode) => {
  const rate = exchangeRates.value.find(
    (r) => r.currency_code === currencyCode
  );
  return rate ? formatRate(rate.rate) : "N/A";
};

// Watchers
watch(isOpen, (newValue) => {
  if (newValue) {
    fetchExchangeRates();
  }
});
</script>

<style scoped>
.v-table th {
  font-weight: 600;
}

.v-table td {
  vertical-align: middle;
}
</style>
