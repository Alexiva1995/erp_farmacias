<script setup>
const props = defineProps({
  paymentMethods: {
    type: Object,
    required: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['change'])

const handleChange = () => {
  emit('change')
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <!-- Encabezado Principal Estandarizado -->
      <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
        <VIcon icon="tabler-currency-dollar" color="primary" size="28" />
        Monedas y Métodos de Pago Habilitados
      </VCardTitle>
      <p class="text-caption text-medium-emphasis mb-6">
        Define qué métodos de pago estarán activos en el Punto de Venta según la moneda de cobro.
      </p>

      <VDivider class="mb-6" />

      <VRow>
        <VCol
          v-for="(currencyData, currency) in paymentMethods"
          :key="currency"
          cols="12"
          md="4"
        >
          <VCard
            variant="outlined"
            class="rounded-lg transition-all"
            :class="{ 'border-primary border-opacity-60': currencyData.enabled }"
            :style="{ opacity: currencyData.enabled ? 1 : 0.6 }"
          >
            <VCardItem class="bg-var-theme-background py-3">
              <div class="d-flex align-center justify-space-between w-100">
                <VCardTitle class="text-subtitle-1 font-weight-black d-flex align-center">
                  <VIcon icon="tabler-coin" class="me-2 text-primary" size="20" />
                  Cobros en {{ currency }}
                </VCardTitle>
                <VSwitch
                  v-model="currencyData.enabled"
                  density="compact"
                  hide-details
                  color="primary"
                  :disabled="isSaving"
                  @update:model-value="handleChange"
                />
              </div>
            </VCardItem>
            <VDivider />
            <VCardText class="py-3">
              <div v-if="!currencyData.enabled" class="text-caption text-disabled py-6 text-center">
                Moneda Desactivada
              </div>
              <div v-else-if="!currencyData.methods || currencyData.methods.length === 0" class="text-caption text-disabled py-4 text-center">
                Sin métodos de pago configurados
              </div>
              <div v-else>
                <div
                  v-for="(method, index) in currencyData.methods"
                  :key="index"
                  class="py-2 border-bottom-dashed"
                >
                  <div class="d-flex align-center justify-space-between mb-1">
                    <div class="d-flex align-center">
                      <span class="font-weight-bold text-body-2 me-1">{{ method.label }}</span>
                      <VBtn
                        icon="tabler-pencil"
                        variant="text"
                        size="x-small"
                        color="primary"
                        title="Editar instrucciones de pago"
                        :disabled="!currencyData.enabled || !method.enabled || isSaving"
                        @click="method.showDescription = !method.showDescription"
                      />
                    </div>
                    <VSwitch
                      v-model="method.enabled"
                      density="compact"
                      hide-details
                      color="success"
                      :disabled="!currencyData.enabled || isSaving"
                      @update:model-value="handleChange"
                    />
                  </div>
                  <div v-if="method.showDescription" class="mt-2">
                    <div class="d-flex align-center gap-2">
                      <VTextarea
                        v-model="method.description"
                        :placeholder="`Instrucciones para pago con ${method.label}...`"
                        density="compact"
                        variant="outlined"
                        rows="2"
                        auto-grow
                        hide-details
                        :disabled="!currencyData.enabled || !method.enabled || isSaving"
                        class="text-caption flex-grow-1"
                        style="font-size: 11px;"
                      />
                      <VBtn
                        icon="tabler-device-floppy"
                        color="success"
                        size="small"
                        variant="flat"
                        class="rounded-lg flex-shrink-0"
                        title="Guardar instrucciones de pago"
                        :disabled="!currencyData.enabled || !method.enabled || isSaving"
                        @click="handleChange"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.border-bottom-dashed:not(:last-child) {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.12);
}
</style>
