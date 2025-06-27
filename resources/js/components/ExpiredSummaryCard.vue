<!-- /components/ExpiredSummaryCard.vue -->
<script setup>
import { computed } from "vue";

const props = defineProps({
  summary: {
    type: Object,
    required: true,
    default: () => ({
      month: "N/A",
      total_quantity: 0,
      total_lost_value: 0,
    }),
  },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["show-details"]);

const formattedMonth = computed(() => {
  if (!props.summary.month) return "Mes Desconocido";
  const [year, month] = props.summary.month.split("-");
  const date = new Date(year, month - 1);
  return date.toLocaleString("es-CO", { month: "long", year: "numeric" });
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value || 0);
};
</script>

<template>
  <VCard class="mb-4">
    <VCardTitle class="text-capitalize">{{ formattedMonth }}</VCardTitle>
    <VDivider />
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="4">
          <div class="d-flex align-center">
            <VAvatar color="error" rounded variant="tonal" class="me-4">
              <VIcon icon="tabler-package-off" />
            </VAvatar>
            <div>
              <span class="text-caption">Unidades Caducadas</span>
              <p class="text-h6 mb-0">{{ summary.total_quantity }}</p>
            </div>
          </div>
        </VCol>
        <VCol cols="12" md="4">
          <div class="d-flex align-center">
            <VAvatar color="warning" rounded variant="tonal" class="me-4">
              <VIcon icon="tabler-currency-dollar-off" />
            </VAvatar>
            <div>
              <span class="text-caption">Costo Perdido</span>
              <p class="text-h6 mb-0">
                {{ formatCurrency(summary.total_lost_value) }}
              </p>
            </div>
          </div>
        </VCol>
        <VCol cols="12" md="4" class="text-center text-md-right">
          <VBtn @click="emit('show-details', summary.month)">
            Ver Productos del Mes
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
