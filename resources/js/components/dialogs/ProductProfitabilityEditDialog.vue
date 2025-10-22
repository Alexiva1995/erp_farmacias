<script setup>
import axios from '@/plugins/axios';
import { ref } from 'vue';
const props= defineProps({
  dialog:  {type: Boolean, required: true},
  product: {type: Object, requerired: true},
})


const emit = defineEmits(["refresh", "close-modal"])

const percentage = ref()

function storeProfitability(){

  let data = {
    "product_id": props.product.product_id,
    "profitability_percentage": percentage.value,
    "is_locked" : 1
  };
  
  //console.log(data)
  try {
    const response = axios.post("/finances/profitability/product/store", data);
    
    console.log('Éxito:', response.data);
    emit('close-modal')
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

async function updateProfitability() {

  let data = {
    "id": props.product.id,
    "product_id": props.product.product_id,
    "profitability_percentage": percentage.value,
    "is_locked" : props.product.is_locked
  };
  
  console.log(data)

  try {
    const response = axios.post("/finances/profitability/product/update", data);
    
    console.log('Éxito:', response.data);
    emit('close-modal')
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

const productExistProfitability = async () => {

try {
  const response = await axios.get(`/finances/profitability/product/${props.product.product_id}`);

    if (response.status === 200) {
      console.log("estas editando")
      updateProfitability();

    }
  } catch (error) {
      console.log("estas creando")
      storeProfitability()

  }

}

console.log(props.product);

</script>


<template>
  <div>
    <VDialog

      v-model="props.dialog" 
      max-width="800px"
    >
      <VCard class="d-flex flex-column">
        <VCardTitle class="d-flex align-center">
          <span class="text-h5 font-weight-bold">{{
            props.product.id ? "Crear Rentabilidad" : "Editar Rentabilidad"
          }}</span>
          <VSpacer />
          <VBtn icon variant="text" @click="emit('close-modal')">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        
        </VCardTitle>

        <VDivider />

        <VCardText class="d-flex">

          <!--VNumberInput v-model="percentage" :label="props.product.percentage" :placeholder="props.product.percentage" /-->
          <VTextField
              label="Rentabilidad"
              v-model="percentage"
              :label="props.product.percentage"
              :placeholder="props.product.percentage"
              type="text"
          />
              
          <!--v-card-actions class="justify-between">
            <VBtn text="Cerrar" @click="emit('close-modal')" />
            <VBtn 
              text="actualizar" 
              @click="productExistProfitability"
            />
          </v-card-actions-->
        </VCardText>

        <VDivider />

      <!-- El VCardActions se mantiene igual, será el pie de página fijo -->
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="emit('close-modal')"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="productExistProfitability"
          class="flex-grow-1 w-0"
        >
          Guardar
        </VBtn>
      </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
