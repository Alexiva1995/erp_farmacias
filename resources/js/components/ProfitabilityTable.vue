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



const emit = defineEmits(['refresh', "update:options", "editProduct"]);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory.name", sortable: true },
  { title: "Costo", key: "cost_price", sortable: true },
  { title: "Precio Venta", key: "sale_price", sortable: true },
  { title: 'Utilidad', key: 'profitability', sortable: true  },
  { title: "Acciones", key: "actions", sortable: false },
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

const formatPrice = (price) => {
  return new Intl.NumberFormat("es-CO", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
};

const calculateSalePriceWithIva = (product) => {
  const basePrice = Number(product.sale_price || 0);

  if (product.iva == 1) {
    const priceWithIva = basePrice * 1.16;
    return priceWithIva.toFixed(2);
  }

  return basePrice.toFixed(2);
};

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
      @update:options="(options) => emit('update:options', options)"
      >  
      <template #item.id="{ item }">
        <span :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-error' : 'font-weight-medium']">{{ item.id }}</span>
      </template>
      <!--template #item.name="{ item }">
        <span class="font-weight-medium" >{{ item.name }}</span>
      </template-->
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-error' : 'font-weight-medium']"
            >
              {{ item.name }}

              <span v-if="item.iva == 1"> (G)</span>

              <span v-if="item.is_colombian_origin == 1"> (COL)</span>
            </span>

            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template> 
      <template #item.laboratory.name="{ item }">
        <span :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-error' : 'font-weight-medium']">{{ item.laboratory ? item.laboratory.name : ""}}</span>
      </template>
      <template #item.cost_price="{ item }">
        <span :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-error' : 'font-weight-medium']">{{
          formatPrice(item.unit_cost)
        }}</span>
      </template>
      <template #item.sale_price="{ item }">
        <div class="d-flex flex-column">
          <span :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-error' : 'font-weight-medium']">
            {{ formatPrice(calculateSalePriceWithIva(item)) }}
          </span>
          <span v-if="item.iva == 1" class="text-xs text-success">
            (IVA incluido)
          </span>
        </div>
      </template>
      <template #item.profitability="{ item }">
        <span :class="[item.profitability?.is_locked == '1' ? 'font-weight-medium text-error' : 'font-weight-medium']">
          {{ item.profitability?.is_locked == '1' ? 
            (parseFloat(item.sale_price) + (parseFloat(item.sale_price) * (parseInt(item.profitability.profitability_percentage)/100))).toFixed(2) 
            : (parseFloat(item.sale_price) + (parseFloat(item.sale_price) * (parseInt(profitability)/100))).toFixed(2) }}
            ({{ item.profitability?.is_locked == '1' ? parseInt(item.profitability.profitability_percentage) : parseInt(profitability) }}%)
        </span>
      </template>
      <template #item.actions="{ item }" >
        <IconBtn @click="emit('editProduct', item.profitability?.id, item.profitability?.profitability_percentage, item.id, item.profitability?.is_locked)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
        <IconBtn color="primary" icon @click="productExistProfitability(item.id,  item.profitability?.id, profitability, item.profitability?.is_locked)">
          <FontAwesomeIcon :class="[item.profitability?.is_locked == '1' ? 'text-lg text-error' : 'text-lg']" :icon="['fas', item.profitability?.is_locked == '1' ? 'lock' : 'unlock']" />
        </IconBtn>
      </template>
    </VDataTableServer>

</template>
