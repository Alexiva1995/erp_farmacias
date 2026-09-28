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
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['toggle'])

// Emite el cambio de estado si no está en proceso de guardado ni deshabilitado
const handleToggle = () => {
  if (props.isSaving || props.disabled) return
  emit('toggle', props.view.key)
}
</script>

<template>
  <VCard
    variant="outlined"
    :color="isActive ? 'primary' : undefined"
    class="h-100 rounded-lg cursor-pointer rrhh-card"
    :class="[
      isActive ? 'border-primary bg-primary-tonal' : 'border-opacity-50',
      { 'pointer-events-none opacity-60': isSaving || disabled }
    ]"
    @click="handleToggle"
  >
    <VCardItem class="pa-4 h-100 d-flex flex-column justify-space-between">
      <div>
        <div class="d-flex align-start justify-space-between gap-2 mb-3">
          <div class="d-flex align-center gap-3">
            <VAvatar
              :color="isActive ? 'primary' : 'default'"
              :variant="isActive ? 'flat' : 'tonal'"
              size="40"
              class="rounded-lg"
            >
              <VIcon :icon="view.icon" size="22" />
            </VAvatar>

            <div>
              <div class="text-subtitle-2 font-weight-bold line-clamp-1">
                {{ view.title }}
              </div>
              <VChip
                :color="isActive ? 'success' : 'secondary'"
                size="x-small"
                variant="tonal"
                class="mt-1 font-weight-medium"
              >
                {{ isActive ? 'Habilitado' : 'Deshabilitado' }}
              </VChip>
            </div>
          </div>

          <div class="d-flex align-center">
            <VProgressCircular
              v-if="isSaving"
              indeterminate
              size="20"
              width="2"
              color="primary"
            />
            <VSwitch
              v-else
              :model-value="isActive"
              :disabled="disabled || isSaving"
              density="comfortable"
              hide-details="auto"
              color="primary"
              @click.stop="handleToggle"
            />
          </div>
        </div>

        <p class="text-caption text-medium-emphasis mb-0">
          {{ view.description }}
        </p>
      </div>

      <div v-if="view.category" class="pt-3">
        <VChip
          size="x-small"
          variant="outlined"
          color="default"
          class="text-caption text-disabled"
        >
          {{ view.category }}
        </VChip>
      </div>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.bg-primary-tonal {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.rrhh-card {
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
</style>
