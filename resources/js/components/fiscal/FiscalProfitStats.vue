<template>
  <VRow class="mb-6 match-height">
    <!-- Card 1: Utilidad Gravable Estimada -->
    <VCol cols="12" md="4">
      <VCard :loading="loading" class="h-100">
        <VCardText>
          <div class="d-flex align-center mb-2">
            <VAvatar color="purple-lighten-5" size="40" class="mr-3">
              <VIcon icon="tabler-currency-dollar" color="purple" size="20" />
            </VAvatar>
            <span class="text-h5 font-weight-semibold">{{
              formatCurrency(rentaBruta)
            }}</span>
          </div>
          <div class="text-body-2 text-medium-emphasis mb-1">
            Utilidad Gravable Estimada
          </div>
          <div class="d-flex align-center text-caption">
            <span class="text-success font-weight-medium mr-1"
              >Renta Bruta</span
            >
            <span class="text-medium-emphasis">año {{ year }}</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Card 2: ISLR a Pagar Estimado -->
    <VCol cols="12" md="4">
      <VCard :loading="loading" class="h-100">
        <VCardText>
          <div class="d-flex align-center mb-2">
            <VAvatar color="orange-lighten-5" size="40" class="mr-3">
              <VIcon icon="tabler-file-invoice" color="orange" size="20" />
            </VAvatar>
            <span class="text-h5 font-weight-semibold">{{
              formatCurrency(impuestoISLR)
            }}</span>
          </div>
          <div class="text-body-2 text-medium-emphasis mb-1">
            ISLR a Pagar Estimado
          </div>
          <div class="d-flex align-center text-caption">
            <span class="text-warning font-weight-medium mr-1"
              >{{ tramoISLR.tasa }}%</span
            >
            <span class="text-medium-emphasis">{{ tramoISLR.tramo }}</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Card 3: Estado Última Declaración -->
    <VCol cols="12" md="4">
      <VCard :loading="loadingDeclaration" class="h-100">
        <VCardText class="d-flex flex-column justify-center" style="min-height: 160px">
          <template v-if="latestDeclaration">
            <div class="d-flex align-center mb-2">
              <VAvatar
                :color="
                  latestDeclaration.status === 'paid'
                    ? 'success-lighten-5'
                    : 'warning-lighten-5'
                "
                size="40"
                class="mr-3"
              >
                <VIcon
                  :icon="
                    latestDeclaration.status === 'paid'
                      ? 'tabler-circle-check'
                      : 'tabler-clock'
                  "
                  :color="
                    latestDeclaration.status === 'paid'
                      ? 'success'
                      : 'warning'
                  "
                  size="20"
                />
              </VAvatar>
              <span class="text-h5 font-weight-semibold">
                {{ latestDeclaration.status_text }}
              </span>
            </div>
            <div class="text-body-2 text-medium-emphasis mb-1">
              Estado Última Declaración ({{ latestDeclaration.year }})
            </div>
            <div class="d-flex align-center text-caption">
              <span class="text-medium-emphasis mr-1">Declarada el</span>
              <span class="text-disabled">{{
                formatDate(latestDeclaration.declaration_date)
              }}</span>
            </div>
            <div class="d-flex align-center text-caption mt-1">
              <span class="text-medium-emphasis mr-1">Monto:</span>
              <span class="font-weight-bold">{{
                formatCurrency(latestDeclaration.amount)
              }}</span>
            </div>
          </template>

          <template v-else>
            <div class="d-flex flex-column align-center justify-center flex-grow-1">
              <VIcon
                icon="tabler-file-x"
                size="40"
                color="warning"
                class="mb-2"
              />
              <div class="text-body-2 text-medium-emphasis mb-3 text-center">
                No hay declaración registrada para {{ year }}
              </div>
              <VBtn
                color="primary"
                size="small"
                prepend-icon="tabler-plus"
                @click="$emit('open-create')"
              >
                Crear Declaración
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
