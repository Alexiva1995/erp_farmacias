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
  if (!dateString) return "N/A";
  return new Date(dateString).toLocaleDateString("es-ES");
};

const getStatusText = (isActive) => {
  return isActive ? "Activa" : "Inactiva";
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
  <VDialog :model-value="props.modelValue" max-width="900px" persistent>
    <VCard :loading="props.loading">
      <VCardTitle class="d-flex align-center pa-4">
        <span class="text-h5 font-weight-bold">Detalles de Oferta</span>
        <VSpacer />
        <VBtn icon variant="text" @click="onCancel">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div v-if="props.offerData">
          <h3 class="text-h6 mb-4">Información General</h3>

          <VRow>
            <VCol cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100">
                <div class="d-flex align-center mb-2">
                  <VIcon
                    icon="tabler-stethoscope"
                    class="me-2 text-medium-emphasis"
                  />
                  <span
                    class="text-caption text-medium-emphasis font-weight-bold"
                    >MÉDICO</span
                  >
                </div>
                <div class="text-body-1 font-weight-bold text-high-emphasis">
                  {{ props.offerData.doctor_name }}
                </div>
                <div class="text-caption text-disabled mt-1">
                  ID: {{ props.offerData.doctor_id }}
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" md="4">
              <VCard
                variant="tonal"
                color="primary"
                class="pa-4 h-100 d-flex flex-column justify-center align-center text-center"
              >
                <div class="text-overline mb-1">Descuento Aplicado</div>
                <div class="text-h3 font-weight-bold text-primary">
                  {{ props.offerData.discount }}%
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" md="4">
              <VCard
                variant="outlined"
                class="pa-4 h-100 d-flex flex-column justify-center"
              >
                <div class="d-flex align-center mb-2">
                  <VIcon
                    icon="tabler-activity"
                    class="me-2 text-medium-emphasis"
                  />
                  <span
                    class="text-caption text-medium-emphasis font-weight-bold"
                    >ESTATUS ACTUAL</span
                  >
                </div>
                <div class="d-flex align-center gap-2">
                  <VChip
                    :color="getStatusColor(props.offerData.is_active)"
                    variant="elevated"
                  >
                    {{ getStatusText(props.offerData.is_active) }}
                  </VChip>

                  <VChip
                    v-if="isOfferActive"
                    color="success"
                    variant="tonal"
                    prepend-icon="tabler-check"
                  >
                    Vigente
                  </VChip>
                  <VChip
                    v-else
                    color="error"
                    variant="tonal"
                    prepend-icon="tabler-alert-circle"
                  >
                    No Vigente
                  </VChip>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" md="6">
              <VCard variant="outlined" class="pa-4 h-100">
                <div class="d-flex align-center mb-1">
                  <VIcon
                    icon="tabler-calendar-plus"
                    class="me-2 text-success"
                  />
                  <span class="text-caption text-medium-emphasis"
                    >Fecha de Inicio</span
                  >
                </div>
                <div class="text-h6 font-weight-medium">
                  {{ formatDate(props.offerData.start_date) }}
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" md="6">
              <VCard variant="outlined" class="pa-4 h-100">
                <div class="d-flex align-center mb-1">
                  <VIcon icon="tabler-calendar-minus" class="me-2 text-error" />
                  <span class="text-caption text-medium-emphasis"
                    >Fecha de Finalización</span
                  >
                </div>
                <div class="text-h6 font-weight-medium">
                  {{ formatDate(props.offerData.end_date) }}
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="tonal" @click="onCancel">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
/* No se requiere CSS adicional para scroll */
</style>
