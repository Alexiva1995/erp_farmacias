<script setup>
import axios from '@/plugins/axios';
import Swal from "sweetalert2";

const props = defineProps({
  dollar: { type: Number, required: true },
  //bolivares: { type: Number, required: true },
  pesos: { type: Number, required: true },
  dateUpdateDollar : { type: Date, required: false },
  dateUpdatePesos : { type: Date, required: false },
  dateColorDollar: { type: String, required: true },
  dateColorPesos: { type: String, required: true },
});

const emit = defineEmits(["refresh"]);

const pesos = ref()

const sudmitPesos = async () => {
    let time;
    let data = {
      "currency_code": "COP",
      "rate" : parseFloat(pesos.value)
    }
    
    try {
      const response = await axios.post('/finances/exchange-rates/store', data)
      
      Swal.fire("Se ha actualizado el peso");
      setTimeout(() => {
        emit("refresh")
        
      }, 150);
      pesos.value = ""
    } catch (error) {
      console.error('Error al enviar:', error)
    }
}

const updateBCVDollar = async () => {

    let data = {
      "currency_code": "USD",
    }
    
    try {
      const response = await axios.post('/finances/exchange-rates/updateBCVDollar', data)
      
      Swal.fire("Se ha actualizado el dolar BCV");
      setTimeout(() => {
        emit("refresh")
        
      }, 150);

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
          
            

            <VRow no-gutters>
              <label class="text-sm">Dolar BCV <VChip :color="dateColorDollar">{{ dateUpdateDollar }}</VChip></label>
              <VCol cols="10">

                
                <VTextField
                  id="dollar"
                  v-model="props.dollar"
                  placeholder="$"
                  persistent-placeholder
                  class="mb-2 mt-2"
                />
              </VCol>
              <VCol cols="2">
                <VBtn @click="updateBCVDollar" class="mb-2 mt-2 ml-2">
                  Actualizar
                </VBtn>
              </VCol>
            </VRow>
        
            

            
            

            <VRow no-gutters>

              <VCol cols="6" class="mb-1" style="padding-top:.1em">
                <label class="text-sm ml-2">Bolivares</label>
              </VCol>

              <VCol cols="6" class="mb-1">
                <label class="text-sm ml-2">COP <VChip :color="dateColorPesos">{{ dateUpdatePesos }}</VChip></label>
              </VCol>

              <VCol cols="6">
                
                
                <VTextField
                  id="bolivares"
                  v-model="props.bolivares"
                  :placeholder="props.dollar"
                  persistent-placeholder
                  class="mb-2 mr-1"
                />
                <!--VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                  class="me-4 w-100"
                >
                  cancelar
                </VBtn-->

              </VCol>
                
              <VCol cols="6">


                
                <VTextField
                  id="pesos"
                  v-model="pesos"
                  :placeholder="props.pesos"
                  persistent-placeholder
                  class="mb-2 ml-1"
                />
                
              </VCol>

              <VCol cols="12">
                <VBtn
                  type="button"
                  @click="sudmitPesos"
                  class="me-4 w-100"
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
