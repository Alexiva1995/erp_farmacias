<script setup>
import axios from "@/plugins/axios";
import Swal from "sweetalert2";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed, ref } from "vue";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

const props = defineProps({
  rates: { type: Object, required: true },
});

const emit = defineEmits(["refresh"]);

const pesosInput = ref("");
const eurosInput = ref("");
const copcInput = ref("");
const loadingButtons = ref({});

const submitPesos = async () => {
  if (!pesosInput.value) {
    Swal.fire({
      icon: "info",
      title: "Actualización Manual Requerida",
      text: "Para COP (Pesos Colombianos), es necesario ingresar el valor manualmente en el campo.",
    });
    return;
  }

  loadingButtons.value['COP'] = true;
  let data = {
    currency_code: "COP",
    rate: parseFloat(pesosInput.value),
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    Swal.fire("Tasa de peso procesada");
    emit("refresh");
    pesosInput.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  } finally {
    loadingButtons.value['COP'] = false;
  }
};

const submitEuros = async () => {
  loadingButtons.value['EUR_manual'] = true;
  let data = {
    currency_code: "EUR",
    rate: eurosInput.value ? parseFloat(eurosInput.value) : null,
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    Swal.fire("Tasa de euro procesada");
    emit("refresh");
    eurosInput.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  } finally {
    loadingButtons.value['EUR_manual'] = false;
  }
};

const updateRate = async (currency) => {
  loadingButtons.value[currency] = true;
  let data = {
    currency_code: currency,
    rate: null,
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    const sourceText = currency === "BINANCE" ? "Binance P2P" : (currency === "EUR" ? "Euro Oficial" : "BCV");
    Swal.fire(`Tasa actualizada desde ${sourceText}`);
    emit("refresh");
  } catch (error) {
    console.error("Error al enviar:", error);
  } finally {
    loadingButtons.value[currency] = false;
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

  loadingButtons.value['COPC'] = true;
  let data = {
    currency_code: "COPC",
    rate: parseFloat(copcInput.value),
  };

  try {
    await axios.post("/finances/exchange-rates/store", data);
    Swal.fire("Tasa de COPC procesada");
    emit("refresh");
    copcInput.value = "";
  } catch (error) {
    console.error("Error al enviar:", error);
  } finally {
    loadingButtons.value['COPC'] = false;
  }
};
</script>

<template>
  <VRow class="match-height ma-0 mx-n1">
    <!-- Dólar BCV -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm">
        <div
          class="card-bg-decoration"
          style="background: linear-gradient(45deg, rgba(var(--v-theme-primary), 0.1), transparent)"
        ></div>
        
        <VCardText class="pa-5 relative-content d-flex flex-column h-100">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar color="primary" variant="tonal" rounded="lg" size="44" class="elevation-1">
              <VIcon size="24" icon="tabler-currency-dollar" />
            </VAvatar>
            <div class="text-right">
              <span class="text-overline font-weight-bold text-disabled leading-none mb-1 d-block" style="letter-spacing: 1px !important">
                Dólar BCV
              </span>
              <span class="text-super-xs font-weight-black opacity-60 uppercase text-primary">Tasa Oficial</span>
            </div>
          </div>

          <div class="mb-4">
            <div class="text-h4 font-weight-black text-primary">Bs. {{ Number(props.rates.BS.rate).toFixed(4) }}</div>
          </div>

          <VDivider class="mb-4 opacity-20" />

          <div class="d-flex flex-column gap-3 mt-auto">
            <div class="d-flex align-center justify-space-between mb-2">
              <VChip :color="props.rates.BS.dateColor" size="x-small" class="font-weight-black rounded uppercase">
                <VIcon start icon="tabler-calendar-stats" size="14"></VIcon>
                {{ props.rates.BS.dateUpdate || 'Cargando...' }}
              </VChip>
            </div>

            <VBtn 
              color="primary" 
              variant="flat" 
              block 
              :loading="!!loadingButtons['BS']"
              :disabled="!!loadingButtons['BS']"
              @click="updateRate('BS')" 
              prepend-icon="tabler-refresh"
              class="rounded-lg font-weight-black text-xs shadow-sm"
              size="small"
            >
              ACTUALIZAR BCV
            </VBtn>
          </div>
        </VCardText>
        <div class="accent-border bg-primary"></div>
      </VCard>
    </VCol>

    <!-- Dólar Binance -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm" :class="{'restaurant-selected-card': isRestaurant}">
        <div
          class="card-bg-decoration"
          style="background: linear-gradient(45deg, rgba(var(--v-theme-error), 0.1), transparent)"
        ></div>
        
        <VCardText class="pa-5 relative-content d-flex flex-column h-100">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar color="error" variant="tonal" rounded="lg" size="44" class="elevation-1">
              <VIcon size="24" icon="tabler-currency-bitcoin" />
            </VAvatar>
            <div class="text-right">
              <span class="text-overline font-weight-bold text-disabled leading-none mb-1 d-block" style="letter-spacing: 1px !important">
                Dólar Binance
              </span>
              <span class="text-super-xs font-weight-black opacity-60 uppercase text-error">
                {{ isRestaurant ? 'Tasa Activa (Rest)' : 'Tasa P2P' }}
              </span>
            </div>
          </div>

          <div class="mb-4">
            <div class="text-h4 font-weight-black text-error">Bs. {{ Number(props.rates.BINANCE.rate).toFixed(4) }}</div>
          </div>

          <VDivider class="mb-4 opacity-20" />

          <div class="d-flex flex-column gap-3 mt-auto">
            <div class="d-flex align-center justify-space-between mb-2">
              <VChip :color="props.rates.BINANCE.dateColor" size="x-small" class="font-weight-black rounded uppercase">
                <VIcon start icon="tabler-calendar-stats" size="14"></VIcon>
                {{ props.rates.BINANCE.dateUpdate || 'Cargando...' }}
              </VChip>
            </div>

            <VBtn 
              color="error" 
              variant="flat" 
              block 
              :loading="!!loadingButtons['BINANCE']"
              :disabled="!!loadingButtons['BINANCE']"
              @click="updateRate('BINANCE')" 
              prepend-icon="tabler-refresh"
              class="rounded-lg font-weight-black text-xs shadow-sm"
              size="small"
            >
              ACTUALIZAR BINANCE
            </VBtn>
          </div>
        </VCardText>
        <div class="accent-border bg-error"></div>
      </VCard>
    </VCol>

    <!-- Peso Colombiano (COP) -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm">
        <div
          class="card-bg-decoration"
          style="background: linear-gradient(45deg, rgba(var(--v-theme-info), 0.1), transparent)"
        ></div>
        
        <VCardText class="pa-5 relative-content d-flex flex-column h-100">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar color="info" variant="tonal" rounded="lg" size="44" class="elevation-1">
              <VIcon size="24" icon="tabler-currency-peso" />
            </VAvatar>
            <div class="text-right">
              <span class="text-overline font-weight-bold text-disabled leading-none mb-1 d-block" style="letter-spacing: 1px !important">
                Peso (COP)
              </span>
              <span class="text-super-xs font-weight-black opacity-60 uppercase text-info">Tasa Manual</span>
            </div>
          </div>

          <div class="mb-4">
            <div class="text-h4 font-weight-black text-info">{{ Number(props.rates.COP.rate) }}</div>
            <div class="text-super-xs text-medium-emphasis uppercase font-weight-bold">Tasa Actual de Venta</div>
          </div>

          <VDivider class="mb-4 opacity-20" />

          <div class="d-flex flex-column gap-3 mt-auto">
            <div class="d-flex align-center justify-space-between mb-2">
              <VChip :color="props.rates.COP.dateColor" size="x-small" class="font-weight-black rounded uppercase">
                <VIcon start icon="tabler-calendar-stats" size="14"></VIcon>
                {{ props.rates.COP.dateUpdate || 'Cargando...' }}
              </VChip>
            </div>

            <div class="d-flex align-center gap-2">
              <VTextField
                v-model="pesosInput"
                placeholder="0.00"
                prefix="$"
                density="compact"
                variant="outlined"
                hide-details
                type="number"
                :disabled="!!loadingButtons['COP']"
                class="flex-grow-1 rounded-lg custom-input-rate"
              />
              <VBtn 
                color="info" 
                icon="tabler-check" 
                variant="flat" 
                :loading="!!loadingButtons['COP']"
                :disabled="!!loadingButtons['COP']"
                @click="submitPesos" 
                size="small"
                class="rounded-lg shadow-sm"
              />
            </div>
          </div>
        </VCardText>
        <div class="accent-border bg-info"></div>
      </VCard>
    </VCol>

    <!-- Euro (EUR) -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm">
        <div
          class="card-bg-decoration"
          style="background: linear-gradient(45deg, rgba(var(--v-theme-warning), 0.1), transparent)"
        ></div>
        
        <VCardText class="pa-5 relative-content d-flex flex-column h-100">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar color="warning" variant="tonal" rounded="lg" size="44" class="elevation-1">
              <VIcon size="24" icon="tabler-currency-euro" />
            </VAvatar>
            <div class="text-right">
              <span class="text-overline font-weight-bold text-disabled leading-none mb-1 d-block" style="letter-spacing: 1px !important">
                Euro BCV
              </span>
              <span class="text-super-xs font-weight-black opacity-60 uppercase text-warning">Tasa Oficial</span>
            </div>
          </div>

          <div class="mb-4">
            <div class="text-h4 font-weight-black text-warning">Bs. {{ Number(props.rates.EUR.rate).toFixed(4) }}</div>
          </div>

          <VDivider class="mb-4 opacity-20" />

          <div class="d-flex flex-column gap-3 mt-auto">
            <div class="d-flex align-center justify-space-between mb-2">
              <VChip :color="props.rates.EUR.dateColor" size="x-small" class="font-weight-black rounded uppercase">
                <VIcon start icon="tabler-calendar-stats" size="14"></VIcon>
                {{ props.rates.EUR.dateUpdate || 'Cargando...' }}
              </VChip>
            </div>

            <VBtn 
              color="warning" 
              variant="flat" 
              block 
              :loading="!!loadingButtons['EUR']"
              :disabled="!!loadingButtons['EUR']"
              @click="updateRate('EUR')" 
              prepend-icon="tabler-refresh"
              class="rounded-lg font-weight-black text-xs shadow-sm"
              size="small"
            >
              ACTUALIZAR BCV
            </VBtn>
          </div>
        </VCardText>
        <div class="accent-border bg-warning"></div>
      </VCard>
    </VCol>

    <!-- COP Cambio (COPC) -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm">
        <div
          class="card-bg-decoration"
          style="background: linear-gradient(45deg, rgba(var(--v-theme-success), 0.1), transparent)"
        ></div>
        
        <VCardText class="pa-5 relative-content d-flex flex-column h-100">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar color="success" variant="tonal" rounded="lg" size="44" class="elevation-1">
              <VIcon size="24" icon="tabler-arrows-right-left" />
            </VAvatar>
            <div class="text-right">
              <span class="text-overline font-weight-bold text-disabled leading-none mb-1 d-block" style="letter-spacing: 1px !important">
                COP (COPC)
              </span>
              <span class="text-super-xs font-weight-black opacity-60 uppercase text-success">Cambio Manual</span>
            </div>
          </div>

          <div class="mb-4">
            <div class="text-h4 font-weight-black text-success">{{ Number(props.rates.COPC.rate) }}</div>
            <div class="text-super-xs text-medium-emphasis uppercase font-weight-bold">Tasa para Compras</div>
          </div>

          <VDivider class="mb-4 opacity-20" />

          <div class="d-flex flex-column gap-3 mt-auto">
            <div class="d-flex align-center justify-space-between mb-2">
              <VChip :color="props.rates.COPC.dateColor" size="x-small" class="font-weight-black rounded uppercase">
                <VIcon start icon="tabler-calendar-stats" size="14"></VIcon>
                {{ props.rates.COPC.dateUpdate || 'Cargando...' }}
              </VChip>
            </div>

            <div class="d-flex align-center gap-2">
              <VTextField
                v-model="copcInput"
                placeholder="0.00"
                prefix="$"
                density="compact"
                variant="outlined"
                hide-details
                type="number"
                :disabled="!!loadingButtons['COPC']"
                class="flex-grow-1 rounded-lg custom-input-rate"
              />
              <VBtn 
                color="success" 
                icon="tabler-check" 
                variant="flat" 
                :loading="!!loadingButtons['COPC']"
                :disabled="!!loadingButtons['COPC']"
                @click="submitCOPC" 
                size="small"
                class="rounded-lg shadow-sm"
              />
            </div>
          </div>
        </VCardText>
        <div class="accent-border bg-success"></div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  transition: all 0.3s ease;
  position: relative;
}

.stats-card:hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 95%) !important;
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1) !important;
}

.restaurant-selected-card {
  border: 2px solid rgb(var(--v-theme-error)) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 120px;
  filter: blur(50px);
  inline-size: 120px;
  inset-block-start: -30px;
  inset-inline-end: -30px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1.2;
}

.custom-input-rate :deep(.v-field__input) {
  font-weight: 800;
  font-size: 0.875rem;
  padding-block: 4px;
}

.custom-input-rate :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15;
}
</style>
