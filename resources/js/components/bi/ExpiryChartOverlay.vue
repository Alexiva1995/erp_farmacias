<script setup>
defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  /** Mínima altura del contenedor (px) para el skeleton */
  minHeight: {
    type: Number,
    default: 350,
  },
})
</script>

<template>
  <Transition name="fade-overlay">
    <!-- Skeleton de barras animadas mientras carga — reemplaza el spinner básico -->
    <div
      v-if="loading"
      class="chart-overlay"
      :style="{ minHeight: `${minHeight}px` }"
      role="status"
      aria-label="Cargando gráfico..."
    >
      <div class="skeleton-chart">
        <!-- Barras de skeleton que simulan un gráfico de barras cargando -->
        <div
          v-for="n in 6"
          :key="n"
          class="skeleton-bar"
          :style="{ height: `${25 + (n * 11) % 65}%`, animationDelay: `${n * 0.12}s` }"
        />
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.chart-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  justify-content: center;
  align-items: flex-end;
  padding: 24px 32px;
  background: rgba(var(--v-theme-surface), 0.9);
  backdrop-filter: blur(3px);
  z-index: 5;
  border-radius: 8px;
}

/* Contenedor de barras skeleton */
.skeleton-chart {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  width: 100%;
  height: 70%;
}

.skeleton-bar {
  flex: 1;
  border-radius: 4px 4px 0 0;
  background: linear-gradient(
    90deg,
    rgba(var(--v-theme-on-surface), 0.06) 25%,
    rgba(var(--v-theme-on-surface), 0.12) 50%,
    rgba(var(--v-theme-on-surface), 0.06) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.6s infinite ease-in-out;
}

/* Shimmer animado */
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Transición suave al aparecer/desaparecer */
.fade-overlay-enter-active,
.fade-overlay-leave-active {
  transition: opacity 0.25s ease;
}

.fade-overlay-enter-from,
.fade-overlay-leave-to {
  opacity: 0;
}
</style>
