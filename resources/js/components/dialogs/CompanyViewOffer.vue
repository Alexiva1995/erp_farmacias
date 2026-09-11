<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  offerData: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "modal-closed-view"]);

const { mobile } = useDisplay();

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed-view");
};

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

const isOfferActive = computed(() => {
  if (!props.offerData) return false;
  const now = new Date();
  const startDate = new Date(props.offerData.start_date);
  const endDate = new Date(props.offerData.end_date);
  return props.offerData.is_active && now >= startDate && now <= endDate;
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="680px"
    width="680px"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="props.offerData" :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium Standard -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1">
            <VIcon icon="tabler-building" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ (props.offerData.company_name || 'Detalle de Oferta').toUpperCase() }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                Información de Oferta por Empresa (ID #{{ props.offerData.id }})
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
            @click="onCancel"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Barra de Resumen Integrada centrada -->
        <div class="pa-3 px-3 rounded border bg-var-theme-background mb-4">
          <VRow dense class="align-center text-center">
            <!-- Empresa -->
            <VCol cols="6" sm="4" class="d-flex align-center justify-center gap-2 py-1">
              <VAvatar size="32" color="primary" variant="tonal" class="rounded">
                <VIcon icon="tabler-building" size="16" />
              </VAvatar>
              <div class="d-flex flex-column text-start">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Empresa</span>
                <span class="text-sm font-weight-black text-high-emphasis leading-tight truncate" style="max-inline-size: 130px;" :title="props.offerData.company_name">
                  {{ props.offerData.company_name }}
                </span>
                <span class="text-super-xs text-disabled font-weight-bold uppercase">ID: #{{ props.offerData.company_id }}</span>
              </div>
            </VCol>

            <!-- Vigencia -->
            <VCol cols="6" sm="4" class="d-flex align-center justify-center gap-2 py-1">
              <VAvatar size="32" color="secondary" variant="tonal" class="rounded">
                <VIcon icon="tabler-calendar-event" size="16" />
              </VAvatar>
              <div class="d-flex flex-column text-start">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Vigencia</span>
                <span class="text-super-xs font-weight-black text-success uppercase leading-tight">
                  INI: {{ formatDate(props.offerData.start_date) }}
                </span>
                <span class="text-super-xs font-weight-black text-error uppercase leading-tight">
                  FIN: {{ formatDate(props.offerData.end_date) }}
                </span>
              </div>
            </VCol>

            <!-- Estado -->
            <VCol cols="12" sm="4" class="d-flex align-center justify-center py-1 mt-2 mt-sm-0">
              <div class="d-flex flex-column align-center">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Disponibilidad</span>
                <div class="d-flex align-center gap-1">
                  <VChip
                    :color="getStatusColor(props.offerData.is_active)"
                    size="x-small"
                    variant="tonal"
                    class="font-weight-black rounded"
                  >
                    {{ getStatusText(props.offerData.is_active) }}
                  </VChip>
                  <VChip
                    :color="isOfferActive ? 'success' : 'error'"
                    size="x-small"
                    variant="tonal"
                    class="font-weight-black rounded"
                  >
                    {{ isOfferActive ? 'VIGENTE' : 'NO VIGENTE' }}
                  </VChip>
                </div>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Escalas de Descuento -->
        <div class="mb-4">
          <div class="d-flex align-center gap-1-5 mb-3">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Escalas de Descuento Configuradas</span>
          </div>

          <VTable v-if="props.offerData.scales && props.offerData.scales.length > 0" density="compact" class="internal-table rounded border shadow-none bg-surface">
            <thead>
              <tr>
                <th class="text-center" style="width: 50px;">#</th>
                <th class="text-end">MONTO MÍNIMO</th>
                <th class="text-end">MONTO MÁXIMO</th>
                <th class="text-center">% DESCUENTO</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(scale, index) in props.offerData.scales" :key="scale.id || index">
                <td class="text-center font-weight-black text-primary">{{ index + 1 }}</td>
                <td class="text-end font-weight-bold text-high-emphasis">{{ formatCurrency(scale.min_amount, 'USD') }}</td>
                <td class="text-end font-weight-bold text-high-emphasis">{{ formatCurrency(scale.max_amount, 'USD') }}</td>
                <td class="text-center">
                  <VChip size="x-small" color="success" variant="tonal" class="font-weight-black rounded">
                    {{ scale.discount_percentage }}% OFF
                  </VChip>
                </td>
              </tr>
            </tbody>
          </VTable>

          <VAlert
            v-else
            type="info"
            variant="tonal"
            density="compact"
            class="mt-2 rounded"
          >
            No hay escalas de descuento definidas para esta oferta.
          </VAlert>
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
          @click="onCancel"
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

.detail-dialog-card {
  border-radius: 5px !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }
.header-indicator.success { background-color: rgb(var(--v-theme-success)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  border-radius: 5px !important;
}

.internal-table :deep(thead th) {
  background-color: rgba(var(--v-border-color), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.65rem !important;
  font-weight: 950 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.gap-1-5 { gap: 6px !important; }

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.leading-tight { line-height: 1.25 !important; }
.leading-none { line-height: 1 !important; }
.font-weight-950 { font-weight: 950 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
