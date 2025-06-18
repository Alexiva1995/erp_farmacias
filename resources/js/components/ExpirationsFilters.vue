<script setup>
import { computed } from 'vue'

const props = defineProps({
  searchQuery: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['update:searchQuery'])

const internalSearchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit('update:searchQuery', value),
})
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12">
          <VTextField
            v-model="internalSearchQuery"
            placeholder="Buscar por Producto, Principio Activo, Código de Barras..."
            density="compact"
            prepend-inner-icon="tabler-search"
            clearable
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
