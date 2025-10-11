<script setup>
import FormExchangeRate from '@/components/FormExchangeRate.vue';
import axios from '@/plugins/axios';

const dollar = ref(0)
const bolivares = ref(0)
const pesos = ref(0)

const dateUpdateDollar = ref("")
const dateUpdatePesos = ref("")

const dateColorDollar = ref('success');
const dateColorPesos = ref('success');

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

    dateColorDollar.value = esHoy ? 'success' : 'warning';
    dollar.value = promedio
    dateUpdateDollar.value = fecha

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

    
    pesos.value = parseFloat(promedio)
    dateColorPesos.value = esHoy ? 'success' : 'warning';
    dateUpdatePesos.value = fecha
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
    :dateUpdateDollar="dateUpdateDollar"
    :dateUpdatePesos="dateUpdatePesos"
    :dateColorDollar="dateColorDollar"
    :dateColorPesos="dateColorPesos"
    @refresh="refresh"
    />

</template>




