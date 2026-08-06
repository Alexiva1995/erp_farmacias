<script setup>
import { computed } from 'vue'

const props = defineProps({
  menuItems: {
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

const emit = defineEmits([
  'move-up',
  'move-down',
  'make-child',
  'extract-child',
  'remove-item',
  'remove-child',
  'save'
])

const isSaveDisabled = computed(() => {
  return !props.menuItems.length || props.disabled || props.loading
})
</script>

<template>
  <div class="border pa-6 rounded bg-white elevation-1 h-100 d-flex flex-column">
    <div class="d-flex align-center justify-space-between mb-2">
      <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2">
        <VIcon icon="tabler-layout-navbar" size="18" color="primary" />
        Estructura del Menú
      </h3>
      <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
        {{ menuItems.length }} {{ menuItems.length === 1 ? 'elemento' : 'elementos' }}
      </VChip>
    </div>
    
    <p class="text-caption text-muted mb-5">
      Organiza y estructura tus enlaces de navegación. Puedes reordenar y anidar elementos para crear submenús desplegables.
    </p>

    <!-- Lista de Enlaces -->
    <div class="flex-grow-1 d-flex flex-column gap-3 mb-6 min-h-200">
      <!-- Estado Vacío -->
      <div
        v-if="!menuItems.length"
        class="border border-dashed d-flex flex-column align-center justify-center py-12 px-4 rounded bg-grey-lighten-5 text-center"
      >
        <VIcon icon="tabler-menu-order" size="40" class="text-muted mb-2" />
        <span class="text-body-2 font-weight-medium text-muted">El menú está vacío</span>
        <span class="text-caption text-muted">Agrega categorías o enlaces personalizados desde el panel izquierdo.</span>
      </div>

      <TransitionGroup name="list" tag="div" class="d-flex flex-column gap-3">
        <!-- Iteración de items principales -->
        <div
          v-for="(item, idx) in menuItems"
          :key="item.id"
          class="d-flex flex-column gap-2 transition-all"
        >
          <!-- Item Principal -->
          <div class="border pa-3 rounded bg-white d-flex align-center justify-space-between hover-shadow">
            <div class="d-flex align-center gap-3">
              <VIcon icon="tabler-grip-vertical" class="text-muted cursor-move" size="18" />
              <div class="d-flex align-center gap-2">
                <span class="text-body-2 font-weight-bold tracking-wide">{{ item.label }}</span>
                <VChip
                  size="x-small"
                  :color="item.type === 'category' ? 'info' : 'secondary'"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  {{ item.type === 'category' ? 'Categoría' : 'Enlace' }}
                </VChip>
              </div>
            </div>

            <!-- Acciones de Ítem Principal -->
            <div class="d-flex align-center gap-1">
              <VBtn
                icon="tabler-arrow-up"
                variant="text"
                size="small"
                title="Mover arriba"
                :disabled="idx === 0 || disabled || loading"
                @click="emit('move-up', idx)"
              />
              <VBtn
                icon="tabler-arrow-down"
                variant="text"
                size="small"
                title="Mover abajo"
                :disabled="idx === menuItems.length - 1 || disabled || loading"
                @click="emit('move-down', idx)"
              />
              <VBtn
                icon="tabler-indent-increase"
                variant="text"
                size="small"
                title="Anidar en el elemento anterior"
                :disabled="idx === 0 || disabled || loading"
                @click="emit('make-child', idx)"
              />
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                title="Eliminar elemento"
                :disabled="disabled || loading"
                @click="emit('remove-item', idx)"
              />
            </div>
          </div>

          <!-- Items Hijos (Submenús) -->
          <div
            v-if="item.children && item.children.length"
            class="pl-6 d-flex flex-column gap-2 border-start ml-4 py-1"
          >
            <TransitionGroup name="list" tag="div" class="d-flex flex-column gap-2">
              <div
                v-for="(child, childIdx) in item.children"
                :key="child.id"
                class="border pa-2.5 rounded bg-grey-lighten-5 d-flex align-center justify-space-between hover-shadow transition-all"
              >
                <div class="d-flex align-center gap-3">
                  <VIcon icon="tabler-corner-down-right" class="text-muted" size="18" />
                  <div class="d-flex align-center gap-2">
                    <span class="text-body-2 font-weight-medium tracking-wide">{{ child.label }}</span>
                    <VChip
                      size="x-small"
                      :color="child.type === 'category' ? 'info' : 'secondary'"
                      variant="outlined"
                      class="font-weight-medium"
                    >
                      {{ child.type === 'category' ? 'Categoría' : 'Enlace' }}
                    </VChip>
                  </div>
                </div>

                <!-- Acciones de Hijos -->
                <div class="d-flex align-center gap-1">
                  <VBtn
                    icon="tabler-indent-decrease"
                    variant="text"
                    size="small"
                    title="Mover al menú principal"
                    :disabled="disabled || loading"
                    @click="emit('extract-child', { parentIndex: idx, childIndex: childIdx })"
                  />
                  <VBtn
                    icon="tabler-trash"
                    variant="text"
                    size="small"
                    color="error"
                    title="Eliminar submenú"
                    :disabled="disabled || loading"
                    @click="emit('remove-child', { parentIndex: idx, childIndex: childIdx })"
                  />
                </div>
              </div>
            </TransitionGroup>
          </div>
        </div>
      </TransitionGroup>
    </div>

    <!-- Footer con Botón Guardar -->
    <div class="d-flex justify-space-between align-center border-top pt-4 mt-auto">
      <span class="text-caption text-muted">
        * Recuerda hacer clic en guardar para aplicar los cambios en tu tienda.
      </span>
      <VBtn
        color="primary"
        size="large"
        variant="elevated"
        class="px-8 text-uppercase tracking-wider font-weight-bold"
        :loading="loading"
        :disabled="isSaveDisabled"
        prepend-icon="tabler-device-floppy"
        @click="emit('save')"
      >
        Guardar Menú
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.hover-shadow:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.cursor-move {
  cursor: grab;
}
.border-dashed {
  border-style: dashed !important;
}
.min-h-200 {
  min-height: 200px;
}
.gap-1 {
  gap: 4px;
}
.gap-2 {
  gap: 8px;
}
.gap-3 {
  gap: 12px;
}

/* Transiciones de animación */
.list-move,
.list-enter-active,
.list-leave-active {
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
.list-leave-active {
  position: absolute;
  width: 100%;
}
</style>
