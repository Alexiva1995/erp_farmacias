<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  dialog: { type: Boolean, required: true },
  percentage: { type: Number, default: 0 },
});

const emit = defineEmits(["refresh", "close-modal", "update:dialog"]);

const localPercentage = ref(0);
const loading = ref(false);

watch(
  () => props.percentage,
  (val) => {
    localPercentage.value = val;
  },
  { immediate: true },
);

async function storeProfitability() {
  const data = {
    default_profitability_percentage: localPercentage.value,
  };

  loading.value = true;
  try {
    await axios.post("/finances/profitability/store", data);
    toast.success(
      "Rentabilidad asignada correctamente. Se actualizaron los precios de venta de los productos no bloqueados.",
    );
    emit("refresh");
    emit("close-modal");
  } catch (error) {
    console.error("Error en la solicitud:", error);
    const message =
      error.response?.data?.message ||
      error.response?.data?.errors?.default_profitability_percentage?.[0] ||
      "Error al asignar rentabilidad.";
    toast.error(message);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <VDialog
    :model-value="props.dialog"
    max-width="500px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    @update:model-value="emit('close-modal')"
  >
    <VCard class="rounded-xl overflow-hidden">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="premium-dialog-header pa-5 d-flex align-center bg-primary">
          <VAvatar size="40" color="rgba(255,255,255,0.2)" class="me-3">
            <VIcon icon="tabler-percentage" color="white" size="24" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black text-white leading-tight">Asignar Rentabilidad</span>
            <span class="text-xs text-white opacity-80 uppercase font-weight-bold">Ajuste Global</span>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="emit('close-modal')"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow>
          <VCol cols="12">
            <div class="mb-4 d-flex align-center gap-2">
              <VIcon icon="tabler-alert-circle" color="warning" size="20" />
              <span class="text-sm font-weight-bold text-warning uppercase">Importante</span>
            </div>
            <p class="text-sm text-medium-emphasis mb-6">
              Esta acción actualizará el margen de utilidad de **todos los productos** que no tengan su precio bloqueado individualmente.
            </p>

            <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Nuevo Porcentaje</span>
            <AppTextField
              v-model="localPercentage"
              placeholder="Ej: 25"
              type="number"
              suffix="%"
              autofocus
              variant="outlined"
              density="compact"
              class="premium-input-compact"
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider class="opacity-10" />

      <VCardActions class="pa-4 pt-6">
        <VBtn
          color="secondary"
          variant="tonal"
          size="large"
          class="rounded-lg font-weight-black flex-grow-1"
          @click="emit('close-modal')"
        >
          CANCELAR
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          size="large"
          :loading="loading"
          :disabled="loading || !localPercentage"
          class="rounded-lg font-weight-black shadow-sm flex-grow-1"
          @click="storeProfitability"
        >
          GUARDAR AJUSTE
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.premium-dialog-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #2c3e50 100%);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 1rem !important;
    font-weight: 800 !important;
    min-block-size: 50px !important;
  }
}
</style>
