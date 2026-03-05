<script setup lang="js">
const props = defineProps({
  indexNavegacion: { type: Number, required: true }
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
  <VCard class="mb-5 overflow-hidden">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between px-6 pt-5 pb-3">
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
.step-avatar {
  transition: all 0.3s ease;
}

.step-item {
  transition: opacity 0.2s;
}

.step-item:hover .step-avatar {
  transform: scale(1.1);
}

.step-connector {
  flex-shrink: 0;
  border-radius: 2px;
  background: rgba(var(--v-border-color), 0.3);
  block-size: 2px;
  inline-size: 32px;
  transition: background 0.3s;
}

.step-connector-done {
  background: rgb(var(--v-theme-success));
}
</style>
