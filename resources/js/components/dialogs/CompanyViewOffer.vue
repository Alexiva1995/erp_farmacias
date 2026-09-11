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
    :max-inline-size="mobile ? '100%' : '800px'"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="props.offerData" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium Standard -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
            <VIcon icon="tabler-building" color="primary" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ (props.offerData.company_name || 'Detalle de Oferta').toUpperCase() }}
            </h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
              Información de Oferta por Empresa (ID #{{ props.offerData.id }})
            </span>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="onCancel"
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
                  <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1">Empresa</span>
                </div>
                <div class="d-flex flex-column pt-1">
                  <span class="text-subtitle-1 font-weight-black text-high-emphasis text-uppercase truncate leading-tight">
                    {{ props.offerData.company_name }}
                  </span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">ID Empresa: {{ props.offerData.company_id }}</span>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="4">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1 relative overflow-hidden h-100">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="header-indicator secondary"></div>
                  <span class="text-super-xs font-weight-black text-secondary uppercase letter-spacing-1">Vigencia</span>
                </div>
                <div class="d-flex flex-column pt-1">
                  <span class="text-super-xs font-weight-black text-success uppercase">
                    INI: {{ formatDate(props.offerData.start_date) }}
                  </span>
                  <span class="text-super-xs font-weight-black text-error uppercase mt-1">
                    FIN: {{ formatDate(props.offerData.end_date) }}
                  </span>
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
                    :color="getStatusColor(props.offerData.is_active)"
                    size="small"
                    variant="flat"
                    class="font-weight-black rounded"
                  >
                    {{ getStatusText(props.offerData.is_active) }}
                  </VChip>
                  <VChip
                    :color="isOfferActive ? 'success' : 'error'"
                    size="small"
                    variant="tonal"
                    class="font-weight-black rounded"
                  >
                    {{ isOfferActive ? 'VIGENTE' : 'NO VIGENTE' }}
                  </VChip>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <VDivider class="border-dashed mb-6" />

          <!-- Escalas de Descuento -->
          <div class="mb-4">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-primary uppercase letter-spacing-1">Escalas de Descuento Configuradas</span>
            </div>

            <VTable v-if="props.offerData.scales && props.offerData.scales.length > 0" class="internal-table rounded-lg border shadow-sm bg-white">
              <thead>
                <tr>
                  <th class="text-left">#</th>
                  <th class="text-left">MONTO MÍNIMO</th>
                  <th class="text-left">MONTO MÁXIMO</th>
                  <th class="text-center">% DESCUENTO</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(scale, index) in props.offerData.scales" :key="scale.id || index">
                  <td class="font-weight-black text-primary">{{ index + 1 }}</td>
                  <td class="font-weight-bold">{{ formatCurrency(scale.min_amount, 'USD') }}</td>
                  <td class="font-weight-bold">{{ formatCurrency(scale.max_amount, 'USD') }}</td>
                  <td class="text-center">
                    <VChip size="small" color="success" variant="tonal" class="font-weight-black rounded">
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
              class="mt-2 rounded-lg"
            >
              No hay escalas de descuento definidas para esta oferta.
            </VAlert>
          </div>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-6 bg-white">
        <VBtn color="primary" variant="flat" class="rounded-lg font-weight-black px-12 shadow-primary text-button uppercase" block size="large" @click="onCancel">
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

.internal-table :deep(thead th) {
  background-color: #f8fafc !important;
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
