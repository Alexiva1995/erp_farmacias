<script setup lang="ts">
import axios from '@/plugins/axios'
import { ref, computed, onMounted } from 'vue'

interface PendingPaymentGroup {
  supplier_id: number
  supplier_name: string
  payment_date: string
  currency: string
  total_amount: number
  total_usd: number
  remainingAmountUSD: number
  invoice_count: number
}

// ── Estado ────
const pendingPayments = ref<PendingPaymentGroup[]>([])
const loading = ref(true)

// ── Cargar Datos ────
const fetchPendingPayments = async () => {
  try {
    loading.value = true
    const response = await axios.get('/finances/pending-payments')
    if (response.data && response.data.data) {
      pendingPayments.value = response.data.data.pending_payments || []
    }
  } catch (error) {
    console.error('Error al cargar pagos pendientes de facturas:', error)
  } finally {
    loading.value = false
  }
}

// ── Ordenar cronológicamente ascendente (los más cercanos o vencidos primero) ────
const sortedPendingPayments = computed(() => {
  return [...pendingPayments.value].sort((a, b) => {
    if (!a.payment_date) return 1
    if (!b.payment_date) return -1
    return new Date(a.payment_date).getTime() - new Date(b.payment_date).getTime()
  })
})

// ── Formateadores ────
const formatUsd = (val: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(val)
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  }).format(date)
}

// Determinar si el pago está vencido
const isOverdue = (dateStr: string) => {
  if (!dateStr) return false
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const paymentDate = new Date(dateStr)
  paymentDate.setHours(0, 0, 0, 0)
  return paymentDate < today
}

onMounted(() => {
  fetchPendingPayments()
})
</script>

<template>
  <VCard
    title="Pagos Pendientes de Facturas"
    subtitle="Listado consolidado por fecha más cercana"
  >
    <template #append>
      <div class="mt-n4 me-n2">
        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          @click="fetchPendingPayments"
        >
          <VIcon icon="tabler-refresh" />
        </VBtn>
      </div>
    </template>

    <VCardText class="position-relative" style="min-height: 200px;">
      <!-- Loading State -->
      <div v-if="loading" class="d-flex justify-center align-center position-absolute w-100 h-100 top-0 left-0" style="z-index: 2; background: rgba(var(--v-theme-surface), 0.7);">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <!-- Empty State -->
      <div v-else-if="sortedPendingPayments.length === 0" class="d-flex flex-column align-center justify-center py-8 text-center">
        <VAvatar color="success" variant="tonal" size="48" class="mb-2">
          <VIcon icon="tabler-circle-check" size="24" />
        </VAvatar>
        <span class="text-high-emphasis font-weight-bold text-body-1 mb-1">¡Al día!</span>
        <span class="text-medium-emphasis text-body-2">No hay pagos de facturas pendientes</span>
      </div>

      <!-- List of Payments -->
      <div v-else>
        <VList class="card-list mb-4">
          <VListItem
            v-for="payment in sortedPendingPayments.slice(0, 10)"
            :key="payment.supplier_id + '_' + payment.payment_date"
            class="payment-item"
          >
            <template #prepend>
              <VAvatar
                size="40"
                :color="isOverdue(payment.payment_date) ? 'error' : 'warning'"
                variant="tonal"
                class="me-3"
              >
                <VIcon :icon="isOverdue(payment.payment_date) ? 'tabler-alert-triangle' : 'tabler-calendar-time'" size="22" />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-bold text-high-emphasis text-truncate" style="max-width: 180px;">
              {{ payment.supplier_name }}
            </VListItemTitle>
            
            <VListItemSubtitle class="d-flex align-center mt-1">
              <span class="text-body-2 text-medium-emphasis">
                {{ formatDate(payment.payment_date) }}
              </span>
              <VChip
                size="x-small"
                color="secondary"
                variant="tonal"
                class="ms-2 font-weight-medium"
              >
                {{ payment.invoice_count }} {{ payment.invoice_count === 1 ? 'fac' : 'facs' }}
              </VChip>
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex flex-column align-end">
                <span class="font-weight-black text-body-1 text-high-emphasis">
                  {{ formatUsd(payment.remainingAmountUSD) }}
                </span>
                <span v-if="isOverdue(payment.payment_date)" class="text-error font-weight-bold text-caption mt-n1">
                  Vencido
                </span>
                <span v-else class="text-warning font-weight-bold text-caption mt-n1">
                  Pendiente
                </span>
              </div>
            </template>
          </VListItem>
        </VList>

        <!-- View All link -->
        <div class="d-flex justify-center pt-2">
          <VBtn
            variant="tonal"
            color="primary"
            size="small"
            to="/finances/pending-payments"
            append-icon="tabler-arrow-right"
            class="px-4 font-weight-bold"
          >
            Ver todos los pagos pendientes
          </VBtn>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1.25rem;
}
.payment-item {
  transition: transform 0.2s ease, background-color 0.2s ease;
  border-radius: 8px;
  
  &:hover {
    transform: translateY(-2px);
    background-color: rgba(var(--v-theme-on-surface), 0.04);
  }
}
</style>
