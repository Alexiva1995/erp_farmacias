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
    label: customLabel.value,
    url: customUrl.value
  })

  customLabel.value = ''
  customUrl.value = ''
}
</script>

<template>
  <VCard variant="outlined" class="bg-surface">
    <VCardItem class="pb-2">
      <template #prepend>
        <VIcon icon="tabler-link" size="20" color="primary" class="mr-2" />
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">
        Enlace Personalizado
      </VCardTitle>
      <VCardSubtitle class="text-caption text-medium-emphasis">
        Crea accesos directos o URLs externas en la navegación.
      </VCardSubtitle>
    </VCardItem>

    <VCardText class="pt-2">
      <VForm @submit.prevent="handleSubmit" class="d-flex flex-column ga-3">
        <VTextField
          v-model="customLabel"
          label="Texto del enlace *"
          placeholder="Ej: OFERTAS, BLOG, SUCURSALES"
          variant="outlined"
          density="comfortable"
          hide-details="auto"
          :disabled="disabled"
          prepend-inner-icon="tabler-letter-t"
        />

        <VTextField
          v-model="customUrl"
          label="URL / Destino"
          placeholder="Ej: /promociones o #ofertas"
          variant="outlined"
          density="comfortable"
          hide-details="auto"
          :disabled="disabled"
          prepend-inner-icon="tabler-world"
        />

        <VBtn
          type="submit"
          variant="flat"
          color="primary"
          block
          class="mt-2 font-weight-bold"
          :disabled="!isFormValid || disabled"
          prepend-icon="tabler-plus"
        >
          Añadir al Menú
        </VBtn>
      </VForm>
    </VCardText>
  </VCard>
</template>
