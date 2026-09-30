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

// Emite el evento para alternar el estado del submódulo
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
                {{ isActive ? 'Habilitado' : 'Desactivado' }}
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

        <p class="text-caption text-medium-emphasis mb-2">
          {{ view.description }}
        </p>

        <div v-if="view.route" class="d-flex align-center gap-1 mt-auto pt-2">
          <VIcon icon="tabler-link" size="14" color="secondary" />
          <span class="text-caption text-disabled font-monospace">
            /telegram/{{ view.key }}
          </span>
        </div>
      </div>
    </VCardItem>
  </VCard>
</template>
