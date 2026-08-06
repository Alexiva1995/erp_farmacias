<template>
  <VRow class="mb-6 match-height">
    <!-- Cierres Diarios Recientes -->
    <VCol cols="12" md="4">
      <VCard class="h-100">
        <VCardTitle class="pt-4 px-4 d-flex justify-space-between align-center">
          <span class="text-subtitle-1 font-weight-bold">Cierres Diarios Recientes</span>
          <VIcon icon="tabler-calendar-stats" class="text-medium-emphasis" size="20" />
        </VCardTitle>
        <VCardText class="pa-0">
          <VSkeletonLoader v-if="loading" type="list-item-avatar-two-line@3" class="pa-2" />
          <VList v-else density="compact">
            <VListItem v-for="(closure, index) in recentClosures" :key="closure.id || index" class="px-4 py-2">
              <template #prepend>
                <VAvatar color="success-lighten-5" size="36" class="mr-3" rounded="lg">
                  <VIcon icon="tabler-calendar-event" color="success" size="20" />
                </VAvatar>
              </template>
              <VListItemTitle class="text-body-2 font-weight-medium">
                {{ formatDate(closure.created_at) }}
              </VListItemTitle>
              <VListItemSubtitle class="text-xs text-medium-emphasis">
                Cierre #{{ closure.id }} • Consolidado
              </VListItemSubtitle>
              <template #append>
                <span class="text-body-2 font-weight-bold text-success">
                  {{ formatCurrencyUSD(closure.total_sales) }}
                </span>
              </template>
            </VListItem>
            <VListItem v-if="recentClosures.length === 0" class="px-4 py-6 text-center text-medium-emphasis text-caption">
              Sin cierres registrados recientemente
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Productos Populares -->
    <VCol cols="12" md="4">
      <VCard class="h-100">
        <VCardTitle class="pt-4 px-4 text-subtitle-1 font-weight-bold">Productos Populares</VCardTitle>
        <VCardText class="pa-0">
          <VSkeletonLoader v-if="loading" type="list-item-avatar-two-line@3" class="pa-2" />
          <VList v-else density="compact">
            <VListItem v-for="(prod, index) in popularProducts" :key="index" class="px-4 py-2">
              <template #prepend>
                <VAvatar color="primary-lighten-5" size="36" class="mr-3" rounded="lg">
                  <VIcon icon="tabler-package" color="primary" size="20" />
                </VAvatar>
              </template>
              <VListItemTitle class="text-body-2 font-weight-medium">{{ prod.name }}</VListItemTitle>
              <VListItemSubtitle class="text-caption text-medium-emphasis">
                {{ prod.laboratory }} · {{ prod.quantity }} uds
              </VListItemSubtitle>
              <template #append>
                <span class="text-body-2 font-weight-bold">{{ formatCurrencyUSD(prod.price) }}</span>
              </template>
            </VListItem>
            <VListItem v-if="popularProducts.length === 0" class="px-4 py-6 text-center text-medium-emphasis text-caption">
              Sin productos vendidos este mes
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Transacciones -->
    <VCol cols="12" md="4">
      <VCard class="h-100">
        <VCardTitle class="pt-4 px-4 text-subtitle-1 font-weight-bold">Transacciones</VCardTitle>
        <VCardText class="pa-0">
          <VSkeletonLoader v-if="loading" type="list-item-avatar-two-line@3" class="pa-2" />
          <VList v-else density="compact">
            <VListItem v-for="(tx, index) in transactions" :key="index" class="px-4 py-2">
              <template #prepend>
                <VAvatar :color="tx.color + '-lighten-5'" size="36" class="mr-3" rounded="lg">
                  <VIcon :icon="tx.icon" :color="tx.color" size="20" />
                </VAvatar>
              </template>
              <VListItemTitle class="text-body-2 font-weight-medium">{{ tx.title }}</VListItemTitle>
              <VListItemSubtitle class="text-caption text-medium-emphasis">{{ tx.subtitle }}</VListItemSubtitle>
              <template #append>
                <span :class="`text-body-2 font-weight-bold ${tx.amount > 0 ? 'text-success' : 'text-error'}`">
                  {{ tx.amount > 0 ? '+' : '' }}{{ formatCurrencyUSD(tx.amount) }}
                </span>
              </template>
            </VListItem>
            <VListItem v-if="transactions.length === 0" class="px-4 py-6 text-center text-medium-emphasis text-caption">
              Sin transacciones este mes
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<script setup>
defineProps({
  recentClosures: {
    type: Array,
    default: () => [],
  },
  popularProducts: {
    type: Array,
    default: () => [],
  },
  transactions: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const formatDate = (dateStr) => {
  if (!dateStr) return ""
  const date = new Date(dateStr)
  const months = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"]
  return `${date.getDate()} de ${months[date.getMonth()]} ${date.getFullYear()}`
}

const formatCurrencyUSD = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount)
</script>
