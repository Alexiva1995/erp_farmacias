<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    default: 'tabler-adjustments'
  },
  modelValue: {
    type: Boolean,
    default: false
  },
  badgeText: {
    type: String,
    required: true
  },
  badgeColor: {
    type: String,
    default: 'primary'
  },
  label: {
    type: String,
    required: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const handleToggle = (val) => {
  if (props.isSaving) return
  emit('update:modelValue', val)
}
</script>

<template>
  <VCard
    variant="outlined"
    class="rounded-lg expense-setting-card h-100 transition-all"
    :class="[
      modelValue ? 'is-active border-primary' : 'border-color-light opacity-90',
      { 'is-disabled pointer-events-none opacity-50': isSaving }
    ]"
  >
    <VCardItem class="py-4 px-4 h-100 d-flex flex-column justify-space-between">
      <div>
        <!-- Encabezado con Icono, Titulo y Badge -->
        <div class="d-flex align-center justify-space-between w-100 mb-3">
          <div class="d-flex align-center gap-2">
            <VAvatar
              :color="modelValue ? 'primary' : 'secondary'"
              variant="tonal"
              size="38"
              class="rounded-lg"
            >
              <VIcon :icon="icon" size="20" />
            </VAvatar>
            <div>
              <h3 class="text-subtitle-2 font-weight-bold mb-0">
                {{ title }}
              </h3>
              <VChip
                :color="badgeColor"
                size="x-small"
                variant="flat"
                class="mt-1 font-weight-medium"
              >
                {{ badgeText }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Descripción corta -->
        <p class="text-caption text-medium-emphasis mb-4 leading-tight">
          {{ description }}
        </p>
      </div>

      <!-- Control Switch -->
      <div class="pt-2 border-t border-color-light">
        <VSwitch
          :model-value="modelValue"
          :label="label"
          color="primary"
          density="compact"
          hide-details
          :disabled="isSaving"
          @update:model-value="handleToggle"
        />
      </div>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.expense-setting-card {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  border-width: 1.5px !important;
}

.expense-setting-card:hover:not(.is-disabled) {
  box-shadow: 0 8px 20px -4px rgba(var(--v-theme-primary), 0.12) !important;
}

.expense-setting-card.is-active {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
