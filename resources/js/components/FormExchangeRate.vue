<script setup>
import axios from "@/plugins/axios";
import Swal from "sweetalert2";

const props = defineProps({
  dollar: { type: Number, required: true },
  pesos: { type: Number, required: true },
  euros: { type: Number, required: false, default: 0 },
  idDollar: { type: Number, required: false },
  idPesos: { type: Number, required: false },
  idEuros: { type: Number, required: false },
  dateUpdateDollar: { type: Date, required: false },
  dateUpdatePesos: { type: Date, required: false },
  dateUpdateEuros: { type: Date, required: false },
  dateColorDollar: { type: String, required: true },
  dateColorPesos: { type: String, required: true },
  dateColorEuros: { type: String, default: "success" },
});

const emit = defineEmits(["refresh"]);

const pesos = ref();
const euros = ref();

const sudmitPesos = async () => {
  let data = {
    id: props.idPesos,
    currency_code: "COP",
    rate: parseFloat(pesos.value),
  };

  try {
    const response = await axios.post("/finances/exchange-rates/store", data);

    Swal.fire("Se ha actualizado el peso");
    console.log(response);
    setTimeout(() => {
      emit("refresh");
    }, 150);
    pesos.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  }
};

const submitEuros = async () => {
  let data = {
    id: props.idEuros,
    currency_code: "EUR",
    rate: parseFloat(euros.value),
  };

  try {
    const response = await axios.post("/finances/exchange-rates/store", data);

    Swal.fire("Se ha actualizado el euro");
    console.log(response);
    setTimeout(() => {
      emit("refresh");
    }, 150);
    euros.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  }
};

const updateBCVDollar = async () => {
  let data = {
    exchange_id: props.idDollar,
    currency_code: "BS",
  };

  try {
    const response = await axios.post(
      "/finances/exchange-rates/updateBCVDollar",
      data,
    );

    Swal.fire("Se ha actualizado el dolar BCV");
    console.log(response);
    setTimeout(() => {
      emit("refresh");
    }, 150);
  } catch (error) {
    console.error("Error al enviar:", error);
  }
};
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
            <VCol cols="6" class="mb-1">
              <label class="text-sm ml-2"
                >Dolar BCV
                <VChip :color="dateColorDollar">{{
                  dateUpdateDollar
                }}</VChip></label
              >
            </VCol>

            <VCol cols="6" class="mb-1">
              <label class="text-sm ml-2"
                >COP
                <VChip :color="dateColorPesos">{{
                  dateUpdatePesos
                }}</VChip></label
              >
            </VCol>

            <VCol cols="4">
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

            <VCol cols="4">
              <VTextField
                id="pesos"
                v-model="pesos"
                :placeholder="props.pesos"
                persistent-placeholder
                class="mb-2 mt-2"
              />
            </VCol>

            <VCol cols="2">
              <VBtn type="button" @click="sudmitPesos" class="my-2 ml-2">
                Actualizar
              </VBtn>
            </VCol>

            <!-- EUR row -->
            <VCol cols="6" class="mb-1 mt-2">
              <label class="text-sm ml-2"
                >Euro (EUR)
                <VChip :color="dateColorEuros">{{
                  dateUpdateEuros
                }}</VChip></label
              >
            </VCol>
            <VCol cols="6" />

            <VCol cols="4">
              <VTextField
                id="euros"
                v-model="euros"
                :placeholder="String(props.euros)"
                persistent-placeholder
                class="mb-2 mt-2"
              />
            </VCol>

            <VCol cols="2">
              <VBtn type="button" @click="submitEuros" class="my-2 ml-2">
                Actualizar
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
