<script setup>
import { computed, ref, watch } from 'vue'
import { formatCurrency } from '@/utils/currencyFormatter'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  dish: {
    type: Object,
    default: null,
  },
  initialQuantity: {
    type: Number,
    default: 1,
  },
  initialModifiers: {
    type: Array,
    default: () => [],
  },
  initialNotes: {
    type: String,
    default: '',
  },
  orderDetailId: {
    type: [Number, String],
    default: null,
  },
  selectedDisplayCurrency: {
    type: String,
    default: 'USD',
  },
  exchangeRates: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const selectedModifiers = ref([])
const notes = ref('')
const quantity = ref(1)

const isEditing = computed(() => Boolean(props.orderDetailId))

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      selectedModifiers.value = Array.isArray(props.initialModifiers)
        ? props.initialModifiers.map((m) => ({
            modifier_option_id: m.modifier_option_id || m.option_id || null,
            name: m.name,
            modifier_type: m.modifier_type,
            extra_price_usd: Number(m.extra_price_usd ?? 0),
            product_id: m.product_id || null,
            base_product_id: m.base_product_id || null,
            portion: Number(m.portion ?? 0),
          }))
        : []
      notes.value = props.initialNotes || ''
      quantity.value = Math.max(1, Number(props.initialQuantity) || 1)
    }
  }
)

const groupSelectionCount = (group) => {
  const groupOptionIds = (group.options || []).map((o) => o.id)
  return selectedModifiers.value.filter((m) =>
    groupOptionIds.includes(m.modifier_option_id)
  ).length
}

const isSelectionValid = computed(() => {
  const groups = props.dish?.modifier_groups || []
  return groups.every((group) => {
    const min = Number(group.min_selections) || 0
    const max = Number(group.max_selections) || 0
    const count = groupSelectionCount(group)
    if (min > 0 && count < min) return false
    if (max > 0 && count > max) return false
    return true
  })
})

const isOptionSelected = (optionId) => {
  return selectedModifiers.value.some((m) => m.modifier_option_id === optionId)
}

const toggleOption = (group, option) => {
  const isSelected = isOptionSelected(option.id)

  if (isSelected) {
    selectedModifiers.value = selectedModifiers.value.filter(
      (m) => m.modifier_option_id !== option.id
    )
    return
  }

  const max = Number(group.max_selections) || 0
  if (max === 1) {
    const groupOptionIds = (group.options || []).map((o) => o.id)
    selectedModifiers.value = selectedModifiers.value.filter(
      (m) => !groupOptionIds.includes(m.modifier_option_id)
    )
  } else if (max > 1 && groupSelectionCount(group) >= max) {
    return
  }

  selectedModifiers.value.push({
    modifier_option_id: option.id,
    name: option.name,
    modifier_type: option.modifier_type,
    extra_price_usd: Number(option.extra_price_usd ?? 0),
    product_id: option.product_id || null,
    base_product_id: group.base_product_id || null,
    portion: Number(option.portion ?? 0),
  })
}

const convertUsdToDisplay = (usdAmount) => {
  const usd = Number(usdAmount) || 0
  const curr = String(props.selectedDisplayCurrency || 'USD').toUpperCase()
  if (curr === 'BS') {
    const rate = Number(props.exchangeRates?.USD_BS || props.exchangeRates?.bs || 1)
    return usd * (rate > 0 ? rate : 1)
  }
  if (curr === 'COP') {
    const rate = Number(props.exchangeRates?.USD_COP || props.exchangeRates?.cop || 4000)
    return Math.ceil((usd * (rate > 0 ? rate : 4000)) / 100) * 100
  }
  return usd
}

const extrasUsd = computed(() => {
  return selectedModifiers.value.reduce((sum, m) => sum + (Number(m.extra_price_usd) || 0), 0)
})

const unitTotalUsd = computed(() => {
  return (Number(props.dish?.designated_price || 0)) + extrasUsd.value
})

const unitTotalDisplay = computed(() => {
  const curr = String(props.selectedDisplayCurrency || 'USD').toUpperCase()
  if (curr === 'COP' && Number(props.dish?.designated_price_cop) > 0) {
    return Number(props.dish.designated_price_cop) + convertUsdToDisplay(extrasUsd.value)
  }
  return convertUsdToDisplay(unitTotalUsd.value)
})

const totalOrderLineDisplay = computed(() => unitTotalDisplay.value * quantity.value)

const handleConfirm = () => {
  emit('confirm', {
    dish: props.dish,
    quantity: quantity.value,
    modifiers: selectedModifiers.value,
    notes: notes.value,
    orderDetailId: props.orderDetailId || null,
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="dish" class="rounded-lg">
      <VCardTitle class="d-flex align-center justify-space-between pa-4 bg-primary text-white">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-tools-kitchen-2" />
          <span class="text-h6 font-weight-bold text-white">{{ dish.name }}</span>
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
          <span class="text-subtitle-2 text-medium-emphasis">Precio Unitario (con selección):</span>
          <span class="text-h6 font-weight-bold text-primary">
            {{ formatCurrency(unitTotalDisplay, selectedDisplayCurrency) }}
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
            <div class="d-flex align-center gap-1">
              <VChip
                v-if="group.max_selections > 0"
                size="x-small"
                color="info"
                variant="tonal"
              >
                {{ group.max_selections === 1 ? 'Elige 1' : `Máx. ${group.max_selections}` }}
              </VChip>
              <VChip
                size="x-small"
                :color="group.min_selections > 0 ? 'warning' : 'default'"
                variant="tonal"
              >
                {{ group.min_selections > 0 ? `Requerido (mín. ${group.min_selections})` : 'Opcional' }}
              </VChip>
            </div>
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
                    +{{ formatCurrency(convertUsdToDisplay(opt.extra_price_usd), selectedDisplayCurrency) }}
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

      <VCardActions class="pa-4 bg-grey-lighten-5 d-flex justify-space-between align-center gap-2">
        <span class="text-subtitle-2 font-weight-bold text-primary">
          Total: {{ formatCurrency(totalOrderLineDisplay, selectedDisplayCurrency) }}
        </span>
        <div class="d-flex gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            @click="emit('update:modelValue', false)"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="tabler-check"
            :disabled="!isSelectionValid"
            @click="handleConfirm"
          >
            {{ isEditing ? 'Guardar Cambios' : 'Agregar al Pedido' }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
