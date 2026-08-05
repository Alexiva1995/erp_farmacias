<script setup>
import { computed } from "vue";

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      total_count: 0,
      total_exempt: 0,
      total_iva: 0,
      grand_total: 0,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const cards = computed(() => [
  {
    title: "TOTAL FACTURADO",
    value: `Bs. ${formatCurrency(props.stats?.grand_total)}`,
    icon: "tabler-cash",
    color: "success",
    bgColor: "bg-success-tonal",
  },
  {
    title: "TOTAL EXENTO",
    value: `Bs. ${formatCurrency(props.stats?.total_exempt)}`,
    icon: "tabler-receipt-tax",
    color: "primary",
    bgColor: "bg-primary-tonal",
  },
  {
    title: "TOTAL IVA (16%)",
    value: `Bs. ${formatCurrency(props.stats?.total_iva)}`,
    icon: "tabler-percentage",
    color: "warning",
    bgColor: "bg-warning-tonal",
  },
  {
    title: "DOCUMENTOS",
    value: `${props.stats?.total_count || 0} Reg.`,
    icon: "tabler-file-description",
    color: "info",
    bgColor: "bg-info-tonal",
  },
]);
</script>

<template>
  <VRow class="mb-2">
    <VCol
      v-for="(card, index) in cards"
      :key="index"
      cols="12"
      sm="6"
      md="3"
    >
      <VCard border variant="flat" class="kpi-card pa-3">
        <VSkeletonLoader v-if="props.loading" type="list-item-two-line" />
        <div v-else class="d-flex align-center gap-3">
          <div :class="['pa-3', 'rounded-lg', card.bgColor]">
            <VIcon :icon="card.icon" size="24" :color="card.color" />
          </div>
          <div class="d-flex flex-column overflow-hidden">
            <span class="text-caption font-weight-bold text-disabled text-uppercase truncate">
              {{ card.title }}
            </span>
            <span class="text-h6 font-weight-black text-high-emphasis truncate">
              {{ card.value }}
            </span>
          </div>
        </div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.kpi-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

.bg-success-tonal {
  background-color: rgba(var(--v-theme-success), 0.1);
}

.bg-primary-tonal {
  background-color: rgba(var(--v-theme-primary), 0.1);
}

.bg-warning-tonal {
  background-color: rgba(var(--v-theme-warning), 0.1);
}

.bg-info-tonal {
  background-color: rgba(var(--v-theme-info), 0.1);
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
