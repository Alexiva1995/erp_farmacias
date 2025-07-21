<script setup>
import axios from '@/plugins/axios';
import { ref } from 'vue';
const props= defineProps({
  dialog:  {type: Boolean, required: true},
  product: {type: Object, requerired: true},
})


const emit = defineEmits(["refresh", "close-modal"])

const percentage = ref()

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

console.log(props.product);

</script>


<template>
  <div>
    <VDialog

      v-model="props.dialog" 
      max-width="600px"
    >
      <VCard class="shadow-lg bg-white" style="padding: 2em;">
          <h3>Editar rentabilidad</h3>
          
            <VNumberInput v-model="percentage" :label="props.product.percentage" :placeholder="props.product.percentage" />
            
        <v-card-actions class="justify-between">
          <VBtn text="Cerrar" @click="emit('close-modal')" />
          <VBtn 
            text="actualizar" 
            @click="updateProfitability"
          />
        </v-card-actions>
      </VCard>
    </VDialog>
  </div>
</template>
