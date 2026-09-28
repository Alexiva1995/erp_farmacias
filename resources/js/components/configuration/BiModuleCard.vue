<script setup>
// Props para el componente de tarjeta de módulo BI
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

// Manejo de clic para alternar estado
const handleToggle = () => {
  if (props.isSaving) return
  emit('toggle', props.view.key)
}
</script>

<template>
  <VCard
    variant="outlined"
    :color="isActive ? 'primary' : undefined"
    class="rounded-lg h-100 d-flex flex-column justify-space-between transition-swing cursor-pointer"
    :class="{ 'opacity-60 pointer-events-none': isSaving }"
    hover
    @click="handleToggle"
  >
    <VCardItem class="pb-2">
      <div class="d-flex align-center justify-space-between gap-2 mb-3">
        <div class="d-flex align-center gap-3 overflow-hidden">
          <VAvatar
            :color="isActive ? 'primary' : 'secondary'"
            variant="tonal"
            size="40"
            rounded
          >
            <VIcon :icon="view.icon" size="22" />
          </VAvatar>

          <div class="overflow-hidden">
            <h4 class="text-subtitle-1 font-weight-bold text-truncate" :title="view.title">
              {{ view.title }}
            </h4>
            <VChip
              :color="isActive ? 'success' : 'secondary'"
              size="x-small"
              variant="tonal"
              class="font-weight-medium mt-1"
            >
              {{ isActive ? 'Habilitado' : 'Deshabilitado' }}
            </VChip>
          </div>
        </div>

        <VSwitch
          :model-value="isActive"
          :disabled="isSaving"
          color="primary"
          density="comfortable"
          hide-details="auto"
          @click.stop="handleToggle"
        />
      </div>

      <p class="text-body-2 text-medium-emphasis mb-0">
        {{ view.description }}
      </p>
    </VCardItem>

    <VCardActions class="pt-0 px-4 pb-3 justify-end">
      <VTooltip text="Alternar visibilidad del módulo en la navegación lateral" location="top">
        <template #activator="{ props: tooltipProps }">
          <VIcon
            v-bind="tooltipProps"
            icon="tabler-info-circle"
            size="18"
            class="text-disabled cursor-pointer"
          />
        </template>
      </VTooltip>
    </VCardActions>
  </VCard>
</template>
