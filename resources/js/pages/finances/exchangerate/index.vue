<script setup>
import FormExchangeRate from "@/components/FormExchangeRate.vue";
import axios from "@/plugins/axios";
import { onMounted, ref } from "vue";

const dollar = ref(0);
const pesos = ref(0);
const euros = ref(0);
const copc = ref(0);
const binance = ref(0);

const idDollar = ref(null);
const idPesos = ref(null);
const idEuros = ref(null);
const idCopc = ref(null);
const idBinance = ref(null);

const dateUpdateDollar = ref("");
const dateUpdatePesos = ref("");
const dateUpdateEuros = ref("");
const dateUpdateCopc = ref("");
const dateUpdateBinance = ref("");

const dateColorDollar = ref("success");
const dateColorPesos = ref("success");
const dateColorEuros = ref("success");
const dateColorCopc = ref("success");
const dateColorBinance = ref("success");

const getDollarBCV = async () => {
  try {
    const response = await axios.get("/finances/exchange-rates/consultOneBCV");

    const fechaRecibida = new Date(response.data.updated_at);
    const hoy = new Date();

    const esHoy =
      fechaRecibida.getFullYear() === hoy.getFullYear() &&
      fechaRecibida.getMonth() === hoy.getMonth() &&
      fechaRecibida.getDate() === hoy.getDate();

    let promedio = response.data.rate;
    let fecha = fechaRecibida.toLocaleDateString("es-VE", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });

    dateColorDollar.value = esHoy ? "success" : "warning";
    dollar.value = promedio;
    idDollar.value = response.data.id;
    dateUpdateDollar.value = fecha;
  } catch (error) {
    console.error("Hubo un error al obtener la tasa del dólar:", error);
  }
};

const getCOP = async () => {
  try {
    const response = await axios.get("/finances/exchange-rates/consultOneCOP");
    const fechaRecibida = new Date(response.data.updated_at);
    const hoy = new Date();

    const esHoy =
      fechaRecibida.getFullYear() === hoy.getFullYear() &&
      fechaRecibida.getMonth() === hoy.getMonth() &&
      fechaRecibida.getDate() === hoy.getDate();

    let promedio = response.data.rate;
    let fecha = fechaRecibida.toLocaleDateString("es-VE", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });

    pesos.value = parseFloat(promedio);
    idPesos.value = response.data.id;
    dateColorPesos.value = esHoy ? "success" : "warning";
    dateUpdatePesos.value = fecha;
  } catch (error) {
    console.error("Hubo un error al obtener la tasa COP:", error);
  }
};

const getEUR = async () => {
  try {
    const response = await axios.get("/finances/exchange-rates/consultOneEUR");
    if (!response.data) return;

    const fechaRecibida = new Date(response.data.updated_at);
    const hoy = new Date();

    const esHoy =
      fechaRecibida.getFullYear() === hoy.getFullYear() &&
      fechaRecibida.getMonth() === hoy.getMonth() &&
      fechaRecibida.getDate() === hoy.getDate();

    let fecha = fechaRecibida.toLocaleDateString("es-VE", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });

    euros.value = parseFloat(response.data.rate);
    idEuros.value = response.data.id;
    dateColorEuros.value = esHoy ? "success" : "warning";
    dateUpdateEuros.value = fecha;
  } catch (error) {
    console.error("Hubo un error al obtener la tasa EUR:", error);
  }
};

const getCOPC = async () => {
  try {
    const response = await axios.get("/finances/exchange-rates/consultOneCOPC");
    if (!response.data) return;

    const fechaRecibida = new Date(response.data.updated_at);
    const hoy = new Date();

    const esHoy =
      fechaRecibida.getFullYear() === hoy.getFullYear() &&
      fechaRecibida.getMonth() === hoy.getMonth() &&
      fechaRecibida.getDate() === hoy.getDate();

    let fecha = fechaRecibida.toLocaleDateString("es-VE", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });

    copc.value = parseFloat(response.data.rate);
    idCopc.value = response.data.id;
    dateColorCopc.value = esHoy ? "success" : "warning";
    dateUpdateCopc.value = fecha;
  } catch (error) {
    console.error("Hubo un error al obtener la tasa COPC:", error);
  }
};

const getBINANCE = async () => {
  try {
    const response = await axios.get("/finances/exchange-rates/consultOneBINANCE");
    if (!response.data) return;

    const fechaRecibida = new Date(response.data.updated_at);
    const hoy = new Date();

    const esHoy =
      fechaRecibida.getFullYear() === hoy.getFullYear() &&
      fechaRecibida.getMonth() === hoy.getMonth() &&
      fechaRecibida.getDate() === hoy.getDate();

    let fecha = fechaRecibida.toLocaleDateString("es-VE", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });

    binance.value = parseFloat(response.data.rate);
    idBinance.value = response.data.id;
    dateColorBinance.value = esHoy ? "success" : "warning";
    dateUpdateBinance.value = fecha;
  } catch (error) {
    console.error("Hubo un error al obtener la tasa de Binance:", error);
  }
};

const loading = ref(false);

async function refresh() {
  loading.value = true;
  try {
    await Promise.all([
      getCOP(),
      getDollarBCV(),
      getEUR(),
      getCOPC(),
      getBINANCE()
    ]);
  } catch (error) {
    console.error("Error al refrescar las tasas:", error);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  refresh();
});
</script>

<template>
  <FormExchangeRate
    :pesos="pesos"
    :dollar="dollar"
    :euros="euros"
    :binance="binance"
    :idDollar="idDollar"
    :idPesos="idPesos"
    :idEuros="idEuros"
    :idBinance="idBinance"
    :dateUpdateDollar="dateUpdateDollar"
    :dateUpdatePesos="dateUpdatePesos"
    :dateUpdateEuros="dateUpdateEuros"
    :dateUpdateBinance="dateUpdateBinance"
    :dateColorDollar="dateColorDollar"
    :dateColorPesos="dateColorPesos"
    :dateColorEuros="dateColorEuros"
    :dateColorBinance="dateColorBinance"
    :copc="copc"
    :idCopc="idCopc"
    :dateUpdateCopc="dateUpdateCopc"
    :dateColorCopc="dateColorCopc"
    @refresh="refresh"
  />
</template>
