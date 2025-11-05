<script setup>
import { computed } from "vue";

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

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed-view");
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('es-ES');
};

const getStatusText = (isActive) => {
  return isActive ? 'Activa' : 'Inactiva';
};

const getStatusColor = (isActive) => {
  return isActive ? 'success' : 'error';
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
    max-width="700px"
    persistent
  >
    <VCard :loading="props.loading" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center p-4">
        <span class="text-h5 font-weight-bold">Detalles de Oferta</span>
        <VSpacer />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div v-if="props.offerData" class="details-container">
          <!-- Información General -->
          <VRow class="mb-6">
            <VCol cols="12">
              <h3 class="text-h6 mb-4">Información General</h3>
            </VCol>
            
            <VCol cols="12" sm="6">
              <VCard variant="outlined" class="pa-3">
                <div class="text-caption text-medium-emphasis">Empresa</div>
                <div class="text-body-1 font-weight-medium">
                  {{ props.offerData.company_name }}
                </div>
                <div class="text-caption text-disabled">
                  ID: {{ props.offerData.company_id }}
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6">
              <VCard variant="outlined" class="pa-3">
                <div class="text-caption text-medium-emphasis">Estatus</div>
                <div class="d-flex align-center gap-2">
                  <VChip
                    :color="getStatusColor(props.offerData.is_active)"
                    size="small"
                    variant="flat"
                  >
                    {{ getStatusText(props.offerData.is_active) }}
                  </VChip>
                  <VChip
                    v-if="isOfferActive"
                    color="success"
                    size="small"
                    variant="outlined"
                  >
                    Vigente
                  </VChip>
                  <VChip
                    v-else
                    color="error"
                    size="small"
                    variant="outlined"
                  >
                    No Vigente
                  </VChip>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6">
              <VCard variant="outlined" class="pa-3">
                <div class="text-caption text-medium-emphasis">Fecha de Inicio</div>
                <div class="text-body-1 font-weight-medium">
                  {{ formatDate(props.offerData.start_date) }}
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6">
              <VCard variant="outlined" class="pa-3">
                <div class="text-caption text-medium-emphasis">Fecha de Finalización</div>
                <div class="text-body-1 font-weight-medium">
                  {{ formatDate(props.offerData.end_date) }}
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Escalas de Descuento -->
          <VRow>
            <VCol cols="12">
              <h3 class="text-h6 mb-4">Escalas de Descuento</h3>
              
              <VTable v-if="props.offerData.scales && props.offerData.scales.length > 0">
                <thead>
                  <tr>
                    <th class="text-left">Volumen Mínimo</th>
                    <th class="text-left">Volumen Máximo</th>
                    <th class="text-left">% Descuento</th>
                    <th class="text-left">Rango</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(scale, index) in props.offerData.scales" :key="scale.id || index">
                    <td>{{ scale.min_volume }}</td>
                    <td>{{ scale.max_volume }}</td>
                    <td>
                      <VChip size="small" color="primary" variant="flat">
                        {{ scale.discount_percentage }}%
                      </VChip>
                    </td>
                    <td class="text-caption text-disabled">
                      {{ scale.min_volume }} - {{ scale.max_volume }} unidades
                    </td>
                  </tr>
                </tbody>
              </VTable>
              
              <VAlert
                v-else
                type="info"
                variant="tonal"
                class="mt-2"
              >
                No hay escalas de descuento definidas para esta oferta.
              </VAlert>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="primary" variant="flat" @click="onCancel">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.details-container {
  max-height: 60vh;
  overflow-y: auto;
}
</style>