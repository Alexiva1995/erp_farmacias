<script setup>
import { defineProps } from 'vue';

const props = defineProps({
  logoSrc: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <div class="text-center pa-2 mb-2">
    <a href="#">
      <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
    </a>
  </div>
  <div class="text-center">
    <span class="headerPrint">J-50540695-7</span>
  </div>
  <div class="text-center">
    <span class="headerPrint">FARMACIA BARRIO SUCRE 2024, C.A.</span>
  </div>
  <div class="text-center">
    <span class="headerPrint">CALLE PRINCIPAL LOCAL 05 (L5)</span>
  </div>
  <div class="text-center">
    <span class="headerPrint">SECTOR BARRIO SUCRE LA FRIA TACHIRA</span>
  </div>
  <div class="text-center">
    <span class="headerPrint">ZONA POSTAL 5020</span>
  </div>
</template>
