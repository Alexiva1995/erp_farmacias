<script setup>
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
    :color="isActive ? 'primary' : undefined"
    class="rounded-lg cursor-pointer h-100 transition-swing"
    :class="{ 'pointer-events-none opacity-50': isSaving }"
    @click="handleToggle"
  >
    <VCardItem class="pa-4 h-100 d-flex flex-column justify-space-between">
      <div>
        <div class="d-flex align-center justify-space-between w-100 mb-3">
          <div class="d-flex align-center gap-3">
            <VAvatar
              :color="isActive ? 'primary' : 'secondary'"
              variant="tonal"
              size="40"
              rounded
            >
              <VIcon :icon="view.icon" size="22" />
            </VAvatar>
            <div>
              <div class="text-subtitle-1 font-weight-bold text-high-emphasis leading-tight">
                {{ view.title }}
              </div>
              <VChip
                :color="isActive ? 'success' : 'secondary'"
                size="x-small"
                variant="tonal"
                class="mt-1 font-weight-medium"
              >
                {{ isActive ? 'Activo en Menú' : 'Oculto' }}
              </VChip>
            </div>
          </div>

          <VSwitch
            :model-value="isActive"
            :disabled="isSaving"
            color="primary"
            density="comfortable"
            hide-details="auto"
            class="ms-2"
            @click.stop="handleToggle"
          />
        </div>

        <p class="text-caption text-medium-emphasis mb-0">
          {{ view.description }}
        </p>
      </div>
    </VCardItem>
  </VCard>
</template>
