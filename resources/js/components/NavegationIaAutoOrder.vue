<script setup lang="js">

const prop= defineProps({
  indexNavegacion: {type: Number, required: true}
})

const emit= defineEmits(['actualizarIndexNavegacion'])
</script>
<template>
  <VCard class="mb-5" style="padding-bottom: 24px">
    <VCardTitle style="text-align: center" class="text-primary"
      >IA ASSISTENCE</VCardTitle
    >
    <VRow align="center" justify="center">
      <VCol sm="auto" md="auto" lg="auto">
        <VIcon
          icon="tabler-shopping-bag-exclamation"
          size="70"
          :color="indexNavegacion >= 1 ? 'primary' : ''"
          @click="() => emit('actualizarIndexNavegacion', 1)"
        />
      </VCol>
      <VCol sm="auto" md="auto" lg="auto">
        <VIcon
          icon="tabler-chevron-right"
          size="20"
          :color="indexNavegacion >= 1 ? 'primary' : ''"
          @click="() => emit('actualizarIndexNavegacion', 1)"
        />
      </VCol>
      <VCol sm="auto" md="auto" lg="auto">
        <VIcon
          icon="tabler-discount"
          size="70"
          :color="indexNavegacion >= 2 ? 'primary' : ''"
          @click="() => emit('actualizarIndexNavegacion', 2)"
        />
      </VCol>
      <VCol sm="auto" md="auto" lg="auto">
        <VIcon
          icon="tabler-chevron-right"
          size="20"
          :color="indexNavegacion >= 2 ? 'primary' : ''"
          @click="() => emit('actualizarIndexNavegacion', 2)"
        />
      </VCol>
      <VCol sm="auto" md="auto" lg="auto">
        <VIcon
          icon="tabler-zoom-exclamation"
          size="70"
          :color="indexNavegacion >= 3 ? 'primary' : ''"
          @click="() => emit('actualizarIndexNavegacion', 3)"
        />
      </VCol>
      <VCol sm="auto" md="auto" lg="auto">
        <VIcon
          icon="tabler-chevron-right"
          size="20"
          :color="indexNavegacion >= 3 ? 'primary' : ''"
          @click="() => emit('actualizarIndexNavegacion', 3)"
        />
      </VCol>
      <VCol sm="auto" md="auto" lg="auto">
        <VIcon
          icon="tabler-receipt"
          size="70"
          :color="indexNavegacion >= 4 ? 'primary' : ''"
          @click="() => emit('actualizarIndexNavegacion', 4)"
        />
      </VCol>
    </VRow>
  </VCard>
</template>
