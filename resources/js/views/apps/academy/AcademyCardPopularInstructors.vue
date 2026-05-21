<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'

const sellersRanking = ref([])

const fetchRanking = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    sellersRanking.value = response.data.sellers_ranking || []
  } catch (error) {
    console.error('Error al cargar ranking de vendedores:', error)
  }
}

const formatCurrencyUSD = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}

onMounted(fetchRanking)
</script>

<template>
  <VCard
    title="Top Vendedores este mes"
    subtitle="Ranking por volumen de venta USD"
  >
    <VCardText>
      <VTable class="text-no-wrap">
        <thead>
          <tr>
            <th class="ps-0">VENDEDOR</th>
            <th class="text-center">VENTA TOTAL</th>
            <th class="text-center">LAB. VENTAS</th>
            <th class="text-end">LAB. UNI</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(seller, index) in sellersRanking"
            :key="index"
          >
            <td class="ps-0">
              <div class="d-flex align-center">
                <VAvatar
                  size="34"
                  :color="['primary', 'info', 'success', 'warning', 'error', 'secondary'][index % 6]"
                  variant="tonal"
                  class="me-3"
                >
                  <span class="text-xs font-weight-medium">{{ seller.name.substring(0, 2).toUpperCase() }}</span>
                </VAvatar>
                <div class="d-flex flex-column">
                  <div class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ seller.name }}
                  </div>
                  <div class="text-caption text-disabled text-capitalize">
                    Vendedor
                  </div>
                </div>
              </div>
            </td>
            <td class="text-center text-success font-weight-bold">
              {{ formatCurrencyUSD(seller.total) }}
            </td>
            <td class="text-center">
              <VChip size="small" color="primary" variant="tonal" label>
                {{ seller.top_lab_amount }}
              </VChip>
            </td>
            <td class="text-end">
              <VChip size="small" color="info" variant="tonal" label>
                {{ seller.top_lab_units }}
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
      
      <div v-if="sellersRanking.length === 0" class="text-center py-10 text-disabled">
        Sin ventas registradas este mes
      </div>
    </VCardText>
  </VCard>
</template>
