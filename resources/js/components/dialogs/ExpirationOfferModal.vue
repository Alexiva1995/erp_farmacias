<script setup>
import { toast } from "@/plugins/sweetalert";
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  loading: { type: Boolean, default: false },
  isEditing: { type: Boolean, default: false },
  offerToEdit: { type: Object, default: null },
  availableProductLots: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'save', 'modal-closed'])

const defaultOfferData = {
  months_to_expiration: '',
  discount_percentage: '',
  is_active: true,
  product_lot_ids: [],
}

const offerData = ref({ ...defaultOfferData })
const selectedProductLots = ref([])
const formErrors = ref({})

const dialogTitle = computed(() => {
  return props.isEditing ? 'Editar Oferta' : 'Crear Nueva Oferta'
})

const onCancel = () => {
  emit('update:modelValue', false)
  emit('modal-closed')
  resetForm()
}

const onSave = () => {
  // Validaciones básicas
  if (!offerData.value.months_to_expiration) {
    formErrors.value.months_to_expiration = ['Este campo es obligatorio']
    return
  }

  if (!offerData.value.discount_percentage) {
    formErrors.value.discount_percentage = ['Este campo es obligatorio']
    return
  }

  if (selectedProductLots.value.length === 0) {
    toast.error('Debe seleccionar al menos un lote de producto')
    return
  }

  const productLotIds = selectedProductLots.value.map(lot => {
    // Si el lote es un objeto, tomar el id, si es solo el id, usarlo directamente
    return typeof lot === 'object' ? lot.id : lot;
  });

  // Preparar datos para enviar

  const saveData = {
    ...offerData.value,
    product_lot_ids: productLotIds
  }
  emit('save', saveData)
}

const resetForm = () => {
  offerData.value = { ...defaultOfferData }
  selectedProductLots.value = []
  formErrors.value = {}
}

// Función auxiliar para formatear fecha
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('es-ES');
}

// Función para crear el display_name de los lotes
const getLotDisplayName = (lot) => {
  if (!lot) return '';
  
  if (typeof lot === 'string') return lot;
  
  return `${lot.product?.name || 'Producto'} - Lote: ${lot.lot_number || 'N/A'} - Exp: ${formatDate(lot.expiration_date)}`;
}

// Watchers
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.offerToEdit) {
        // Cargar datos de edición
        offerData.value = {
          id: props.offerToEdit.id,
          months_to_expiration: props.offerToEdit.months_to_expiration,
          discount_percentage: props.offerToEdit.discount_percentage,
          is_active: Boolean(props.offerToEdit.is_active),
        }
        
        // Cargar lotes asociados
        if (props.offerToEdit.product_lots) {
          selectedProductLots.value = props.offerToEdit.product_lots
        }
      } else {
        resetForm()
      }
    }
  }
)

// Limpiar errores cuando se cambian los valores
watch(
  () => offerData.value.months_to_expiration,
  () => {
    if (formErrors.value.months_to_expiration) {
      delete formErrors.value.months_to_expiration
    }
  }
)

watch(
  () => offerData.value.discount_percentage,
  () => {
    if (formErrors.value.discount_percentage) {
      delete formErrors.value.discount_percentage
    }
  }
)
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="onCancel"
  >
    <VCard :loading="props.loading">
      <VCardTitle class="d-flex align-center pa-4">
        <span class="text-h5 font-weight-bold">{{ dialogTitle }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="onCancel">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <VForm>
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="offerData.months_to_expiration"
                label="Meses para Expirar *"
                type="number"
                min="1"
                max="36"
                :error-messages="formErrors.months_to_expiration"
                placeholder="Ej: 3"
                :disabled="props.loading"
              />
              <div class="text-caption text-disabled mt-1">
                Número de meses antes de la expiración para aplicar la oferta
              </div>
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="offerData.discount_percentage"
                label="Porcentaje de Descuento *"
                type="number"
                min="0.01"
                max="100"
                step="0.01"
                suffix="%"
                :error-messages="formErrors.discount_percentage"
                placeholder="Ej: 15.50"
                :disabled="props.loading"
              />
              <div class="text-caption text-disabled mt-1">
                Porcentaje de descuento a aplicar (0.01% - 100%)
              </div>
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="offerData.is_active"
                label="Oferta Activa"
                :true-value="true"
                :false-value="false"
                color="primary"
                :disabled="props.loading"
              />
            </VCol>

            <VCol cols="12">
              <VAutocomplete
                v-model="selectedProductLots"
                :items="props.availableProductLots"
                :item-title="getLotDisplayName"
                item-value="id"
                label="Seleccionar Lotes de Productos *"
                multiple
                chips
                closable-chips
                :loading="props.loading"
                placeholder="Buscar lotes por expirar..."
                :disabled="props.loading"
                return-object
              >
                <template #selection="{ item }">
                  <VChip class="me-2 mb-2" closable @click:close="selectedProductLots = selectedProductLots.filter(lot => lot.id !== item.raw.id)">
                    {{ getLotDisplayName(item.raw) }}
                    {{ item.raw.product?.name }} - 
                    Lote: {{ item.raw.lot_number }} - 
                    Exp: {{ formatDate(item.raw.expiration_date) }}
                  </VChip>
                </template>
                
                <template #item="{ item, props: itemProps }">
                  <VListItem v-bind="itemProps">
                    <VListItemTitle>
                      {{ item.raw.product?.name }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      Lote: {{ item.raw.lot_number }} | 
                      Expira: {{ formatDate(item.raw.expiration_date) }} | 
                      Stock: {{ item.raw.quantity }} |
                      Proveedor: {{ item.raw.supplier?.name || 'N/A' }}
                    </VListItemSubtitle>
                  </VListItem>
                </template>

                <template #prepend-item>
                  <VListItem title="Lotes Disponibles" class="font-weight-bold bg-grey-lighten-3" />
                  <VDivider />
                </template>
              </VAutocomplete>
              
              <VAlert
                v-if="selectedProductLots.length > 0"
                type="info"
                variant="tonal"
                class="mt-2"
              >
                <strong>{{ selectedProductLots.length }}</strong> lote(s) seleccionado(s)
              </VAlert>

              <VAlert
                v-else
                type="warning"
                variant="tonal"
                class="mt-2"
              >
                Debe seleccionar al menos un lote de producto
              </VAlert>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          :disabled="props.loading"
          @click="onCancel"
        >
          Cancelar
        </VBtn>
        
        <VSpacer />
        
        <VBtn
          color="primary"
          variant="flat"
          :loading="props.loading"
          :disabled="!offerData.months_to_expiration || !offerData.discount_percentage || selectedProductLots.length === 0"
          @click="onSave"
        >
          {{ props.isEditing ? 'Actualizar' : 'Crear' }} Oferta
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

