<template>
  <VRow class="mb-6 match-height">
    <!-- Tarjeta de Felicitaciones / Líder de Ventas -->
    <VCol cols="12" md="4">
      <VCard class="h-100 bg-light-primary">
        <VCardText class="d-flex flex-column justify-space-between h-100 pa-5">
          <div class="d-flex align-center gap-3 mb-2">
            <VAvatar size="50" class="border-2 border-white shadow-lg">
              <VImg :src="leader?.photo || defaultAvatar" />
            </VAvatar>
            <div>
              <h6 class="text-h6 text-primary font-weight-semibold mb-0">
                ¡Felicitaciones {{ leader?.name || 'Admin' }}! 🎉
              </h6>
              <div class="text-caption text-medium-emphasis">
                Líder de Ventas
              </div>
            </div>
          </div>
          <div>
            <div class="text-h5 text-primary font-weight-bold">
              {{ formatCurrencyUSD(leader?.sales || 0) }}
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Tarjeta de Estadísticas Mensuales -->
    <VCol cols="12" md="8">
      <VCard class="h-100">
        <VCardTitle class="pt-4 px-4 d-flex justify-space-between align-center">
          <span class="text-subtitle-1 font-weight-bold">Estadísticas Mensuales</span>
          <span class="text-caption text-medium-emphasis">Actualizado hoy</span>
        </VCardTitle>
        <VCardText class="pa-4 d-flex align-center justify-space-around flex-wrap">
          <VSkeletonLoader
            v-if="loading"
            type="text"
            class="w-100 py-2"
            height="44"
          />
          <template v-else>
            <!-- Ventas -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="primary-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-chart-bar" color="primary" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.sales }}</div>
                <div class="text-caption text-medium-emphasis">Ventas</div>
              </div>
            </div>

            <!-- Clientes -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="info-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-users" color="info" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.clients }}</div>
                <div class="text-caption text-medium-emphasis">Clientes Nuevos</div>
              </div>
            </div>

            <!-- Productos -->
            <div class="d-flex align-center mb-4 mr-4">
              <VAvatar color="error-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-box" color="error" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.products }}</div>
                <div class="text-caption text-medium-emphasis">Productos (Unidades)</div>
              </div>
            </div>

            <!-- Ingresos / Ganancia -->
            <div class="d-flex align-center mb-4">
              <VAvatar color="success-lighten-5" size="44" class="mr-3" rounded="lg">
                <VIcon icon="tabler-currency-dollar" color="success" size="24" />
              </VAvatar>
              <div>
                <div class="text-h6 font-weight-bold">{{ stats.revenue }}</div>
                <div class="text-caption text-medium-emphasis">Ganancia</div>
              </div>
            </div>
          </template>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<script setup>
const defaultAvatar = '/images/avatars/seller-avatar.png'

defineProps({
  leader: {
    type: Object,
    default: null,
  },
  stats: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const formatCurrencyUSD = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount)
</script>

<style scoped>
.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}
</style>
