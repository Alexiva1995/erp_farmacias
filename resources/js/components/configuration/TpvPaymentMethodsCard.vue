<script setup>
import { confirmDialog } from '@/plugins/sweetalert'

const props = defineProps({
  paymentMethods: {
    type: Object,
    required: true
  },
  canEdit: {
    type: Boolean,
    default: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:paymentMethods'])

const handleCurrencyToggle = async (currency, currentValue) => {
  if (!props.canEdit || props.isSaving) return

  // Si se está desactivando una moneda activa
  if (currentValue) {
    const isConfirmed = await confirmDialog({
      title: `¿Desactivar cobros en ${currency}?`,
      text: `Los cajeros no podrán seleccionar métodos de pago en ${currency} durante la venta en TPV.`,
      icon: 'warning',
      confirmButtonText: 'Sí, desactivar',
      cancelButtonText: 'Cancelar'
    })

    if (!isConfirmed) return
  }

  props.paymentMethods[currency].enabled = !currentValue
  emit('update:paymentMethods', props.paymentMethods)
}

const handleMethodToggle = (currency, methodIndex) => {
  if (!props.canEdit || props.isSaving) return
  const method = props.paymentMethods[currency].methods[methodIndex]
  method.enabled = !method.enabled
  emit('update:paymentMethods', props.paymentMethods)
}

const toggleDescription = (method) => {
  method.showDescription = !method.showDescription
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="px-6 py-5">
      <!-- Encabezado Estandarizado -->
      <div class="d-flex align-center gap-3 mb-2">
        <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
          <VIcon icon="tabler-currency-dollar" size="22" />
        </VAvatar>
        <div>
          <VCardTitle class="text-h6 font-weight-bold mb-0">
            Monedas y Métodos de Pago Habilitados
          </VCardTitle>
          <VCardSubtitle class="text-body-2 text-medium-emphasis">
            Define los canales de recepción activos en el Punto de Venta según la divisa seleccionada en caja.
          </VCardSubtitle>
        </div>
      </div>

      <VDivider class="my-4" />

      <VRow>
        <VCol
          v-for="(currencyData, currency) in paymentMethods"
          :key="currency"
          cols="12"
          md="4"
        >
          <VCard
            variant="outlined"
            class="rounded-lg h-100 d-flex flex-column"
            :class="currencyData.enabled ? 'border-primary' : 'opacity-75'"
          >
            <!-- Cabecera de la moneda -->
            <VCardItem class="bg-var-theme-background py-3 px-4">
              <div class="d-flex align-center justify-space-between w-100">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="currencyData.enabled ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="32"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-coin" size="18" />
                  </VAvatar>
                  <div>
                    <span class="text-subtitle-2 font-weight-bold d-block">Cobros en {{ currency }}</span>
                    <VChip
                      :color="currencyData.enabled ? 'primary' : 'secondary'"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ currencyData.enabled ? 'Activa' : 'Inactiva' }}
                    </VChip>
                  </div>
                </div>

                <VSwitch
                  :model-value="currencyData.enabled"
                  density="comfortable"
                  hide-details="auto"
                  color="primary"
                  :disabled="!canEdit || isSaving"
                  @update:model-value="() => handleCurrencyToggle(currency, currencyData.enabled)"
                />
              </div>
            </VCardItem>

            <VDivider />

            <!-- Métodos asociados -->
            <VCardText class="py-3 px-4 flex-grow-1">
              <div v-if="!currencyData.enabled" class="text-caption text-medium-emphasis py-6 text-center">
                <VIcon icon="tabler-ban" size="24" class="d-block mx-auto mb-1 text-disabled" />
                Moneda desactivada para cobros en TPV
              </div>

              <div v-else-if="!currencyData.methods || currencyData.methods.length === 0" class="text-caption text-medium-emphasis py-4 text-center">
                Sin métodos de pago configurados
              </div>

              <div v-else class="d-flex flex-column gap-3">
                <div
                  v-for="(method, index) in currencyData.methods"
                  :key="index"
                  class="pa-3 rounded-lg border bg-surface"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div class="d-flex align-center gap-2">
                      <span class="font-weight-medium text-body-2">{{ method.label }}</span>
                      <VBtn
                        icon="tabler-pencil"
                        variant="text"
                        size="x-small"
                        color="primary"
                        title="Instrucciones de pago para cajeros"
                        :disabled="!canEdit || isSaving"
                        @click="toggleDescription(method)"
                      />
                    </div>
                    <VSwitch
                      :model-value="method.enabled"
                      density="comfortable"
                      hide-details="auto"
                      color="primary"
                      :disabled="!canEdit || isSaving"
                      @update:model-value="() => handleMethodToggle(currency, index)"
                    />
                  </div>

                  <!-- Campo de instrucciones -->
                  <VExpandTransition>
                    <div v-if="method.showDescription" class="mt-2 pt-2 border-t">
                      <label class="text-caption text-medium-emphasis d-block mb-1">
                        Instrucciones en pantalla para {{ method.label }}:
                      </label>
                      <VTextarea
                        v-model="method.description"
                        :placeholder="`Datos bancarios, pasos o requerimientos para ${method.label}...`"
                        variant="outlined"
                        density="comfortable"
                        rows="2"
                        auto-grow
                        hide-details="auto"
                        :disabled="!canEdit || isSaving"
                      />
                    </div>
                  </VExpandTransition>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

