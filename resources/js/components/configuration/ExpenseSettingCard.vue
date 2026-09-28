<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    default: 'tabler-adjustments',
  },
  modelValue: {
    type: Boolean,
    default: false,
  },
  badgeText: {
    type: String,
    required: true,
  },
  badgeColor: {
    type: String,
    default: 'primary',
  },
  label: {
    type: String,
    required: true,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
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
    class="rounded-lg h-100 transition-all border-opacity-100"
    :class="[
      modelValue ? 'border-primary bg-surface' : 'border-secondary',
      { 'opacity-50 pointer-events-none': isSaving },
    ]"
  >
    <VCardItem class="py-4 px-4 h-100 d-flex flex-column justify-space-between">
      <div>
        <!-- Encabezado con Icono, Título y Badge de Estado -->
        <div class="d-flex align-start justify-space-between w-100 mb-3">
          <div class="d-flex align-center gap-3">
            <VAvatar
              :color="modelValue ? 'primary' : 'secondary'"
              variant="tonal"
              size="42"
              class="rounded-lg"
            >
              <VIcon :icon="icon" size="22" />
            </VAvatar>
            <div>
              <h3 class="text-subtitle-1 font-weight-bold mb-0">
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

        <!-- Descripción Funcional -->
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ description }}
        </p>
      </div>

      <!-- Control de Alternancia (Switch Estandarizado) -->
      <div class="pt-3 border-t">
        <VSwitch
          :model-value="modelValue"
          :label="label"
          color="primary"
          density="comfortable"
          hide-details="auto"
          :disabled="isSaving"
          @update:model-value="handleToggle"
        />
      </div>
    </VCardItem>
  </VCard>
</template>

