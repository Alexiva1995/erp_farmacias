<script setup>
const props = defineProps({
  loadingApp: { type: Boolean, required: true },
});
</script>

<style lang="css">
.contenedor-loader {
  position: absolute;
  z-index: 1004;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  background-color: #11111191;
  block-size: 100vh;
  inline-size: 100vw;
  inset-block-start: 0;
  inset-inline-start: 0;
}
</style>

<template>
  <div class="contenedor-loader" v-if="props.loadingApp == true">
    <VProgressCircular :size="60" color="primary" indeterminate />
  </div>
</template>
