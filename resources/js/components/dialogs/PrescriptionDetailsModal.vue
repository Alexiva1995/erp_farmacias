<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  prescriptionData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["update:isDialogVisible"]);

const { mobile } = useDisplay();

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString("es-ES", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const getStatusText = (isActive) => {
  return isActive ? "ACTIVA" : "INACTIVA";
};

const getStatusColor = (isActive) => {
  return isActive ? "success" : "error";
};

const isOfferCurrentlyActive = computed(() => {
  if (!props.prescriptionData) return false;
  const now = new Date();
  const startDate = new Date(props.prescriptionData.start_date);
  const endDate = new Date(props.prescriptionData.end_date);

  return props.prescriptionData.is_active && now >= startDate && now <= endDate;
});

const closeModal = () => {
  dialogVisible.value = false;
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="680px"
    width="680px"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @keydown.esc.prevent="closeModal"
  >
    <VCard v-if="props.prescriptionData" :class="mobile ? 'rounded-0' : 'rounded overflow-hidden border-0 shadow-xl bg-surface'">
      <!-- Header Premium Standard -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 text-primary font-weight-black">
            <VIcon icon="tabler-prescription" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ (props.prescriptionData.name || 'Detalle de Oferta').toUpperCase() }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                Oferta por Récipe Médico (ID #{{ props.prescriptionData.id }})
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded"
            @click="closeModal"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Barra de Resumen Integrada -->
        <div class="pa-3 px-4 rounded border bg-var-theme-background mb-4">
          <VRow dense class="align-center">
            <!-- Campaña -->
            <VCol cols="12" sm="4" class="d-flex align-center gap-3 py-1">
              <VAvatar size="34" color="primary" variant="tonal" class="rounded">
                <VIcon icon="tabler-prescription" size="18" />
              </VAvatar>
              <div class="d-flex flex-column text-start">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Campaña</span>
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate" style="max-inline-size: 160px;" :title="props.prescriptionData.name">
                  {{ props.prescriptionData.name }}
                </span>
                <span class="text-super-xs text-disabled font-weight-bold uppercase">ID #{{ props.prescriptionData.id }}</span>
              </div>
            </VCol>

            <!-- Vigencia -->
            <VCol cols="12" sm="5" class="d-flex align-center gap-3 py-1">
              <VAvatar size="34" color="secondary" variant="tonal" class="rounded">
                <VIcon icon="tabler-calendar-event" size="18" />
              </VAvatar>
              <div class="d-flex flex-column text-start">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Periodo de Vigencia</span>
                <div class="d-flex align-center gap-1 text-xs font-weight-bold text-high-emphasis leading-tight">
                  <span>{{ formatDate(props.prescriptionData.start_date) }}</span>
                  <span class="text-disabled font-weight-regular">—</span>
                  <span>{{ formatDate(props.prescriptionData.end_date) }}</span>
                </div>
                <span class="text-super-xs font-weight-bold" :class="isOfferCurrentlyActive ? 'text-success' : 'text-error'">
                  {{ isOfferCurrentlyActive ? '• Oferta vigente actualmente' : '• Fuera de periodo de vigencia' }}
                </span>
              </div>
            </VCol>

            <!-- Estado -->
            <VCol cols="12" sm="3" class="d-flex align-center justify-start justify-sm-end py-1">
              <div class="d-flex flex-column align-start align-sm-end">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1">Estado</span>
                <VChip
                  :color="getStatusColor(props.prescriptionData.is_active)"
                  size="small"
                  variant="tonal"
                  class="font-weight-black rounded"
                >
                  <VIcon start size="14" :icon="props.prescriptionData.is_active ? 'tabler-check' : 'tabler-x'" />
                  {{ getStatusText(props.prescriptionData.is_active) }}
                </VChip>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Parámetro de Descuento -->
        <div class="mb-2">
          <div class="d-flex align-center gap-1-5 mb-3">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Beneficio por Récipe</span>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-3">
              <VAvatar size="34" color="success" variant="tonal" class="rounded">
                <VIcon icon="tabler-percentage" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Descuento Otorgado</span>
                <span class="text-caption font-weight-medium text-medium-emphasis">Aplica al presentar prescripción médica válida</span>
              </div>
            </div>
            <span class="text-h6 font-weight-black text-success">
              {{ props.prescriptionData.discount_percentage }}% OFF
            </span>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VBtn
          color="primary"
          variant="flat"
          height="44"
          block
          class="font-weight-black rounded shadow-primary text-button uppercase"
          @click="closeModal"
        >
          <VIcon start icon="tabler-check" size="18" />
          Cerrar Detalle
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  border-radius: 5px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
