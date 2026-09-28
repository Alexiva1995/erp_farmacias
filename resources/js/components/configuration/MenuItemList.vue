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
  },
  isDirty: {
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
  return !props.menuItems.length || props.disabled || props.loading || !props.isDirty
})
</script>

<template>
  <VCard variant="outlined" class="bg-surface h-100 d-flex flex-column">
    <VCardItem class="pb-2">
      <template #prepend>
        <VIcon icon="tabler-layout-navbar" size="20" color="primary" class="mr-2" />
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">
        Estructura del Menú
      </VCardTitle>
      <VCardSubtitle class="text-caption text-medium-emphasis">
        Reordena y anida elementos para formar menús desplegables.
      </VCardSubtitle>
      <template #append>
        <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
          {{ menuItems.length }} {{ menuItems.length === 1 ? 'nivel principal' : 'niveles principales' }}
        </VChip>
      </template>
    </VCardItem>

    <VCardText class="d-flex flex-column flex-grow-1 pt-2">
      <!-- Contenedor de Elementos -->
      <div class="flex-grow-1 d-flex flex-column ga-3 mb-6 min-h-container">
        <!-- Estado Vacío -->
        <div
          v-if="!menuItems.length"
          class="border border-dashed d-flex flex-column align-center justify-center py-12 px-4 rounded text-center my-auto"
        >
          <VIcon icon="tabler-menu-order" size="44" class="text-medium-emphasis mb-2" />
          <span class="text-body-2 font-weight-medium text-high-emphasis">La estructura de navegación está vacía</span>
          <span class="text-caption text-medium-emphasis">Selecciona categorías o añade enlaces desde el panel izquierdo.</span>
        </div>

        <!-- Lista de Nivel Principal -->
        <TransitionGroup name="list" tag="div" class="d-flex flex-column ga-3">
          <div
            v-for="(item, idx) in menuItems"
            :key="item.id"
            class="d-flex flex-column ga-2"
          >
            <!-- Elemento Principal -->
            <div class="border pa-3 rounded bg-surface d-flex align-center justify-space-between item-card-shadow">
              <div class="d-flex align-center ga-3 overflow-hidden">
                <VIcon icon="tabler-grip-vertical" class="text-medium-emphasis cursor-move flex-shrink-0" size="18" />
                <div class="d-flex align-center ga-2 text-truncate">
                  <span class="text-body-2 font-weight-bold text-truncate">{{ item.label }}</span>
                  <VChip
                    size="x-small"
                    :color="item.type === 'category' ? 'info' : 'secondary'"
                    variant="tonal"
                    class="font-weight-medium flex-shrink-0"
                  >
                    {{ item.type === 'category' ? 'Categoría' : 'Enlace' }}
                  </VChip>
                </div>
              </div>

              <!-- Acciones de Fila -->
              <div class="d-flex align-center ga-1 flex-shrink-0">
                <VTooltip text="Subir posición" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      icon="tabler-arrow-up"
                      variant="text"
                      size="small"
                      :disabled="idx === 0 || disabled || loading"
                      @click="emit('move-up', idx)"
                    />
                  </template>
                </VTooltip>

                <VTooltip text="Bajar posición" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      icon="tabler-arrow-down"
                      variant="text"
                      size="small"
                      :disabled="idx === menuItems.length - 1 || disabled || loading"
                      @click="emit('move-down', idx)"
                    />
                  </template>
                </VTooltip>

                <VTooltip text="Anidar en el elemento superior" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      icon="tabler-indent-increase"
                      variant="text"
                      size="small"
                      :disabled="idx === 0 || disabled || loading"
                      @click="emit('make-child', idx)"
                    />
                  </template>
                </VTooltip>

                <VTooltip text="Eliminar elemento" location="top">
                  <template #activator="{ props: tooltipProps }">
                    <VBtn
                      v-bind="tooltipProps"
                      icon="tabler-trash"
                      variant="text"
                      size="small"
                      color="error"
                      :disabled="disabled || loading"
                      @click="emit('remove-item', idx)"
                    />
                  </template>
                </VTooltip>
              </div>
            </div>

            <!-- Submenús Anidados (Nivel 2) -->
            <div
              v-if="item.children && item.children.length"
              class="pl-6 d-flex flex-column ga-2 border-s-sm ml-4 py-1"
            >
              <TransitionGroup name="list" tag="div" class="d-flex flex-column ga-2">
                <div
                  v-for="(child, childIdx) in item.children"
                  :key="child.id"
                  class="border pa-2.5 rounded bg-surface d-flex align-center justify-space-between item-card-shadow"
                >
                  <div class="d-flex align-center ga-3 overflow-hidden">
                    <VIcon icon="tabler-corner-down-right" class="text-medium-emphasis flex-shrink-0" size="18" />
                    <div class="d-flex align-center ga-2 text-truncate">
                      <span class="text-body-2 font-weight-medium text-truncate">{{ child.label }}</span>
                      <VChip
                        size="x-small"
                        :color="child.type === 'category' ? 'info' : 'secondary'"
                        variant="tonal"
                        class="font-weight-medium flex-shrink-0"
                      >
                        {{ child.type === 'category' ? 'Categoría' : 'Enlace' }}
                      </VChip>
                    </div>
                  </div>

                  <!-- Acciones de Submenú -->
                  <div class="d-flex align-center ga-1 flex-shrink-0">
                    <VTooltip text="Promover a nivel principal" location="top">
                      <template #activator="{ props: tooltipProps }">
                        <VBtn
                          v-bind="tooltipProps"
                          icon="tabler-indent-decrease"
                          variant="text"
                          size="small"
                          :disabled="disabled || loading"
                          @click="emit('extract-child', { parentIndex: idx, childIndex: childIdx })"
                        />
                      </template>
                    </VTooltip>

                    <VTooltip text="Eliminar submenú" location="top">
                      <template #activator="{ props: tooltipProps }">
                        <VBtn
                          v-bind="tooltipProps"
                          icon="tabler-trash"
                          variant="text"
                          size="small"
                          color="error"
                          :disabled="disabled || loading"
                          @click="emit('remove-child', { parentIndex: idx, childIndex: childIdx })"
                        />
                      </template>
                    </VTooltip>
                  </div>
                </div>
              </TransitionGroup>
            </div>
          </div>
        </TransitionGroup>
      </div>

      <VDivider class="mb-4" />

      <!-- Pie de Card con Guardado -->
      <div class="d-flex flex-column flex-sm-row justify-space-between align-sm-center ga-3">
        <span class="text-caption text-medium-emphasis">
          * Los cambios se publicarán en la tienda tras guardar.
        </span>
        <VBtn
          color="primary"
          size="default"
          variant="flat"
          class="px-6 font-weight-bold"
          :loading="loading"
          :disabled="isSaveDisabled"
          prepend-icon="tabler-device-floppy"
          @click="emit('save')"
        >
          Guardar Menú
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.min-h-container {
  min-height: 220px;
}

.cursor-move {
  cursor: grab;
}

.border-dashed {
  border-style: dashed !important;
}

.item-card-shadow:hover {
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
}

/* Animaciones fluidas */
.list-move,
.list-enter-active,
.list-leave-active {
  transition: all 0.25s ease;
}

.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.list-leave-active {
  position: absolute;
  width: 100%;
}
</style>
