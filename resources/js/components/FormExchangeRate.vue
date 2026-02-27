<script setup>
import axios from "@/plugins/axios";
import Swal from "sweetalert2";

const props = defineProps({
  dollar: { type: Number, required: true },
  pesos: { type: Number, required: true },
  euros: { type: Number, required: false, default: 0 },
  copc: { type: Number, required: false, default: 0 },
  idDollar: { type: Number, required: false },
  idPesos: { type: Number, required: false },
  idEuros: { type: Number, required: false },
  idCopc: { type: Number, required: false },
  dateUpdateDollar: { type: String, required: false },
  dateUpdatePesos: { type: String, required: false },
  dateUpdateEuros: { type: String, required: false },
  dateUpdateCopc: { type: String, required: false },
  dateColorDollar: { type: String, required: true },
  dateColorPesos: { type: String, required: true },
  dateColorEuros: { type: String, default: "success" },
  dateColorCopc: { type: String, default: "success" },
});

const emit = defineEmits(["refresh"]);

const pesosInput = ref();
const eurosInput = ref();
const copcInput = ref();

const submitPesos = async () => {
  if (!pesosInput.value) {
    Swal.fire({
      icon: "info",
      title: "Actualización Manual Requerida",
      text: "Para COP (Pesos Colombianos), es necesario ingresar el valor manualmente en el campo.",
    });
    return;
  }

  let data = {
    currency_code: "COP",
    rate: parseFloat(pesosInput.value),
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    Swal.fire("Tasa de peso procesada");
    setTimeout(() => { emit("refresh"); }, 150);
    pesosInput.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  }
};

const submitEuros = async () => {
  let data = {
    currency_code: "EUR",
    rate: eurosInput.value ? parseFloat(eurosInput.value) : null,
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    Swal.fire("Tasa de euro procesada");
    setTimeout(() => { emit("refresh"); }, 150);
    eurosInput.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  }
};

const updateBCV = async (currency) => {
  let data = {
    currency_code: currency,
    rate: null,
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    Swal.fire("Tasa actualizada desde BCV");
    setTimeout(() => { emit("refresh"); }, 150);
  } catch (error) {
    console.error("Error al enviar:", error);
  }
};

const submitCOPC = async () => {
  if (!copcInput.value) {
    Swal.fire({
      icon: "info",
      title: "Actualización Manual Requerida",
      text: "Para COPC, es necesario ingresar el valor manualmente.",
    });
    return;
  }

  let data = {
    currency_code: "COPC",
    rate: parseFloat(copcInput.value),
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    Swal.fire("Tasa de COPC procesada");
    setTimeout(() => { emit("refresh"); }, 150);
    copcInput.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  }
};
</script>

<template>
  <VRow class="match-height">
    <!-- Dólar BCV -->
    <VCol cols="12" md="3">
      <VCard class="h-100 text-center border-primary position-relative" elevation="2" :class="{'border-opacity-100': dateColorDollar === 'success'}">
        <VCardItem class="pb-2 pt-6">
          <VAvatar color="primary" variant="tonal" rounded size="64" class="mb-4 mx-auto">
            <VIcon size="36" icon="tabler-currency-dollar" />
          </VAvatar>
          <VCardTitle class="text-h5 font-weight-bold">Dólar BCV</VCardTitle>
          <VCardSubtitle>Tasa Oficial</VCardSubtitle>
        </VCardItem>

        <VCardText class="pb-6">
          <div class="d-flex justify-center align-end mb-4">
            <div class="text-h3 font-weight-bold text-primary">Bs. {{ props.dollar }}</div>
          </div>
          <VChip :color="dateColorDollar" size="small" class="mb-6 font-weight-medium">
            <VIcon start icon="tabler-calendar-stats" size="16"></VIcon>
            {{ dateUpdateDollar || 'Cargando...' }}
          </VChip>

          <VBtn color="primary" variant="elevated" block @click="updateBCV('BS')" prepend-icon="tabler-refresh">
            Actualizar (BCV)
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Peso Colombiano (COP) -->
    <VCol cols="12" md="3">
      <VCard class="h-100 text-center border-info position-relative" elevation="2" :class="{'border-opacity-100': dateColorPesos === 'success'}">
        <VCardItem class="pb-2 pt-6">
          <VAvatar color="info" variant="tonal" rounded size="64" class="mb-4 mx-auto">
            <VIcon size="36" icon="tabler-currency-peso" />
          </VAvatar>
          <VCardTitle class="text-h5 font-weight-bold">Peso Colombiano</VCardTitle>
          <VCardSubtitle>Tasa Manual</VCardSubtitle>
        </VCardItem>

        <VCardText class="pb-6">
          <div class="mb-4">
            <div class="text-h3 font-weight-bold text-info mb-1">{{ props.pesos }}</div>
            <div class="text-caption text-medium-emphasis">Tasa Actual</div>
          </div>
          
          <VChip :color="dateColorPesos" size="small" class="mb-6 font-weight-medium">
            <VIcon start icon="tabler-calendar-stats" size="16"></VIcon>
            {{ dateUpdatePesos || 'Cargando...' }}
          </VChip>

          <div class="d-flex align-center gap-2">
            <VTextField
              v-model="pesosInput"
              placeholder="0.00"
              prefix="$"
              density="compact"
              variant="outlined"
              hide-details
              type="number"
              class="flex-grow-1"
            />
            <VBtn color="info" icon="tabler-check" variant="elevated" @click="submitPesos" />
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Euro (EUR) -->
    <VCol cols="12" md="3">
      <VCard class="h-100 text-center border-warning position-relative" elevation="2" :class="{'border-opacity-100': dateColorEuros === 'success'}">
        <VCardItem class="pb-2 pt-6">
          <VAvatar color="warning" variant="tonal" rounded size="64" class="mb-4 mx-auto">
            <VIcon size="36" icon="tabler-currency-euro" />
          </VAvatar>
          <VCardTitle class="text-h5 font-weight-bold">Euro BCV</VCardTitle>
          <VCardSubtitle>Tasa Oficial</VCardSubtitle>
        </VCardItem>

        <VCardText class="pb-6">
          <div class="d-flex justify-center align-end mb-4">
            <div class="text-h3 font-weight-bold text-warning">Bs. {{ props.euros }}</div>
          </div>
          <VChip :color="dateColorEuros" size="small" class="mb-6 font-weight-medium">
            <VIcon start icon="tabler-calendar-stats" size="16"></VIcon>
            {{ dateUpdateEuros || 'Cargando...' }}
          </VChip>

          <VBtn color="warning" variant="elevated" block @click="updateBCV('EUR')" prepend-icon="tabler-refresh">
            Actualizar (BCV)
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>

    <!-- COP Cambio (COPC) -->
    <VCol cols="12" md="3">
      <VCard class="h-100 text-center border-success position-relative" elevation="2" :class="{'border-opacity-100': dateColorCopc === 'success'}">
        <VCardItem class="pb-2 pt-6">
          <VAvatar color="success" variant="tonal" rounded size="64" class="mb-4 mx-auto">
            <VIcon size="36" icon="tabler-arrows-right-left" />
          </VAvatar>
          <VCardTitle class="text-h5 font-weight-bold">COP Cambio (COPC)</VCardTitle>
          <VCardSubtitle>Tasa Manual de Cambio</VCardSubtitle>
        </VCardItem>

        <VCardText class="pb-6">
          <div class="mb-4">
            <div class="text-h3 font-weight-bold text-success mb-1">{{ props.copc }}</div>
            <div class="text-caption text-medium-emphasis">Tasa Actual</div>
          </div>
          
          <VChip :color="dateColorCopc" size="small" class="mb-6 font-weight-medium">
            <VIcon start icon="tabler-calendar-stats" size="16"></VIcon>
            {{ dateUpdateCopc || 'Cargando...' }}
          </VChip>

          <div class="d-flex align-center gap-2">
            <VTextField
              v-model="copcInput"
              placeholder="0.00"
              prefix="$"
              density="compact"
              variant="outlined"
              hide-details
              type="number"
              class="flex-grow-1"
            />
            <VBtn color="success" icon="tabler-check" variant="elevated" @click="submitCOPC" />
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
