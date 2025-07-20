<script setup>
const props = defineProps({
  loadingApp: { type: Boolean, required: true },
});
</script>

<style lang="css">
.contenedor-loader {
  position: absolute;
  top: 0px;
  left: 0px;
  width: 100vw;
  height: 100vh;
  z-index: 1004;
  background-color: #11111191;
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
}
</style>

<template>
  <div class="contenedor-loader" v-if="props.loadingApp == true">
    <VProgressCircular :size="60" color="primary" indeterminate />
  </div>
</template>
