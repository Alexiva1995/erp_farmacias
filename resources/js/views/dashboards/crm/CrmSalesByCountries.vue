<script setup lang="ts">
import axios from '@/plugins/axios'
import { ref, onMounted } from 'vue'

interface EmployeeSale {
  name: string
  photo_url: string | null
  sales_amount: number
  orders_count: number
}

// ── Estado ────
const salesData = ref<EmployeeSale[]>([])
const loading = ref(true)

// ── Cargar Datos ────
const fetchSales = async () => {
  try {
    loading.value = true
    const currentYear = new Date().getFullYear()
    const response = await axios.get(`/dashboard/employee-sales-amount`, {
      params: { year: currentYear }
    })
    salesData.value = response.data.data
  } catch (error) {
    console.error('Error al cargar ventas de empleados:', error)
  } finally {
    loading.value = false
  }
}

// ── Procesamiento reactivo (Doble capa: unificación de Yenireth y filtrado de admin en frontend) ────
const computedSalesData = computed(() => {
  const processed: Record<string, EmployeeSale> = {}
  
  for (const item of salesData.value) {
    const lowerName = item.name.toLowerCase()
    // Excluir administrador
    if (lowerName === 'admin' || lowerName.includes('administrator')) {
      continue
    }
    
    let name = item.name
    const isYenireth = lowerName.includes('yenireth')
    
    if (isYenireth) {
      name = 'Yenireth Itanare'
      if (processed[name]) {
        processed[name].sales_amount += item.sales_amount
        processed[name].orders_count += item.orders_count
        if (item.photo_url && !processed[name].photo_url) {
          processed[name].photo_url = item.photo_url
        }
        continue
      }
    }
    
    processed[name] = {
      name,
      photo_url: item.photo_url,
      sales_amount: item.sales_amount,
      orders_count: item.orders_count
    }
  }
  
  return Object.values(processed).sort((a, b) => b.sales_amount - a.sales_amount)
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

// Obtener iniciales del empleado para el avatar de respaldo
const getInitials = (name: string) => {
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .substring(0, 2)
    .toUpperCase()
}

// Colores de Vuetify para avatares aleatorios/agradables
const colors = ['primary', 'success', 'info', 'warning', 'error', 'secondary']
const getRandomColor = (index: number) => {
  return colors[index % colors.length]
}

onMounted(() => {
  fetchSales()
})
</script>

<template>
  <VCard
    title="Venta de Empleados"
    subtitle="Monto total vendido este año (USD)"
  >
    <template #append>
      <div class="mt-n4 me-n2">
        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          @click="fetchSales"
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
      <div v-else-if="computedSalesData.length === 0" class="d-flex flex-column align-center justify-center py-8 text-center">
        <VAvatar color="secondary" variant="tonal" size="48" class="mb-2">
          <VIcon icon="tabler-users" size="24" />
        </VAvatar>
        <span class="text-medium-emphasis text-body-2">No hay ventas registradas este año</span>
      </div>

      <!-- List of Employees -->
      <VList v-else class="card-list">
        <VListItem
          v-for="(employee, index) in computedSalesData"
          :key="employee.name"
          class="employee-item"
        >
          <template #prepend>
            <VAvatar
              size="40"
              :color="employee.photo_url ? undefined : getRandomColor(index)"
              :variant="employee.photo_url ? undefined : 'tonal'"
              class="me-3"
            >
              <VImg v-if="employee.photo_url" :src="employee.photo_url" alt="Avatar" />
              <span v-else class="font-weight-medium text-body-1">{{ getInitials(employee.name) }}</span>
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-bold text-high-emphasis">
            {{ employee.name }}
          </VListItemTitle>
          <VListItemSubtitle class="text-body-2">
            {{ employee.orders_count }} {{ employee.orders_count === 1 ? 'pedido' : 'pedidos' }}
          </VListItemSubtitle>

          <template #append>
            <div class="d-flex flex-column align-end">
              <span class="font-weight-black text-success text-body-1">
                {{ formatUsd(employee.sales_amount) }}
              </span>
            </div>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1.25rem;
}
.employee-item {
  transition: transform 0.2s ease, background-color 0.2s ease;
  border-radius: 8px;
  
  &:hover {
    transform: translateY(-2px);
    background-color: rgba(var(--v-theme-on-surface), 0.04);
  }
}
</style>
