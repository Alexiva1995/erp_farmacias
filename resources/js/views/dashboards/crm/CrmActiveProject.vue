<script setup lang="ts">
import axios from '@/plugins/axios'
import { ref, computed, onMounted } from 'vue'

// ── Definición de Interfaz ────
interface EmployeeUnitSale {
  name: string
  photo_url: string | null
  units_sold: number
}

// ── Estado del Componente ────
const employeesData = ref<EmployeeUnitSale[]>([])
const loading = ref(true)

// ── Obtener Datos del Backend ────
const fetchSalesByUnits = async () => {
  try {
    loading.value = true
    const currentYear = new Date().getFullYear()
    const response = await axios.get('/dashboard/employee-sales-units', {
      params: { year: currentYear }
    })
    employeesData.value = response.data.data
  } catch (error) {
    console.error('Error al obtener ventas de empleados por unidades:', error)
  } finally {
    loading.value = false
  }
}

// ── Cálculos Dinámicos de Progreso ────
// El máximo vendedor (primero en la lista) define el 100% de la barra de progreso
const maxUnits = computed(() => {
  if (employeesData.value.length === 0) return 1
  return employeesData.value[0].units_sold || 1
})

const getProgressPercentage = (units: number) => {
  return Math.round((units / maxUnits.value) * 100)
}

// ── Utilidades de Diseño y Avatares ────
// Obtiene las iniciales de un empleado en caso de que no tenga foto
const getInitials = (name: string) => {
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .substring(0, 2)
    .toUpperCase()
}

// Colores consistentes y estéticos para barras y avatares
const colors = ['primary', 'success', 'info', 'warning', 'error', 'secondary']
const getRandomColor = (index: number) => {
  return colors[index % colors.length]
}

onMounted(() => {
  fetchSalesByUnits()
})
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Vendedores por Unidades</VCardTitle>
      <VCardSubtitle>
        Total de unidades vendidas este año
      </VCardSubtitle>
      <template #append>
        <div class="mt-n4 me-n2">
          <VBtn
            icon
            variant="text"
            size="small"
            color="default"
            @click="fetchSalesByUnits"
          >
            <VIcon icon="tabler-refresh" />
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VCardText class="position-relative" style="min-height: 200px;">
      <!-- Estado de Carga -->
      <div
        v-if="loading"
        class="d-flex justify-center align-center position-absolute w-100 h-100 top-0 left-0"
        style="z-index: 2; background: rgba(var(--v-theme-surface), 0.7);"
      >
        <VProgressCircular indeterminate color="primary" />
      </div>

      <!-- Estado Vacío -->
      <div
        v-else-if="employeesData.length === 0"
        class="d-flex flex-column align-center justify-center py-8 text-center"
      >
        <VAvatar color="secondary" variant="tonal" size="48" class="mb-2">
          <VIcon icon="tabler-package" size="24" />
        </VAvatar>
        <span class="text-medium-emphasis text-body-2">No hay unidades vendidas registradas este año</span>
      </div>

      <!-- Listado de Vendedores -->
      <VList v-else class="card-list">
        <VListItem
          v-for="(employee, index) in employeesData"
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
            {{ employee.units_sold }} {{ employee.units_sold === 1 ? 'unidad' : 'unidades' }}
          </VListItemSubtitle>

          <template #append>
            <div class="d-flex align-center gap-x-4">
              <div style="inline-size: 5.5rem;">
                <VProgressLinear
                  :model-value="getProgressPercentage(employee.units_sold)"
                  :color="getRandomColor(index)"
                  height="8"
                  rounded-bar
                  rounded
                />
              </div>
              <span class="font-weight-bold text-medium-emphasis text-body-2" style="min-width: 2.2rem; text-align: right;">
                {{ getProgressPercentage(employee.units_sold) }}%
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

