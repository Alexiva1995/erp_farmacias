<script setup>
import axios from '@/plugins/axios';

const props = defineProps({
  products: { type: Array, required: true },
  profitability: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(['refresh']);

const headers = [
  { title: 'Locked', key: 'products' },
  { title: 'ID', key: 'id' },
  { title: 'Monto', key: 'sale_price' },
  { title: '% Utilidad', key: 'profitability' }, 
  { title: 'Fecha', key: 'valid_stock' },
];

function storeProfitability(product_id, profitability, id = null, is_locked = 0){

  let locked = 0
  if (is_locked == 0) {
    locked = 1
  }
  else {
    locked = 0
  }

  let data = {
    "id": id,
    "product_id": product_id,
    "profitability_percentage": profitability,
    "is_locked" : locked
  };
  
  console.log(data)
  try {
    const response = axios.post("/finances/profitability/product/store", data);
    
    console.log('Éxito:', response.data);
    emit("refresh")
    
  } catch (error) {
    console.error('Error en la solicitud:', error);
    
    if (error.response) {
      // El servidor respondió con un código de error
      console.error('Datos del error:', error.response.data);
      console.error('Status:', error.response.status);
      console.error('Headers:', error.response.headers);
      
      if (error.response.status === 405) {
        console.error('Sugerencia: Prueba con PUT/PATCH en lugar de POST');
      }
    } else if (error.request) {
      // La solicitud fue hecha pero no hubo respuesta
      console.error('No se recibió respuesta del servidor');
    } else {
      // Hubo un error al configurar la solicitud
      console.error('Error al configurar la solicitud:', error.message);
    }
  }
}

</script>

<template>

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap"
      >
      <template #item.products="{ item }">
        <Vbtn color="primary" icon @click="storeProfitability(item.id, profitability, item.profitability?.id, item.profitability?.is_locked)">
          {{ item.profitability?.is_locked == '1' ? 'lock' : 'unlock' }}
        </Vbtn>
      </template>
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>    
      <template #item.sale_price="{ item }">
        <span class="font-weight-medium">${{ item.cost_price }}</span>
      </template>
      <template #item.profitability="{ item }">
        <span class="font-weight-medium">
          ${{ item.profitability?.is_locked == '1' ? parseFloat((item.cost_price) + (item.cost_price * (item.profitability.profitability_percentage/100).toFixed(2)))
          : parseFloat((item.cost_price) + (item.cost_price * (profitability/100).toFixed(2))) }}
        </span>
      </template>
      <template #item.valid_stock="{ item }">
        <span class="font-weight-medium ">{{ new Date(item.created_at).toLocaleDateString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}</span>
      </template>
    </VDataTableServer>

</template>
