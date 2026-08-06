<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true
  },
  formula: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  },
  icon: {
    type: String,
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

const emit = defineEmits(['select'])

const handleClick = () => {
  if (props.isSaving || props.isActive) return
  emit('select')
}
</script>

<template>
  <VCard
    variant="outlined"
    class="rounded-lg cursor-pointer formula-card h-100 transition-all"
    :class="[
      isActive ? 'is-active border-primary' : 'border-color-light opacity-80',
      { 'is-disabled pointer-events-none opacity-50': isSaving }
    ]"
    @click="handleClick"
  >
    <VCardItem class="py-5 px-5">
      <div class="d-flex align-start gap-4">
        <VAvatar
          :color="isActive ? 'primary' : 'secondary'"
          variant="tonal"
          size="46"
          class="rounded-lg transition-all flex-shrink-0"
        >
          <VIcon :icon="icon" size="24" />
        </VAvatar>
        <div class="d-flex flex-column gap-1 flex-grow-1">
          <div class="d-flex align-center justify-space-between w-100 mb-1">
            <span class="font-weight-black text-body-1 text-high-emphasis">{{ title }}</span>
            <VChip
              :color="isActive ? 'primary' : 'grey-darken-2'"
              size="x-small"
              variant="flat"
              class="font-weight-bold text-white"
            >
              {{ isActive ? 'Seleccionado' : 'Disponible' }}
            </VChip>
          </div>
          <span class="text-caption text-primary font-weight-bold tracking-wide">{{ formula }}</span>
          <p class="text-caption text-medium-emphasis mt-2 mb-0 leading-normal">
            {{ description }}
          </p>
        </div>
      </div>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.formula-card {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  border-width: 1.5px !important;
}

.formula-card:hover:not(.is-disabled) {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px -4px rgba(var(--v-theme-primary), 0.15) !important;
}

.formula-card.is-active {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
