<script setup>
import FormExchangeRate from '@/components/FormExchangeRate.vue';
import axios from '@/plugins/axios';

const dollar = ref(0)
const pesos = ref(0)

const idDollar = ref(null)
const idPesos = ref(null)

const dateUpdateDollar = ref("")
const dateUpdatePesos = ref("")

const dateColorDollar = ref('success');
const dateColorPesos = ref('success');

const getDollarBCV = async () => {
  try {
    const response = await axios.get(
      '/finances/exchange-rates/consultOneBCV'
    );

    const fechaRecibida = new Date(response.data.updated_at);
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
    idDollar.value = response.data.id
    dateUpdateDollar.value = fecha

  } catch (error) {
    console.error('Hubo un error al obtener la rentabilidad:', error);
  }
}

const getCOP = async () => {
  try {
    const response = await axios.get(
      '/finances/exchange-rates/consultOneCOP'
    );
    //profitability.value = response.data.default_profitability_percentage;
    const fechaRecibida = new Date(response.data.updated_at);
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
    idPesos.value = response.data.id
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
    :idDollar="idDollar"
    :idPesos="idPesos"
    :dateUpdateDollar="dateUpdateDollar"
    :dateUpdatePesos="dateUpdatePesos"
    :dateColorDollar="dateColorDollar"
    :dateColorPesos="dateColorPesos"
    @refresh="refresh"
    />

</template>




