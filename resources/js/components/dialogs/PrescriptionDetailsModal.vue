<script setup>
import { computed } from "vue";

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

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  // Ajusta esto según tu formato preferido.
  // toLocaleDateString() usa la zona horaria local del navegador.
  return new Date(dateString).toLocaleDateString("es-ES");
};

const getStatusText = (isActive) => {
  return isActive ? "Activa" : "Inactiva";
};

const getStatusColor = (isActive) => {
  return isActive ? "success" : "error";
};

// Lógica simple para saber si está vigente por fechas
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
  <VDialog v-model="dialogVisible" max-width="900px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <span class="text-h5 font-weight-bold"
          >Detalles de Oferta de Receta</span
        >
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div v-if="props.prescriptionData">
          <h3 class="text-h6 mb-4">Información General</h3>

          <VRow>
            <VCol cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100">
                <div class="d-flex align-center mb-2">
                  <VIcon
                    icon="tabler-file-description"
                    class="me-2 text-medium-emphasis"
                  />
                  <span
                    class="text-caption text-medium-emphasis font-weight-bold"
                    >NOMBRE DE LA OFERTA</span
                  >
                </div>
                <div class="text-body-1 font-weight-bold text-high-emphasis">
                  {{ prescriptionData.name || "Sin nombre" }}
                </div>
                <div class="text-caption text-disabled mt-1">
                  ID: {{ prescriptionData.id }}
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
                  {{ prescriptionData.discount_percentage }}%
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
                    :color="getStatusColor(prescriptionData.is_active)"
                    variant="elevated"
                  >
                    {{ getStatusText(prescriptionData.is_active) }}
                  </VChip>

                  <VChip
                    v-if="isOfferCurrentlyActive"
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
                  {{ formatDate(prescriptionData.start_date) }}
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
                  {{ formatDate(prescriptionData.end_date) }}
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="tonal" @click="closeModal">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
