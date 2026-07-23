<template>
  <div class="d-flex flex-column align-center justify-center py-12 px-4 text-center">
    <VAvatar
      color="grey-light"
      size="72"
      class="mb-4 text-secondary"
    >
      <VIcon
        :icon="icon"
        size="36"
      />
    </VAvatar>
    <h3 class="text-h6 font-weight-bold text-high-emphasis mb-1">
      {{ title }}
    </h3>
    <p class="text-body-2 text-medium-emphasis max-w-sm mb-4">
      {{ message }}
    </p>
    <slot name="actions"></slot>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'Sin registros'
  },
  message: {
    type: String,
    default: 'No se encontraron datos disponibles en este momento.'
  },
  icon: {
    type: String,
    default: 'tabler-info-circle'
  }
});
</script>

<style scoped>
.max-w-sm {
  max-width: 320px;
}
</style>
