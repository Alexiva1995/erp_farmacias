<script setup lang="js">
const props = defineProps({
  indexNavegacion: { type: Number, required: true },
  encontrados: { type: Number, default: 0 },
  noEncontrados: { type: Number, default: 0 },
});
const emit = defineEmits(['actualizarIndexNavegacion']);

const pasos = [
  { index: 1, label: 'Costo Elevado',  icon: 'tabler-trending-up',     desc: 'Revisa productos con precio mayor al esperado' },
  { index: 2, label: 'Costo Bajo',     icon: 'tabler-trending-down',    desc: 'Productos con precio favorable para comprar más' },
  { index: 3, label: 'Precio Estable', icon: 'tabler-minus',            desc: 'Productos con precio histórico estable' },
  { index: 4, label: 'Confirmar',      icon: 'tabler-clipboard-check',  desc: 'Revisa y confirma la orden de compra' },
];
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Header con KPIs minimalistas -->
    <div class="d-flex align-center justify-space-between px-6 py-3 border-b bg-var-theme-background">
      <div class="d-flex align-center gap-2">
        <span class="text-subtitle-2 font-weight-black text-uppercase text-disabled me-2">Análisis de Fallas:</span>
        <VTooltip text="Productos que tienen oferta de proveedores">
          <template #activator="{ props: tp }">
            <VChip v-bind="tp" color="success" size="small" variant="flat" density="comfortable" class="font-weight-bold">
              <VIcon start size="14" icon="tabler-check" />
              {{ props.encontrados }} Encontrados
            </VChip>
          </template>
        </VTooltip>
        <VTooltip text="Productos sin oferta disponible">
          <template #activator="{ props: tp }">
            <VChip v-bind="tp" color="warning" size="small" variant="flat" density="comfortable" class="font-weight-bold">
              <VIcon start size="14" icon="tabler-alert-triangle" />
              {{ props.noEncontrados }} No Encontrados
            </VChip>
          </template>
        </VTooltip>
      </div>
      
      <div class="text-caption text-disabled italic">
        Paso {{ props.indexNavegacion }} de 4
      </div>
    </div>

    <!-- Navegación -->
    <div class="d-flex align-center justify-space-between px-6 py-4">
      <!-- Botón anterior -->
      <VBtn
        variant="tonal"
        color="secondary"
        size="small"
        :disabled="props.indexNavegacion <= 1"
        prepend-icon="tabler-chevron-left"
        @click="emit('actualizarIndexNavegacion', props.indexNavegacion - 1)"
      >
        Anterior
      </VBtn>

      <!-- Stepper central -->
      <div class="d-flex align-center gap-1 flex-wrap justify-center flex-grow-1 mx-4">
        <template v-for="(paso, i) in pasos" :key="paso.index">
          <!-- Paso -->
          <div
            class="step-item d-flex flex-column align-center cursor-pointer"
            :class="{ 'step-active': props.indexNavegacion === paso.index, 'step-done': props.indexNavegacion > paso.index }"
            style=" max-inline-size: 110px;min-inline-size: 80px;"
            @click="emit('actualizarIndexNavegacion', paso.index)"
          >
            <VAvatar
              :color="props.indexNavegacion > paso.index ? 'success' : props.indexNavegacion === paso.index ? 'primary' : 'secondary'"
              :variant="props.indexNavegacion >= paso.index ? 'elevated' : 'tonal'"
              size="40"
              class="mb-1 step-avatar"
            >
              <VIcon
                :icon="props.indexNavegacion > paso.index ? 'tabler-check' : paso.icon"
                size="20"
              />
            </VAvatar>
            <span class="text-caption font-weight-bold text-center" style="line-height: 1.2;">{{ paso.label }}</span>
            <span
              v-if="props.indexNavegacion === paso.index"
              class="text-caption text-disabled text-center mt-1"
              style="font-size: 10px; line-height: 1.2;"
            >{{ paso.desc }}</span>
          </div>

          <!-- Conector -->
          <div
            v-if="i < pasos.length - 1"
            class="step-connector mx-1"
            :class="{ 'step-connector-done': props.indexNavegacion > paso.index }"
          />
        </template>
      </div>

      <!-- Botón siguiente -->
      <VBtn
        variant="tonal"
        color="primary"
        size="small"
        :disabled="props.indexNavegacion >= 4"
        append-icon="tabler-chevron-right"
        @click="emit('actualizarIndexNavegacion', props.indexNavegacion + 1)"
      >
        Siguiente
      </VBtn>
    </div>

    <!-- Barra de progreso -->
    <VProgressLinear
      :model-value="(props.indexNavegacion / 4) * 100"
      color="primary"
      height="3"
      rounded
    />
  </VCard>
</template>

<style scoped>
.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.03);
}

.step-avatar {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.step-item {
  position: relative;
  transition: all 0.2s ease;
  z-index: 1;
}

.step-item:hover .step-avatar {
  transform: translateY(-2px) scale(1.05);
}

.step-connector {
  flex-shrink: 0;
  border-radius: 4px;
  background: rgba(var(--v-border-color), 0.15);
  block-size: 3px;
  inline-size: 40px;
  transition: all 0.4s ease;
}

.step-connector-done {
  background: rgb(var(--v-theme-success));
}

.step-active span {
  color: rgb(var(--v-theme-primary)) !important;
}

.step-done span {
  color: rgb(var(--v-theme-success)) !important;
}
</style>
