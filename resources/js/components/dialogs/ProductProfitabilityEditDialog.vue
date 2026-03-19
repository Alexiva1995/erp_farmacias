<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  dialog: { type: Boolean, required: true },
  product: { type: Object, required: true },
});

const emit = defineEmits(["refresh", "close-modal"]);

const percentage = ref(0);
const loading = ref(false);

watch(
  () => props.product,
  (val) => {
    if (val) {
      percentage.value = val.percentage || 0;
    }
  },
  { immediate: true, deep: true }
);

async function saveProfitability() {
  loading.value = true;
  const isUpdate = !!props.product.id;
  const url = isUpdate 
    ? "/finances/profitability/product/update" 
    : "/finances/profitability/product/store";
  
  const data = {
    id: props.product.id,
    product_id: props.product.product_id,
    profitability_percentage: percentage.value,
    is_locked: 1,
  };

  try {
    await axios.post(url, data);
    toast.success("Rentabilidad del producto actualizada correctamente.");
    emit("close-modal");
    emit("refresh");
  } catch (error) {
    console.error("Error al guardar rentabilidad:", error);
    toast.error("Error al guardar la rentabilidad del producto.");
  } finally {
    loading.value = false;
  }
}

const checkExistenceAndSave = async () => {
  if (props.product.id) {
    await saveProfitability();
    return;
  }

  // Si no tiene ID de rentabilidad, verificamos en el backend por si acaso
  try {
    const response = await axios.get(`/finances/profitability/product/${props.product.product_id}`);
    if (response.status === 200) {
      // Ya existe en BD, usamos el ID que venga si es posible o simplemente enviamos a update
      // Para simplificar según la lógica original:
      await saveProfitability();
    }
  } catch (error) {
    // No existe, crear nuevo
    await saveProfitability();
  }
};
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
            <VIcon icon="tabler-lock" color="white" size="24" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black text-white leading-tight">Ajuste Individual</span>
            <span class="text-xs text-white opacity-80 uppercase font-weight-bold">Producto Seleccionado</span>
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
        <div class="mb-6 pa-4 rounded-lg bg-surface-variant-light border d-flex align-center gap-3">
          <VIcon icon="tabler-archive" color="primary" />
          <div class="d-flex flex-column">
            <span class="text-xs text-disabled font-weight-black uppercase">ID Producto: {{ props.product.product_id }}</span>
            <span class="font-weight-black text-primary">Margen Personalizado</span>
          </div>
        </div>

        <VRow>
          <VCol cols="12">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Rentabilidad Específica</span>
            <AppTextField
              v-model="percentage"
              placeholder="Ej: 30"
              type="number"
              suffix="%"
              autofocus
              variant="outlined"
              density="compact"
              class="premium-input-compact"
              hide-details
            />
            <p class="text-xs text-medium-emphasis mt-3 d-flex align-center">
              <VIcon icon="tabler-shield-lock" size="14" class="me-1" color="error" />
              Al guardar, este producto quedará **bloqueado** del ajuste global.
            </p>
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
          :disabled="loading || !percentage"
          class="rounded-lg font-weight-black shadow-sm flex-grow-1"
          @click="checkExistenceAndSave"
        >
          ACTUALIZAR
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
    font-size: 1.25rem !important;
    font-weight: 800 !important;
    min-block-size: 56px !important;
    text-align: center;
  }
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.05);
}
</style>
