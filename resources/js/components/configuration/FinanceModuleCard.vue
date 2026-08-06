<script setup>
import { computed } from 'vue'

const props = defineProps({
  view: {
    type: Object,
    required: true
  },
  isActive: {
    type: Boolean,
    default: false
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['toggle'])

const handleToggle = () => {
  if (props.isSaving) return
  emit('toggle', props.view.key)
}
</script>

<template>
  <VCard
    variant="outlined"
    class="rounded-lg cursor-pointer finance-module-card h-100 transition-all"
    :class="[
      isActive ? 'is-active border-primary' : 'opacity-70 border-color-light',
      { 'is-disabled pointer-events-none opacity-50': isSaving }
    ]"
    @click="handleToggle"
  >
    <VCardItem class="py-4 px-4 h-100 d-flex flex-column justify-space-between">
      <div>
        <div class="d-flex align-center justify-space-between w-100 mb-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              :color="isActive ? 'primary' : 'secondary'"
              variant="tonal"
              size="36"
              class="rounded-lg"
            >
              <VIcon :icon="view.icon" size="18" />
            </VAvatar>
            <div>
              <h3 class="text-subtitle-2 font-weight-bold mb-0 text-truncate" style="max-width: 120px;" :title="view.title">
                {{ view.title }}
              </h3>
              <VChip
                :color="isActive ? 'success' : 'grey-darken-1'"
                size="x-small"
                variant="flat"
                class="mt-1 font-weight-bold text-white"
              >
                {{ isActive ? 'Visible' : 'Oculto' }}
              </VChip>
            </div>
          </div>

          <VSwitch
            :model-value="isActive"
            :disabled="isSaving"
            density="compact"
            hide-details
            color="primary"
            class="ms-2"
            @click.stop="handleToggle"
          />
        </div>

        <p class="text-caption text-medium-emphasis mb-0 leading-tight description-text">
          {{ view.description }}
        </p>
      </div>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.finance-module-card {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  border-width: 1.5px !important;
}

.finance-module-card:hover:not(.is-disabled) {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px -4px rgba(var(--v-theme-primary), 0.15) !important;
}

.finance-module-card.is-active {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}

.description-text {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.75rem !important;
}
</style>
