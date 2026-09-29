<script setup>
import { computed } from 'vue'

const props = defineProps({
  view: {
    type: Object,
    required: true,
  },
  isActive: {
    type: Boolean,
    default: false,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['toggle'])

// Despacha el evento de alternancia si los permisos y el estado lo permiten
const handleToggle = () => {
  if (props.isSaving || !props.canEdit) return
  emit('toggle', props.view.key)
}
</script>

<template>
  <VCard
    variant="outlined"
    class="rounded-lg h-100 transition-swing"
    :class="[
      isActive ? 'border-primary' : 'border-color-light',
      { 'cursor-pointer': canEdit && !isSaving, 'opacity-60 pointer-events-none': !canEdit || isSaving }
    ]"
    @click="handleToggle"
  >
    <VCardItem class="py-4 px-4 h-100 d-flex flex-column justify-space-between">
      <div>
        <div class="d-flex align-center justify-space-between w-100 mb-3">
          <div class="d-flex align-center gap-3 overflow-hidden">
            <VAvatar
              :color="isActive ? 'primary' : 'secondary'"
              variant="tonal"
              size="40"
              class="rounded-lg flex-shrink-0"
            >
              <VIcon :icon="view.icon" size="22" />
            </VAvatar>

            <div class="overflow-hidden">
              <h3 class="text-subtitle-2 font-weight-bold mb-0 text-truncate" :title="view.title">
                {{ view.title }}
              </h3>
              <VChip
                :color="isActive ? 'success' : 'secondary'"
                size="x-small"
                variant="tonal"
                class="mt-1 font-weight-bold"
              >
                {{ isActive ? 'Activo' : 'Inactivo' }}
              </VChip>
            </div>
          </div>

          <VTooltip
            location="top"
            :text="!canEdit ? 'Sin permisos de edición' : (isActive ? 'Desactivar módulo' : 'Activar módulo')"
          >
            <template #activator="{ props: tooltipProps }">
              <div v-bind="tooltipProps">
                <VSwitch
                  :model-value="isActive"
                  :disabled="isSaving || !canEdit"
                  density="comfortable"
                  hide-details="auto"
                  color="primary"
                  class="ms-2"
                  @click.stop="handleToggle"
                />
              </div>
            </template>
          </VTooltip>
        </div>

        <p class="text-caption text-medium-emphasis mb-0 line-clamp-2">
          {{ view.description }}
        </p>
      </div>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
