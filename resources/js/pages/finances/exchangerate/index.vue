<script setup>
import FormExchangeRate from '@/components/FormExchangeRate.vue';
import axios from '@/plugins/axios';

const dollar = ref(0)
const bolivares = ref(0)
const pesos = ref(0)
const dateUpdate = ref("")
const dateColor = ref('success');

const getDollarBCV = async () => {
  try {
    const response = await axios.get(
      'http://127.0.0.1:8000/api/finances/exchange-rates/consultOneBCV'
    );

    const fechaRecibida = new Date(response.data.created_at);
    const hoy = new Date();

    // Normaliza ambas fechas a solo año, mes y día
    const esHoy =
      fechaRecibida.getFullYear() === hoy.getFullYear() &&
      fechaRecibida.getMonth() === hoy.getMonth() &&
      fechaRecibida.getDate() === hoy.getDate();

    

    let promedio = response.data.rate
    let fecha = fechaRecibida.toLocaleDateString('es-VE', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });

    dateColor.value = esHoy ? 'success' : 'warning';
    dollar.value = promedio
    dateUpdate.value = fecha

  } catch (error) {
    console.error('Hubo un error al obtener la rentabilidad:', error);
  }
}

const getCOP = async () => {
  try {
    const response = await axios.get(
      'http://127.0.0.1:8000/api/finances/exchange-rates/consultOneCOP'
    );
    //profitability.value = response.data.default_profitability_percentage;
    let promedio = response.data.rate
    console.log(promedio)
    pesos.value = parseFloat(promedio)
  } catch (error) {
    console.error('Hubo un error al obtener la rentabilidad:', error);
  }
}

function refresh() {
  getCOP();
  getDollarBCV();
}

onMounted(() => {
  refresh()
});

</script>

<template>

  
    <FormExchangeRate
    :pesos="pesos"
    :bolivares="bolivares"
    :dollar="dollar"
    :dateUpdate="dateUpdate"
    :dateColor="dateColor"
    @refresh="refresh"
    />

</template>




