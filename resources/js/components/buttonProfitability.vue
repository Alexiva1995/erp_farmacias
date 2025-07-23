<script setup>
import axios from '@/plugins/axios';
import { ref } from 'vue';
const props= defineProps({
  //modalFormulario: {type: Boolean, required: true},
  percentage: {type: Number, default: () => 0},
  //companies: {type: Array, required: true},
  //formData: {type: Object, default: () => []},
  //formError: {type: Object, default: () => []},
})

const emit = defineEmits(["reloadTable"])

const dialog = ref(false)

const percentage = ref(0)

async function storeProfitability() {

  let data = {
    "default_profitability_percentage": percentage.value,
  };
  
  console.log(data);
  
  try {
    const response = await axios.post("/finances/profitability/store", data);
    
    console.log('Éxito:', response.data);
    emit("reloadTable")
    
  } catch (error) {
    console.error('Error en la solicitud:', error);
    
    if (error.response) {
      // El servidor respondió con un código de error
      console.error('Datos del error:', error.response.data);
      console.error('Status:', error.response.status);
      console.error('Headers:', error.response.headers);
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
  <div>
    <v-btn color="primary" @click="dialog = true">Cambiar Rentabilidad</v-btn>

    <VDialog

      v-model="dialog" 
      max-width="600px"
    >
      <VCard class="shadow-lg bg-white" style="padding: 2em;">
          <h3>Asignar rentabilidad</h3>
          
            <VNumberInput v-model="percentage" label="porcentaje" placeholder="25%" />
            
        <v-card-actions class="justify-between">
          <VBtn text="Cerrar" @click="dialog = false" />
          <VBtn 
            text="Agregar" 
            @click="storeProfitability" 
          />
        </v-card-actions>
      </VCard>
    </VDialog>
  </div>
</template>




