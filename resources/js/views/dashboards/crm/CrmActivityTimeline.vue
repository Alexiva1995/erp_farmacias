<script lang="ts" setup>
import axios from '@/plugins/axios'
import { ref, onMounted } from 'vue'

interface ExpiringSoldProduct {
  id: number
  product_name: string
  laboratory_name: string
  lot_number: string
  expiration_date: string | null
  quantity: number
  sold_date: string
  user_name: string
}

// ── Estado ────
const soldProducts = ref<ExpiringSoldProduct[]>([])
const loading = ref(true)

// ── Cargar Productos Vendidos por Vencer ────
const fetchSoldExpiringProducts = async () => {
  try {
    loading.value = true
    const response = await axios.get('/dashboard/expiring-sold-products')
    soldProducts.value = Array.isArray(response.data) ? response.data : (response.data.data || [])
  } catch (error) {
    console.error('Error al cargar ventas de productos por vencer:', error)
  } finally {
    loading.value = false
  }
}

// ── Formateadores ────
const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

const formatDateTimeRelative = (dateTimeStr: string) => {
  if (!dateTimeStr) return ''
  const date = new Date(dateTimeStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  
  if (diffMs < 0) return 'Recientemente'
  
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 60) return `Hace ${diffMins} min`
  
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `Hace ${diffHours} hr`
  
  return formatDate(dateTimeStr)
}

onMounted(() => {
  fetchSoldExpiringProducts()
})
</script>

<template>
  <VCard>
    <VCardItem>
      <template #prepend>
        <VIcon
          icon="tabler-alert-circle"
          size="22"
          color="warning"
          class="me-2"
        />
      </template>
      <template #append>
        <div class="mt-n4 me-n2">
          <VBtn
            icon
            variant="text"
            size="small"
            color="default"
            @click="fetchSoldExpiringProducts"
          >
            <VIcon icon="tabler-refresh" />
          </VBtn>
        </div>
      </template>

      <VCardTitle>Productos que Vencían este Mes y se Vendieron</VCardTitle>
      <VCardSubtitle>Control y trazabilidad de vencimientos del mes corriente</VCardSubtitle>
    </VCardItem>

    <VCardText class="position-relative" style="min-height: 250px;">
      <!-- Loading State -->
      <div v-if="loading" class="d-flex justify-center align-center position-absolute w-100 h-100 top-0 left-0" style="z-index: 2; background: rgba(var(--v-theme-surface), 0.7);">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <!-- Empty State -->
      <div v-else-if="soldProducts.length === 0" class="d-flex flex-column align-center justify-center py-10 text-center px-4">
        <VAvatar color="success" variant="tonal" size="54" class="mb-3">
          <VIcon icon="tabler-shield-check" size="28" />
        </VAvatar>
        <span class="text-high-emphasis font-weight-black text-body-1 mb-1">¡Sin pérdidas por caducidad!</span>
        <span class="text-medium-emphasis text-body-2" style="max-width: 320px;">
          No se registran ventas de productos con vencimiento en el mes en curso.
        </span>
      </div>

      <!-- Timeline of Expiring Sold Products -->
      <VTimeline
        v-else
        side="end"
        align="start"
        line-inset="8"
        truncate-line="start"
        density="compact"
        class="ps-2 pe-2"
      >
        <VTimelineItem
          v-for="item in soldProducts"
          :key="item.id"
          size="x-small"
          dot-color="warning"
          class="timeline-item-hover"
        >
          <!-- 👉 Header -->
          <div class="d-flex justify-space-between align-start gap-2 flex-wrap mb-1">
            <span class="app-timeline-title font-weight-black text-body-1 text-high-emphasis">
              {{ item.product_name }}
            </span>
            <span class="app-timeline-meta text-xs text-medium-emphasis">
              {{ formatDateTimeRelative(item.sold_date) }}
            </span>
          </div>

          <!-- 👉 Subtitle / Details -->
          <div class="app-timeline-text mt-0">
            <div class="d-flex align-center flex-wrap gap-x-2 text-caption">
              <span class="text-medium-emphasis">
                Lote: <strong class="text-high-emphasis">{{ item.lot_number }}</strong>
              </span>
              <span class="text-disabled">|</span>
              <span class="text-medium-emphasis">
                Vence: <strong class="text-error">{{ formatDate(item.expiration_date) }}</strong>
              </span>
              <span class="text-disabled">|</span>
              <span class="text-medium-emphasis">
                Lab: <strong class="text-high-emphasis">{{ item.laboratory_name }}</strong>
              </span>
            </div>
          </div>

          <!-- 👉 Sales Chips -->
          <div class="d-flex align-center flex-wrap gap-2 mt-2">
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
              class="font-weight-black"
            >
              <VIcon icon="tabler-shopping-cart" size="12" class="me-1" />
              {{ item.quantity }} Unidades Vendidas
            </VChip>

            <VChip
              size="x-small"
              color="secondary"
              variant="tonal"
              class="font-weight-medium"
            >
              <VIcon icon="tabler-user" size="12" class="me-1" />
              Vendido por: {{ item.user_name }}
            </VChip>
          </div>
        </VTimelineItem>
      </VTimeline>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.timeline-item-hover {
  transition: transform 0.2s ease;
  
  &:hover {
    transform: translateX(4px);
  }
}
</style>
