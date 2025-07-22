<script setup>
import axios from '@/plugins/axios';

const props = defineProps({
  dollar: { type: Number, required: true },
  //bolivares: { type: Number, required: true },
  pesos: { type: Number, required: true },
});

const pesos = ref()

const sudmitPesos = async () => {

    let data = {
      "currency_code": "COP",
      "rate" : parseFloat(pesos.value)
    }
    console.log(data)
    // Aquí podrías enviar datos como props.pesos
    try {
      const response = await axios.post('/finances/exchange-rates/store', data)

      console.log('¡Pesos enviados!', response.data)
    } catch (error) {
      console.error('Error al enviar:', error)
    }
}

</script>

<template>
  <VForm ref="formRef" @submit.prevent="() => {}">
    <VRow>
      <VCol cols="12">
        <VRow no-gutters>
          <!-- 👉 First Name -->
          <!--VCol
            cols="12"
            md="3"
            class="d-flex align-items-center"
          >
            <label
              class="v-label text-body-2 text-high-emphasis"
              for="firstName"
            >Dolar BCV</label>
          </VCol>

          <VCol
            cols="12"
            md="9"
          >
            <AppTextField
              id="firstName"
              v-model="props.dollar"
              placeholder="Bs"
              persistent-placeholder
            />
          </VCol-->
        </VRow>
      </VCol>

      <VCol cols="12">
        <VRow no-gutters>
          <!-- 👉 Email -->
          <VCol
            cols="12"
            md="3"
            class="d-flex align-items-center"
          >
            <label
              class="v-label text-body-2 text-high-emphasis"
              for="dollar"
            >Bolivares (Dolar en BCV)</label>
          </VCol>

          <VCol
            cols="12"
            md="9"
          >
            <AppTextField
              id="dollar"
              v-model="props.dollar"
              placeholder="$"
              persistent-placeholder
            />
          </VCol>
        </VRow>
      </VCol>

      <VCol cols="12">
        <VRow no-gutters>
          <!-- 👉 Mobile -->
          <VCol
            cols="12"
            md="3"
            class="d-flex align-items-center"
          >
            <label
              class="v-label text-body-2 text-high-emphasis"
              for="pesos"
            >Pesos colombianos</label>
          </VCol>

          <VCol
            cols="12"
            md="9"
          >
            <AppTextField
              id="pesos"
              v-model="pesos"
              type="number"
              :placeholder="props.pesos"
              persistent-placeholder
            />
          </VCol>
        </VRow>
      </VCol>

      <!-- 👉 submit and reset button -->
      <VCol cols="12">
        <VRow no-gutters>
          <VCol
            cols="12"
            md="3"
          />
          <VCol
            cols="12"
            md="9"
          >
            <VBtn
              type="submit"
              class="me-4"
              @click="sudmitPesos()"
            >
              Submit
            </VBtn>
            <VBtn
              color="secondary"
              variant="tonal"
              type="reset"
            >
              Reset
            </VBtn>
          </VCol>
        </VRow>
      </VCol>
    </VRow>
  </VForm>
  <!-- 
  <VRow>
    <VCol cols="12">
      <VCard class="px-4 py-4">
        <VCardTitle>
          <span>Tasa de Dolar BCV</span>
          <VChip color="success" >12:00</VChip>
        </VCardTitle>
        <VCardText>
          <VForm>
            <label class="mb-1 text-lg">Bolivares</label>
            <VTextField
              id="firstName"
              v-model="props.dollar"
              placeholder="$"
              persistent-placeholder
              class="mb-2"
            />

            <VRow no-gutters>
              <VCol
                cols="12"
              />
              <VCol
                cols="12"
              >
                <VBtn
                  type="submit"
                  class="me-4"
                >
                  Submit
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                >
                  Reset
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    
    <VCol cols="4">
      <VCard class="px-4 py-4">
        <VCardTitle>
          <span>Precio del Dolar</span>
          <VChip color="success" >12:00</VChip>
        </VCardTitle>
        <VCardText>
          <VForm>
            <label class="mb-1 text-lg">Bolivares</label>
            <VTextField
              id="firstName"
              v-model="props.bolivares"
              placeholder="$"
              persistent-placeholder
              class="mb-2"
            />

            <VRow no-gutters>
              <VCol
                cols="12"
              />
              <VCol
                cols="12"
              >
                <VBtn
                  type="submit"
                  class="me-4"
                >
                  Submit
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                >
                  Reset
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  

    
    <VCol cols="4">
      <VCard class="px-4 py-4">
        <VCardTitle>
          <span>Tasa de Factura</span>
          <VChip color="success" >12:00</VChip>
        </VCardTitle>
        <VCardText>
          <VForm>
            <label class="mb-1 text-lg">COP</label>
            <VTextField
              id="firstName"
              v-model="props.pesos"
              placeholder="$"
              persistent-placeholder
              class="mb-2"
            />

            <VRow no-gutters>
              <VCol
                cols="12"
              />
              <VCol
                cols="12"
              >
                <VBtn
                  type="submit"
                  class="me-4"
                >
                  Submit
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                >
                  Reset
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  -->
</template>
