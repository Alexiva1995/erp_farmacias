<script setup>
import axios from '@/plugins/axios';
import { library } from '@fortawesome/fontawesome-svg-core';
import { faLock, faUnlock } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

library.add(faLock, faUnlock)

const props = defineProps({
  products: { type: Array, required: true },
  profitability: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

//console.log(props)

const emit = defineEmits(['refresh']);

const headers = [
  { title: 'Bloquear', key: 'products' },
  { title: 'ID', key: 'id' },
  { title: 'Producto', key: 'name' },
  { title: 'Monto', key: 'sale_price' },
  { title: '% Utilidad', key: 'profitability' }, 
  { title: 'Fecha', key: 'valid_stock' },
];

function storeProfitability(product_id, profitability){

  let data = {
    "product_id": product_id,
    "profitability_percentage": profitability,
    "is_locked" : 1
  };
  
  //console.log(data)
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

function editProfitability(product_id, profitability, profitability_id, is_locked){


  if (is_locked == 1) {
    is_locked = 0
  }
  else {
    is_locked = 1
  }

  let data = {
    "id": profitability_id,
    "product_id": product_id,
    "profitability_percentage": profitability,
    "is_locked" : is_locked
  };
  
  console.log(data)
  try {
    const response = axios.post("/finances/profitability/product/update", data);
    
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

const productExistProfitability = async (product_id = null, profitability_id, profitability, is_locked = null) => {

try {
  const response = await axios.get(`/finances/profitability/product/${product_id}`);

    if (response.status === 200) {
      //console.log("producto id" . product_id)
      //console.log("Rentabilida ". profitability)
      //console.log("Is Locked ". is_locked)
      //console.log("Editar")
      editProfitability(product_id, profitability, profitability_id, is_locked);
    }
  } catch (error) {
    //console.log(product_id)
    //console.log(profitability)
    //console.log("Crear")
    storeProfitability(product_id, profitability)
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
        <Vbtn color="primary" icon @click="productExistProfitability(item.id,  item.profitability?.id, profitability, item.profitability?.is_locked)">
          <FontAwesomeIcon :class="[item.profitability?.is_locked == '1' ? 'text-lg text-error' : 'text-lg']" :icon="['fas', item.profitability?.is_locked == '1' ? 'lock' : 'unlock']" />
        </Vbtn>
      </template>
      <template #item.id="{ item }">
        <span class="font-weight-medium" :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-lg text-error' : 'font-weight-medium']">{{ item.id }}</span>
      </template>    
      <template #item.name="{ item }">
        <span class="font-weight-medium" :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-lg text-error' : 'font-weight-medium']">{{ item.name }}</span>
      </template> 
      <template #item.sale_price="{ item }">
        <span :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-lg text-error' : 'font-weight-medium']">${{ item.cost_price }}</span>
      </template>
      <template #item.profitability="{ item }">
        <span class="font-weight-medium" :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-lg text-error' : 'font-weight-medium']">
          ${{ item.profitability?.is_locked == '1' ? 
            parseFloat(item.cost_price) + (parseFloat(item.cost_price) * (parseInt(item.profitability.profitability_percentage)/100))
            : parseFloat(item.cost_price) + (parseFloat(item.cost_price) * (parseInt(profitability)/100)) }}
        </span>
      </template>
      <template #item.valid_stock="{ item }">
        <span class="" :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-lg text-error' : 'font-weight-medium']">{{ new Date(item.created_at).toLocaleDateString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}</span>
      </template>
    </VDataTableServer>

</template>
