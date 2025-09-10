<script setup>
import axios from '@/plugins/axios';

const props = defineProps({
  dollar: { type: Number, required: true },
  //bolivares: { type: Number, required: true },
  pesos: { type: Number, required: true },
  dateUpdate : { type: Date, required: false },
  dateColor: { type: String, required: true },
});

const emit = defineEmits(["refresh"]);

const pesos = ref()

const sudmitPesos = async () => {

    let data = {
      "currency_code": "COP",
      "rate" : parseFloat(pesos.value)
    }
    //console.log(data)
    // Aquí podrías enviar datos como props.pesos
    
    try {
      const response = await axios.post('/finances/exchange-rates/store', data)
      
      console.log('¡Pesos enviados!', response.data)

      emit("refresh")
    } catch (error) {
      console.error('Error al enviar:', error)
    }
}

const updateBCVDollar = async () => {

    let data = {
      "currency_code": "USD",
    }
    //console.log(data)
    // Aquí podrías enviar datos como props.pesos
    
    try {
      const response = await axios.post('/finances/exchange-rates/updateBCVDollar', data)
      
      console.log('¡Pesos enviados!', response.data)

      emit("refresh")
    } catch (error) {
      console.error('Error al enviar:', error)
    }
}

</script>

<template>

  <VRow>
    <VCol cols="12">
      <VCard class="px-4 py-4">
        <VCardTitle>
          <span class="mr-2">Tasa de Cambio</span>
        </VCardTitle>
        <VCardText>
          
            <label class="text-sm">Dolar banco centrar <VChip :color="dateColor">{{ dateUpdate }}</VChip></label>
            <VTextField
              id="firstName"
              v-model="props.dollar"
              placeholder="$"
              persistent-placeholder
              class="mb-2 mt-2"
            />

            <VRow no-gutters>

              <VCol cols="12">
                <VBtn @click="updateBCVDollar">
                  Actualizar
                </VBtn>
              </VCol>
            </VRow>
          
        <!--/VCardText>
      </VCard>
    </VCol>

    
    <VCol cols="12">
      <VCard class="px-4 py-4">
        <VCardTitle>
          <span class="mr-2">Precio del Dolar a BCV</span>
          <VChip color="success" >12:00</VChip>
        </VCardTitle > 
        <VCardText-->
          
            <label class="mb-1 text-sm">Bolivares</label>
            <VTextField
              id="firstName"
              v-model="props.bolivares"
              :placeholder="props.dollar"
              persistent-placeholder
              class="mb-2"
            />

            
            <label class="mb-1 text-sm">COP</label>
            <VTextField
              id="firstName"
              v-model="pesos"
              :placeholder="props.pesos"
              persistent-placeholder
              class="mb-2"
            />

            <VRow no-gutters>

              <VCol cols="6">
              
                <VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                  class="me-4 w-100 mr-2"
                >
                  cancelar
                </VBtn>

              </VCol>

                
              <VCol cols="6">

                <VBtn
                  type="button"
                  @click="sudmitPesos"
                  class="me-4 w-100 ml-2"
                >
                  Establecer
                </VBtn>

              </VCol>


            </VRow>
          
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  
</template>
