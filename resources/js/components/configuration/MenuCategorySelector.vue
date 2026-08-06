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
  <div class="border pa-6 rounded bg-white elevation-1">
    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-3 d-flex align-center gap-2">
      <VIcon icon="tabler-tags" size="18" color="primary" />
      Categorías
    </h3>
    <p class="text-caption text-muted mb-4">
      Selecciona categorías de productos para añadirlas a la estructura de navegación.
    </p>

    <!-- Campo de Búsqueda -->
    <VTextField
      v-model="searchQuery"
      placeholder="Buscar categoría..."
      variant="outlined"
      density="compact"
      hide-details
      clearable
      prepend-inner-icon="tabler-search"
      class="mb-3"
      :disabled="disabled || loading"
    />

    <!-- Estado de Carga -->
    <div v-if="loading" class="d-flex flex-column gap-2 py-2">
      <VSkeletonLoader type="list-item" v-for="n in 3" :key="n" class="border rounded" />
    </div>

    <!-- Lista de Categorías -->
    <div v-else class="d-flex flex-column gap-2 max-h-60 overflow-y-auto pr-1">
      <div
        v-for="cat in filteredCategories"
        :key="cat.id"
        class="d-flex align-center justify-space-between border pa-2.5 rounded hover-bg-light cursor-pointer transition-all"
        :class="{ 'opacity-50 pointer-events-none': disabled }"
        @click="handleAdd(cat)"
      >
        <span class="text-body-2 font-weight-medium text-truncate">{{ cat.name }}</span>
        <VBtn
          icon="tabler-plus"
          size="x-small"
          variant="tonal"
          color="primary"
          title="Añadir al menú"
          :disabled="disabled"
        />
      </div>

      <!-- Estado Vacío -->
      <div v-if="!filteredCategories.length" class="text-caption text-center text-muted py-6 border rounded bg-grey-lighten-5">
        {{ searchQuery ? 'No se encontraron categorías.' : 'No hay categorías disponibles.' }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.hover-bg-light:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
.max-h-60 {
  max-height: 280px;
}
.gap-2 {
  gap: 8px;
}
</style>
