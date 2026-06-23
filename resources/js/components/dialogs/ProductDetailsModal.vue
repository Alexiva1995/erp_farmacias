<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true
  },
  product: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'add-to-cart'])

const selectedVariant = ref(null)

// Sincronizar variante seleccionada por defecto al abrir el modal
watch(() => props.product, (newVal) => {
  if (newVal && newVal.variants && newVal.variants.length > 0) {
    selectedVariant.value = newVal.variants[0]
  } else {
    selectedVariant.value = null;
  }
}, { immediate: true })

const close = () => {
  emit('update:modelValue', false)
}

const handleAddToCart = () => {
  if (props.product) {
    emit('add-to-cart', props.product, selectedVariant.value)
    close()
  }
}
</script>

<template>
  <VDialog :model-value="props.modelValue" max-width="600" @update:model-value="emit('update:modelValue', $event)">
    <VCard class="rounded-xl overflow-hidden" v-if="props.product">
      <VRow no-gutters>
        <VCol cols="12" sm="5">
          <VImg :src="props.product.photo_url || '/images/default-product.jpg'" height="100%" min-height="250" cover />
        </VCol>
        <VCol cols="12" sm="7" class="pa-5 d-flex flex-column justify-space-between">
          <div>
            <div class="d-flex justify-space-between align-start mb-2">
              <h3 class="text-h5 font-weight-black">{{ props.product.name }}</h3>
              <VBtn icon="tabler-x" variant="text" size="small" @click="close" />
            </div>
            
            <VChip v-if="props.product.category" size="small" color="primary" class="mb-3 font-weight-bold">
              {{ props.product.category.name }}
            </VChip>

            <p class="text-sm text-medium-emphasis mb-4">
              {{ props.product.description || 'Sin descripción detallada disponible.' }}
            </p>

            <!-- Selector de Variantes -->
            <div v-if="props.product.variants && props.product.variants.length > 0" class="mb-4">
              <span class="text-subtitle-2 font-weight-black d-block mb-2">
                Selecciona {{ props.product.variants[0].attribute_type === 'size' ? 'Talla' : 'Tono/Volumen' }}
              </span>
              <VSelect
                v-model="selectedVariant"
                :items="props.product.variants"
                item-title="attribute_value"
                return-object
                density="compact"
                variant="outlined"
                hide-details
                rounded="lg"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps" :subtitle="item.raw.price_modifier != 0 ? 'Modifica: $' + Number(item.raw.price_modifier).toLocaleString() : ''" />
                </template>
              </VSelect>
            </div>
          </div>

          <div>
            <div class="d-flex align-center justify-space-between mb-4 border-t pt-3">
              <span class="text-subtitle-1 font-weight-bold">Precio</span>
              <span class="text-h5 font-weight-black text-primary">
                ${{ (selectedVariant ? (Number(props.product.sale_price) + Number(selectedVariant.price_modifier)) : Number(props.product.sale_price)).toLocaleString('es-CO') }} COP
              </span>
            </div>

            <VBtn 
              block 
              color="primary" 
              size="large" 
              rounded="pill" 
              class="font-weight-bold shadow-md"
              :disabled="props.product.stock <= 0"
              @click="handleAddToCart"
            >
              Añadir al Carrito
            </VBtn>
          </div>
        </VCol>
      </VRow>
    </VCard>
  </VDialog>
</template>

<style scoped>
.shadow-md {
  box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.3) !important;
}
</style>
