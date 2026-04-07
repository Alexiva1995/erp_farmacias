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
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-coin-bitcoin"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Ajuste de Rentabilidad
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Configuración de Margen Individual • Barrio Sucre
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="emit('close-modal')"
            :disabled="loading"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-4">
        <!-- Perfil del Producto -->
        <VCard
          variant="flat"
          class="pa-4 bg-white rounded-xl border shadow-sm"
        >
          <div class="d-flex align-center justify-space-between mb-3">
            <VChip
              size="small"
              color="primary"
              variant="flat"
              class="font-weight-black px-3 rounded-lg"
            >
              ID: {{ props.product.product_id }}
            </VChip>
            <div class="d-flex align-center gap-2 text-disabled leading-none">
              <VIcon
                icon="tabler-barcode"
                size="16"
              />
              <span class="text-super-xs font-weight-black uppercase letter-spacing-1">Producto TPV</span>
            </div>
          </div>
          <h3 class="text-subtitle-1 font-weight-black text-high-emphasis leading-tight uppercase mb-0">
            {{ props.product.name || "Ajuste Directo de Margen" }}
          </h3>
        </VCard>

        <!-- Configuración de Margen -->
        <div class="d-flex align-center gap-2 mb-0 mt-2">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Parámetros Financieros</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm"
        >
          <VRow dense>
            <VCol cols="12">
              <div class="text-center py-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block letter-spacing-1">Porcentaje de Rentabilidad Objetivo</span>
                <VTextField
                  v-model="percentage"
                  placeholder="0"
                  type="number"
                  suffix="%"
                  variant="plain"
                  class="ultra-huge-input-text h-auto font-weight-950"
                  density="compact"
                  hide-details
                  autofocus
                  @keyup.enter="checkExistenceAndSave"
                />
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Nota de Seguridad -->
        <div class="mt-2 pa-4 rounded-xl bg-error bg-opacity-10 border-dashed-2 d-flex align-center gap-4">
          <VAvatar
            color="error"
            variant="tonal"
            size="40"
            class="rounded-lg"
          >
            <VIcon
              icon="tabler-shield-lock"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <span class="text-xs font-weight-black text-black uppercase letter-spacing-1 mb-1">Restricción de Bloqueo</span>
            <p class="text-super-xs text-medium-emphasis mb-0 leading-tight">
              Al guardar este porcentaje, el producto quedará **excluido** automáticamente de cualquier ajuste de rentabilidad global masivo.
            </p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="emit('close-modal')"
              :disabled="loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="loading"
              :disabled="loading || !percentage"
              @click="checkExistenceAndSave"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
              />
              {{ props.product.id ? 'Actualizar' : 'Registrar' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.ultra-huge-input-text :deep(input) {
  border: none;
  background: transparent;
  block-size: auto;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 3rem !important;
  font-weight: 950 !important;
  inline-size: 100%;
  line-height: 1;
  outline: none;
  text-align: center !important;
}

.ultra-huge-input-text :deep(.v-field__input) {
  padding: 0 !important;
}

.italic {
  font-style: italic;
}
</style>
