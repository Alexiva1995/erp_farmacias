<script setup>
defineProps({
  enableCashea: {
    type: Boolean,
    default: false
  },
  casheaMode: {
    type: String,
    default: 'manual'
  },
  casheaEnvironment: {
    type: String,
    default: 'sandbox'
  },
  casheaClientId: {
    type: String,
    default: ''
  },
  casheaPublicKey: {
    type: String,
    default: ''
  },
  casheaPrivateKey: {
    type: String,
    default: ''
  },
  casheaInstallmentsCount: {
    type: [Number, String],
    default: 3
  },
  casheaInstallmentDays: {
    type: [Number, String],
    default: 14
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

defineEmits([
  'update:enableCashea',
  'update:casheaMode',
  'update:casheaEnvironment',
  'update:casheaClientId',
  'update:casheaPublicKey',
  'update:casheaPrivateKey',
  'update:casheaInstallmentsCount',
  'update:casheaInstallmentDays'
])
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm" variant="flat">
    <VCardItem class="px-6 py-4">
      <div class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="warning" variant="tonal" size="48" class="rounded-lg">
            <VIcon icon="tabler-building-bank" size="28" />
          </VAvatar>
          <div>
            <div class="d-flex align-center gap-2">
              <h2 class="text-h6 font-weight-bold mb-0">Integración con Cashea (BNPL)</h2>
              <VChip
                :color="enableCashea ? 'success' : 'secondary'"
                size="small"
                variant="tonal"
              >
                {{ enableCashea ? 'Habilitado' : 'Deshabilitado' }}
              </VChip>
            </div>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Permite a los clientes pagar la cuota inicial en caja y financiar el restante en cuotas quincenales.
            </p>
          </div>
        </div>

        <VSwitch
          :model-value="enableCashea"
          color="warning"
          density="compact"
          inset
          :disabled="!canEdit || isSaving"
          @update:model-value="$emit('update:enableCashea', $event)"
        />
      </div>
    </VCardItem>

    <VExpandTransition>
      <div v-if="enableCashea">
        <VDivider />
        <VCardText class="pa-6">
          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                :model-value="casheaMode"
                label="Modo de Operación"
                :items="[
                  { title: 'Registro Asistido en Caja (Manual con Referencia)', value: 'manual' },
                  { title: 'Conexión Directa por API Oficial', value: 'api' }
                ]"
                density="compact"
                variant="outlined"
                :disabled="!canEdit || isSaving"
                @update:model-value="$emit('update:casheaMode', $event)"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                :model-value="casheaEnvironment"
                label="Entorno de Conexión"
                :items="[
                  { title: 'Sandbox (Pruebas)', value: 'sandbox' },
                  { title: 'Producción (En vivo)', value: 'production' }
                ]"
                density="compact"
                variant="outlined"
                :disabled="!canEdit || isSaving"
                @update:model-value="$emit('update:casheaEnvironment', $event)"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                :model-value="casheaClientId"
                label="ID de Tienda / Comercio (Store ID)"
                placeholder="Ej. STORE-001"
                density="compact"
                variant="outlined"
                :disabled="!canEdit || isSaving"
                @update:model-value="$emit('update:casheaClientId', $event)"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                :model-value="casheaPublicKey"
                label="Public API Key"
                placeholder="pk_live_..."
                density="compact"
                variant="outlined"
                :disabled="!canEdit || isSaving"
                @update:model-value="$emit('update:casheaPublicKey', $event)"
              />
            </VCol>

            <VCol cols="12" md="4">
              <VTextField
                :model-value="casheaPrivateKey"
                label="Private API Key"
                type="password"
                placeholder="sk_live_..."
                density="compact"
                variant="outlined"
                :disabled="!canEdit || isSaving"
                @update:model-value="$emit('update:casheaPrivateKey', $event)"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                :model-value="casheaInstallmentsCount"
                label="Número de Cuotas por Defecto"
                type="number"
                min="1"
                max="12"
                density="compact"
                variant="outlined"
                suffix="cuotas"
                :disabled="!canEdit || isSaving"
                @update:model-value="$emit('update:casheaInstallmentsCount', Number($event))"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                :model-value="casheaInstallmentDays"
                label="Frecuencia entre Cuotas"
                type="number"
                min="1"
                max="60"
                density="compact"
                variant="outlined"
                suffix="días"
                :disabled="!canEdit || isSaving"
                @update:model-value="$emit('update:casheaInstallmentDays', Number($event))"
              />
            </VCol>
          </VRow>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>
