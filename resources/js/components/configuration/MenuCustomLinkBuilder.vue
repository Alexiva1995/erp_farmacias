<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['add-custom-link'])

const customLabel = ref('')
const customUrl = ref('')

const isFormValid = computed(() => {
  return customLabel.value.trim().length > 0
})

const handleSubmit = () => {
  if (!isFormValid.value || props.disabled) return

  emit('add-custom-link', {
    label: customLabel.value.trim().toUpperCase(),
    url: customUrl.value.trim() || '#'
  })

  customLabel.value = ''
  customUrl.value = ''
}
</script>

<template>
  <div class="border pa-6 rounded bg-white elevation-1">
    <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider mb-3 d-flex align-center gap-2">
      <VIcon icon="tabler-link" size="18" color="primary" />
      Enlace Personalizado
    </h3>
    <p class="text-caption text-muted mb-4">
      Crea enlaces personalizados o externos para añadir a tu navegación.
    </p>

    <VForm @submit.prevent="handleSubmit" class="d-flex flex-column gap-3">
      <VTextField
        v-model="customLabel"
        label="Etiqueta del enlace *"
        placeholder="Ej: OFERTAS, NOSOTROS"
        variant="outlined"
        density="compact"
        hide-details="auto"
        :disabled="disabled"
        prepend-inner-icon="tabler-letter-t"
      />

      <VTextField
        v-model="customUrl"
        label="URL / Enlace"
        placeholder="Ej: #ofertas, /contacto"
        variant="outlined"
        density="compact"
        hide-details="auto"
        :disabled="disabled"
        prepend-inner-icon="tabler-world"
      />

      <VBtn
        type="submit"
        variant="elevated"
        color="primary"
        class="mt-2 text-uppercase tracking-wider font-weight-bold"
        block
        :disabled="!isFormValid || disabled"
        prepend-icon="tabler-plus"
      >
        Añadir al Menú
      </VBtn>
    </VForm>
  </div>
</template>

<style scoped>
.gap-3 {
  gap: 12px;
}
</style>
