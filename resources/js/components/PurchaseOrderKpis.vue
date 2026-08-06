<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  stats: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const { mobile } = useDisplay();
</script>

<template>
  <VRow class="mt-1 mb-5 ma-0 mx-n1" dense>
    <VCol
      v-for="(kpi, index) in [
        {
          title: 'En Espera',
          value: props.stats.pending_orders,
          color: 'warning',
          icon: 'tabler-clock',
          desc: 'Órdenes pendientes',
        },
        {
          title: 'En Camino',
          value: props.stats.sent_orders,
          color: 'info',
          icon: 'tabler-send',
          desc: 'En tránsito',
        },
        {
          title: 'Completadas',
          value: props.stats.completed_orders,
          color: 'success',
          icon: 'tabler-circle-check',
          desc: 'Recibidas con éxito',
        },
        {
          title: 'Inversión Total',
          value: `$ ${Number(props.stats.total_amount || 0).toLocaleString('es-ES', { maximumFractionDigits: 0 })}`,
          color: 'primary',
          icon: 'tabler-coin',
          desc: 'Monto total acumulado',
        },
      ]"
      :key="index"
      cols="6"
      md="3"
      class="pa-1"
    >
      <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
        <!-- Decoración de fondo -->
        <div
          class="card-bg-decoration"
          :style="{
            background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), 0.1), transparent)`,
          }"
        ></div>

        <VCardText :class="mobile ? 'pa-3' : 'pa-5'" class="relative-content">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar
              :color="kpi.color"
              variant="tonal"
              :size="mobile ? 32 : 48"
              rounded="lg"
              class="elevation-1"
            >
              <VIcon :icon="kpi.icon" :size="mobile ? 18 : 26" />
            </VAvatar>

            <div class="text-right">
              <span
                class="text-overline font-weight-bold text-disabled"
                style="letter-spacing: 1px !important"
              >
                {{ kpi.title }}
              </span>
              <h4 class="font-weight-black mt-1" :class="mobile ? 'text-sm' : 'text-h4'">
                <template v-if="props.loading">
                  <div
                    class="skeleton-loader d-inline-block rounded"
                    style="block-size: 24px; inline-size: 70px;"
                  ></div>
                </template>
                <template v-else>
                  {{ kpi.value }}
                </template>
              </h4>
            </div>
          </div>

          <VDivider class="mb-3 opacity-20" />

          <div class="d-flex align-center justify-space-between">
            <span class="text-caption font-weight-medium text-medium-emphasis">
              {{ kpi.desc }}
            </span>
            <VIcon
              icon="tabler-trending-up"
              size="16"
              :color="kpi.color"
              class="opacity-50"
            />
          </div>
        </VCardText>

        <!-- Borde de acento lateral -->
        <div
          class="accent-border"
          :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"
        ></div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.skeleton-loader {
  animation: pulse 1.5s infinite ease-in-out;
  background-color: rgba(var(--v-border-color), 0.12);
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  transition: all 0.3s ease;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 70%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 15%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-h4 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}
</style>
