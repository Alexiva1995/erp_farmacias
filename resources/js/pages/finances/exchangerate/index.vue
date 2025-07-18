<script setup>

import axios from '@/plugins/axios';

const dollar = ref(0)
const bolivares = ref(0)
const pesos = ref(0)

const getDollarBCV = async () => {
  try {
    const response = await axios.get(
      'http://127.0.0.1:8000/api/finances/exchange-rates/apiDollar'
    );
    //profitability.value = response.data.default_profitability_percentage;
    let promedio = response.data[0].promedio
    //console.log(promedio)
    dollar.value = promedio
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



onMounted(() => {
  getCOP();
  getDollarBCV();
});

</script>

<template>

  <VCard class="px-5 py-5">
    <FormExchangeRate
    :pesos="pesos"
    :bolivares="bolivares"
    :dollar="dollar"
    />
  </VCard>
</template>




