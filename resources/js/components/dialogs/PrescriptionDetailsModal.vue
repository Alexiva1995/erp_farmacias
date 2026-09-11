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
    :max-inline-size="mobile ? '100%' : '800px'"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    @keydown.esc.prevent="closeModal"
  >
    <VCard v-if="props.prescriptionData" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium Standard -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
            <VIcon icon="tabler-prescription" color="primary" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ (props.prescriptionData.name || 'Detalle de Oferta').toUpperCase() }}
            </h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
              Oferta por Récipe Médico (ID #{{ props.prescriptionData.id }})
            </span>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="closeModal"
            class="rounded-lg"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light">
        <div class="pa-6">
          <!-- Tarjetas de Información Rápida -->
          <VRow class="mb-6">
            <VCol cols="12" sm="4">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1 relative overflow-hidden h-100">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="header-indicator primary"></div>
                  <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1">Campaña</span>
                </div>
                <div class="d-flex flex-column pt-1">
                  <span class="text-subtitle-1 font-weight-black text-high-emphasis text-uppercase truncate leading-tight">
                    {{ props.prescriptionData.name || "Sin nombre" }}
                  </span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">ID Campaña: {{ props.prescriptionData.id }}</span>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="4">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1 relative overflow-hidden h-100">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="header-indicator secondary"></div>
                  <span class="text-super-xs font-weight-black text-secondary uppercase letter-spacing-1">Descuento</span>
                </div>
                <div class="d-flex flex-column pt-1">
                  <span class="text-h4 font-weight-950 text-success leading-tight">
                    {{ props.prescriptionData.discount_percentage }}% OFF
                  </span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">Ahorro en Récipe</span>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="4">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1 relative overflow-hidden h-100">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="header-indicator success"></div>
                  <span class="text-super-xs font-weight-black text-success uppercase letter-spacing-1">Estado</span>
                </div>
                <div class="d-flex align-center gap-2 pt-1">
                  <VChip
                    :color="getStatusColor(props.prescriptionData.is_active)"
                    size="small"
                    variant="flat"
                    class="font-weight-black rounded"
                  >
                    {{ getStatusText(props.prescriptionData.is_active) }}
                  </VChip>
                  <VChip
                    :color="isOfferCurrentlyActive ? 'success' : 'error'"
                    size="small"
                    variant="tonal"
                    class="font-weight-black rounded"
                  >
                    {{ isOfferCurrentlyActive ? 'VIGENTE' : 'NO VIGENTE' }}
                  </VChip>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <VDivider class="border-dashed mb-6" />

          <!-- Fechas de Vigencia -->
          <div class="d-flex align-center gap-2 mb-4">
            <div class="header-indicator primary shadow-sm"></div>
            <span class="text-subtitle-2 font-weight-black text-primary uppercase letter-spacing-1">Periodo de Validez</span>
          </div>

          <VRow>
            <VCol cols="12" sm="6">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1">
                <div class="d-flex align-center gap-2 mb-2">
                  <VIcon icon="tabler-calendar-event" size="18" color="success" />
                  <span class="text-caption font-weight-black text-disabled uppercase">Fecha de Inicio</span>
                </div>
                <span class="text-subtitle-1 font-weight-black text-high-emphasis">
                  {{ formatDate(props.prescriptionData.start_date) }}
                </span>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1">
                <div class="d-flex align-center gap-2 mb-2">
                  <VIcon icon="tabler-calendar-off" size="18" color="error" />
                  <span class="text-caption font-weight-black text-disabled uppercase">Fecha de Finalización</span>
                </div>
                <span class="text-subtitle-1 font-weight-black text-high-emphasis">
                  {{ formatDate(props.prescriptionData.end_date) }}
                </span>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-6 bg-white">
        <VBtn color="primary" variant="flat" class="rounded-lg font-weight-black px-12 shadow-primary text-button uppercase" block size="large" @click="closeModal">
          <VIcon start>tabler-check</VIcon>
          ENTENDIDO
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 16px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }
.header-indicator.success { background-color: rgb(var(--v-theme-success)); }

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

.leading-tight { line-height: 1.25 !important; }
.leading-none { line-height: 1 !important; }
.font-weight-950 { font-weight: 950 !important; }

.border-dashed {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
