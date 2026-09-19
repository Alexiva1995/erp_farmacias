<template>
  <VRow class="mb-6 match-height">
    <!-- Card 1: Utilidad Gravable Estimada -->
    <VCol cols="12" md="4">
      <VCard :loading="loading" class="h-100 border shadow-sm rounded-lg">
        <VCardText class="d-flex flex-column justify-space-between h-100 pa-4 pa-sm-5">
          <div>
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                Renta Bruta Gravable
              </span>
              <VAvatar color="primary" variant="tonal" size="36">
                <VIcon icon="tabler-receipt-tax" size="20" />
              </VAvatar>
            </div>
            <div class="text-h5 font-weight-black text-high-emphasis mb-1">
              {{ formatCurrency(rentaBruta) }}
            </div>
            <div class="text-body-2 text-medium-emphasis font-weight-medium mb-2">
              Utilidad Gravable Estimada
            </div>
          </div>
          <div class="d-flex align-center pt-2 border-t">
            <VChip size="x-small" color="success" variant="tonal" class="font-weight-bold me-2">
              Base Imponible
            </VChip>
            <span class="text-caption text-medium-emphasis font-weight-medium">
              Ejercicio Fiscal {{ year }}
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Card 2: ISLR a Pagar Estimado -->
    <VCol cols="12" md="4">
      <VCard :loading="loading" class="h-100 border shadow-sm rounded-lg">
        <VCardText class="d-flex flex-column justify-space-between h-100 pa-4 pa-sm-5">
          <div>
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-caption font-weight-bold text-uppercase text-warning">
                ISLR Estimado (P. Jurídica)
              </span>
              <VAvatar color="warning" variant="tonal" size="36">
                <VIcon icon="tabler-calculator" size="20" />
              </VAvatar>
            </div>
            <div class="text-h5 font-weight-black text-warning mb-1">
              {{ formatCurrency(impuestoISLR) }}
            </div>
            <div class="text-body-2 text-medium-emphasis font-weight-medium mb-2">
              Impuesto a Declarar
            </div>
          </div>
          <div class="d-flex align-center justify-space-between pt-2 border-t">
            <div class="d-flex align-center">
              <VChip size="x-small" color="warning" variant="tonal" class="font-weight-bold me-2">
                Tarifa {{ tramoISLR.tasa }}%
              </VChip>
              <span class="text-caption text-high-emphasis font-weight-semibold">
                {{ tramoISLR.tramo }}
              </span>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Card 3: Estado Última Declaración -->
    <VCol cols="12" md="4">
      <VCard :loading="loadingDeclaration" class="h-100 border shadow-sm rounded-lg">
        <VCardText class="d-flex flex-column justify-space-between h-100 pa-4 pa-sm-5">
          <template v-if="latestDeclaration">
            <div>
              <div class="d-flex align-center justify-space-between mb-3">
                <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                  Declaración SENIAT
                </span>
                <VAvatar
                  :color="latestDeclaration.status === 'paid' ? 'success' : 'warning'"
                  variant="tonal"
                  size="36"
                >
                  <VIcon
                    :icon="latestDeclaration.status === 'paid' ? 'tabler-circle-check' : 'tabler-clock'"
                    size="20"
                  />
                </VAvatar>
              </div>
              <div class="text-h5 font-weight-black text-high-emphasis mb-1">
                {{ formatCurrency(latestDeclaration.amount) }}
              </div>
              <div class="d-flex align-center mb-2">
                <VChip
                  size="small"
                  :color="latestDeclaration.status === 'paid' ? 'success' : 'warning'"
                  variant="flat"
                  class="font-weight-bold text-uppercase me-2"
                >
                  {{ latestDeclaration.status_text }}
                </VChip>
                <span class="text-caption text-medium-emphasis">Año {{ latestDeclaration.year }}</span>
              </div>
            </div>
            <div class="d-flex align-center justify-space-between pt-2 border-t text-caption">
              <span class="text-medium-emphasis">Fecha de presentación:</span>
              <span class="font-weight-bold text-high-emphasis">
                {{ formatDate(latestDeclaration.declaration_date) }}
              </span>
            </div>
          </template>

          <template v-else>
            <div class="d-flex flex-column align-center justify-center text-center my-auto py-2">
              <VAvatar color="warning" variant="tonal" size="44" class="mb-2">
                <VIcon icon="tabler-file-alert" size="24" />
              </VAvatar>
              <span class="text-caption font-weight-bold text-uppercase text-warning mb-1">
                Sin Declaración Registrada
              </span>
              <p class="text-caption text-medium-emphasis mb-3">
                No se ha registrado declaración de ISLR para el ejercicio fiscal {{ year }}.
              </p>
              <VBtn
                color="primary"
                variant="flat"
                size="small"
                prepend-icon="tabler-plus"
                class="font-weight-bold"
                @click="$emit('open-create')"
              >
                Registrar Declaración
              </VBtn>
            </div>
          </template>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  loadingDeclaration: { type: Boolean, default: false },
  rentaBruta: { type: Number, default: 0 },
  impuestoISLR: { type: Number, default: 0 },
  tramoISLR: { type: Object, required: true },
  latestDeclaration: { type: Object, default: null },
  year: { type: Number, required: true },
  formatCurrency: { type: Function, required: true },
  formatDate: { type: Function, required: true },
});

defineEmits(["open-create"]);
</script>
