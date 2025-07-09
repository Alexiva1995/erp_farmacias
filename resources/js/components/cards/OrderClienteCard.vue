<script setup></script>
<template>
  <VCard title="No hay orden en proceso" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12">
          <AppTextField
            placeholder="Ingrese identificación"
            clearable
            class="flex-grow-1"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <!-- Puedes añadir un botón para limpiar si lo deseas en el futuro -->
      <VSpacer />
      <VBtn color="primary" prepend-icon="tabler-plus" @click="emit('add-lot')">
        Realizar pedido
      </VBtn>
    </VCardActions>
  </VCard>
</template>
