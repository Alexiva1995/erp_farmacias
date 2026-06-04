<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:isDialogVisible", "submit"]);

const discountPercentage = ref(null);

watch(
  () => props.isDialogVisible,
  (val) => {
    if (val) {
      discountPercentage.value = null;
    }
  }
);

const handleClose = () => {
  emit("update:isDialogVisible", false);
};

const handleSubmit = () => {
  if (!discountPercentage.value) return;

  emit("submit", {
    supplier: props.selectedSupplier,
    percentage: discountPercentage.value,
  });

  handleClose();
};
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="480"
    @update:model-value="handleClose"
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-percent" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Aplicar Descuento Global
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Ajuste de Precios • {{ props.selectedSupplier?.name }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="handleClose" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- Descripción -->
        <VAlert type="info" variant="tonal" density="compact" icon="tabler-info-circle" class="rounded-xl mb-4">
          <span class="text-super-xs font-weight-black">
            Se aplicará el descuento a <strong>todos los productos</strong> del proveedor
            <strong>{{ props.selectedSupplier?.name }}</strong>.
          </span>
        </VAlert>

        <!-- Input de descuento -->
        <div class="d-flex align-center gap-2 mb-3">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Porcentaje de Descuento</span>
        </div>

        <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Descuento a Aplicar</span>
          <AppTextField
            v-model="discountPercentage"
            placeholder="Ej: 15"
            type="number"
            suffix="%"
            autofocus
            prepend-inner-icon="tabler-percentage"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            class="rounded-lg font-weight-black"
          />
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              @click="handleClose"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :disabled="!discountPercentage"
              @click="handleSubmit"
            >
              <VIcon start icon="tabler-check" size="18" />
              Aplicar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
