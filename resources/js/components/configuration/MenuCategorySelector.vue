<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  categories: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['add-category'])

const searchQuery = ref('')

const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) return props.categories
  const query = searchQuery.value.toLowerCase().trim()
  return props.categories.filter(c => c.name && c.name.toLowerCase().includes(query))
})

const handleAdd = (cat) => {
  if (props.disabled || props.loading) return
  emit('add-category', cat)
}
</script>

<template>
  <VCard variant="outlined" class="bg-surface">
    <VCardItem class="pb-2">
      <template #prepend>
        <VIcon icon="tabler-tags" size="20" color="primary" class="mr-2" />
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">
        Categorías
      </VCardTitle>
      <VCardSubtitle class="text-caption text-medium-emphasis">
        Añade categorías de productos al menú principal.
      </VCardSubtitle>
    </VCardItem>

    <VCardText class="pt-2">
      <!-- Buscador de Categorías con Estándar Corporativo -->
      <VTextField
        v-model="searchQuery"
        placeholder="Buscar categoría..."
        variant="outlined"
        density="comfortable"
        hide-details="auto"
        clearable
        prepend-inner-icon="tabler-search"
        class="mb-4"
        :disabled="disabled || loading"
      />

      <!-- Loader Skeleton -->
      <div v-if="loading" class="d-flex flex-column ga-2 py-2">
        <VSkeletonLoader type="list-item" v-for="n in 3" :key="n" />
      </div>

      <!-- Lista de Categorías -->
      <div v-else class="d-flex flex-column ga-2 categories-scroll-container pr-1">
        <div
          v-for="cat in filteredCategories"
          :key="cat.id"
          class="d-flex align-center justify-space-between pa-3 rounded border cursor-pointer category-item-hover transition-swing"
          :class="{ 'opacity-50 pointer-events-none': disabled }"
          @click="handleAdd(cat)"
        >
          <span class="text-body-2 font-weight-medium text-truncate pr-2">{{ cat.name }}</span>
          <VBtn
            icon="tabler-plus"
            size="x-small"
            variant="tonal"
            color="primary"
            :disabled="disabled"
            aria-label="Añadir categoría al menú"
          />
        </div>

        <!-- Estado Vacío -->
        <div v-if="!filteredCategories.length" class="text-caption text-center text-medium-emphasis py-6 border rounded border-dashed">
          {{ searchQuery ? 'No se encontraron categorías coincidentes.' : 'No hay categorías disponibles.' }}
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.categories-scroll-container {
  max-height: 280px;
  overflow-y: auto;
}

.category-item-hover:hover {
  background-color: rgba(var(--v-theme-primary), 0.05);
  border-color: rgba(var(--v-theme-primary), 0.25) !important;
}

.border-dashed {
  border-style: dashed !important;
}
</style>
