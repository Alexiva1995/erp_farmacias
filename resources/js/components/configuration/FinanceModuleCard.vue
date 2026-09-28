<script setup>
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
    class="rounded-lg cursor-pointer h-100 transition-all border"
    :class="[
      isActive ? 'bg-primary-lighten-5 border-primary' : 'border-opacity-25',
      { 'pointer-events-none opacity-50': isSaving }
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
              <VIcon :icon="view.icon" size="20" />
            </VAvatar>
            <div>
              <span class="text-subtitle-2 font-weight-bold d-inline-block text-truncate" style="max-width: 120px;" :title="view.title">
                {{ view.title }}
              </span>
              <div>
                <VChip
                  :color="isActive ? 'success' : 'secondary'"
                  size="x-small"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ isActive ? 'Visible' : 'Oculto' }}
                </VChip>
              </div>
            </div>
          </div>

          <VSwitch
            :model-value="isActive"
            :disabled="isSaving"
            density="comfortable"
            hide-details="auto"
            color="primary"
            class="ms-2"
            @click.stop="handleToggle"
          />
        </div>

        <p class="text-caption text-medium-emphasis mb-0 line-clamp-3">
          {{ view.description }}
        </p>
      </div>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}
</style>
