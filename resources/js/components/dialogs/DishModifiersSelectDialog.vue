<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  dish: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const selectedModifiers = ref([])
const notes = ref('')
const quantity = ref(1)

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      selectedModifiers.value = []
      notes.value = ''
      quantity.value = 1
    }
  }
)

const isOptionSelected = (optionId) => {
  return selectedModifiers.value.some((m) => m.modifier_option_id === optionId)
}

const toggleOption = (group, option) => {
  const isSelected = isOptionSelected(option.id)
  
  if (isSelected) {
    selectedModifiers.value = selectedModifiers.value.filter(
      (m) => m.modifier_option_id !== option.id
    )
  } else {
    // Si es selección única (max_selections === 1)
    if (group.max_selections === 1) {
      const groupOptionIds = (group.options || []).map((o) => o.id)
      selectedModifiers.value = selectedModifiers.value.filter(
        (m) => !groupOptionIds.includes(m.modifier_option_id)
      )
    }

    selectedModifiers.value.push({
      modifier_option_id: option.id,
      name: option.name,
      modifier_type: option.modifier_type,
      extra_price_usd: option.extra_price_usd,
      product_id: option.product_id,
      portion: option.portion,
    })
  }
}

const handleConfirm = () => {
  emit('confirm', {
    dish: props.dish,
    quantity: quantity.value,
    modifiers: selectedModifiers.value,
    notes: notes.value,
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="580"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="dish" class="rounded-lg">
      <VCardTitle class="d-flex align-center justify-space-between pa-4 bg-primary text-white">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-tools-kitchen-2" />
          <span class="text-h6 font-weight-bold">{{ dish.name }}</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          @click="emit('update:modelValue', false)"
        />
      </VCardTitle>

      <VCardText class="pa-4">
        <div class="d-flex align-center justify-space-between mb-4 pb-2 border-b">
          <span class="text-subtitle-2 text-medium-emphasis">Precio Base:</span>
          <span class="text-h6 font-weight-bold text-primary">
            ${{ Number(dish.designated_price || 0).toFixed(2) }}
          </span>
        </div>

        <!-- Grupos de Modificadores -->
        <div
          v-for="group in dish.modifier_groups || []"
          :key="group.id"
          class="mb-4"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="font-weight-bold text-body-1">{{ group.name }}</span>
            <VChip
              size="x-small"
              :color="group.min_selections > 0 ? 'warning' : 'default'"
              variant="tonal"
            >
              {{ group.min_selections > 0 ? 'Requerido' : 'Opcional' }}
            </VChip>
          </div>

          <VRow dense>
            <VCol
              v-for="opt in group.options || []"
              :key="opt.id"
              cols="12"
              sm="6"
            >
              <VCard
                variant="outlined"
                :color="isOptionSelected(opt.id) ? 'primary' : undefined"
                class="pa-2 cursor-pointer transition-swing rounded-lg"
                :class="{ 'border-primary bg-primary-subtle': isOptionSelected(opt.id) }"
                @click="toggleOption(group, opt)"
              >
                <div class="d-flex align-center justify-space-between">
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      :icon="isOptionSelected(opt.id) ? 'tabler-circle-check' : 'tabler-circle'"
                      :color="isOptionSelected(opt.id) ? 'primary' : 'disabled'"
                      size="20"
                    />
                    <span class="text-body-2 font-weight-medium">{{ opt.name }}</span>
                  </div>
                  <VChip
                    v-if="Number(opt.extra_price_usd) > 0"
                    size="x-small"
                    color="success"
                    variant="tonal"
                  >
                    +${{ Number(opt.extra_price_usd).toFixed(2) }}
                  </VChip>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- Instrucciones de Cocina -->
        <div class="mt-4">
          <VTextarea
            v-model="notes"
            label="Notas de cocina o preferencias"
            placeholder="Ej: Bien cocido, sin salsa, aderezo aparte..."
            rows="2"
            auto-grow
            variant="outlined"
            density="comfortable"
          />
        </div>

        <!-- Cantidad -->
        <div class="d-flex align-center justify-space-between mt-4 pa-2 bg-grey-lighten-4 rounded-lg">
          <span class="font-weight-bold">Cantidad de Platos:</span>
          <div class="d-flex align-center gap-2">
            <VBtn
              size="small"
              icon="tabler-minus"
              variant="tonal"
              :disabled="quantity <= 1"
              @click="quantity--"
            />
            <span class="text-h6 font-weight-bold px-2">{{ quantity }}</span>
            <VBtn
              size="small"
              icon="tabler-plus"
              variant="tonal"
              @click="quantity++"
            />
          </div>
        </div>
      </VCardText>

      <VCardActions class="pa-4 bg-grey-lighten-5 d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-check"
          @click="handleConfirm"
        >
          Agregar al Pedido
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
