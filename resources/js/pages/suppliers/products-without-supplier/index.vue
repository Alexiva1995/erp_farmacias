<script setup lang="js">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const products = ref([]);
const loading = ref(true);

const headers = [
  { title: '#', key: 'index', sortable: false, width: '50px' },
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Producto', key: 'name', sortable: true },
  { title: 'Laboratorio', key: 'laboratory.name', sortable: false },
  { title: 'Ventas', key: 'total_sold_completed', sortable: true },
  { title: 'Promedio', key: 'promedio_calculado', sortable: true },
  { title: 'Costo Lot.', key: 'lot_cost', sortable: false },
  { title: 'Costo Unit.', key: 'unit_cost', sortable: true },
  { title: 'Stock', key: 'stockFaltante', sortable: true },
  { title: 'Precio Venta', key: 'sale_price', sortable: true },
];

onMounted(() => {
  try {
    const productosParam = route.query.productos;
    if (productosParam) {
      products.value = JSON.parse(productosParam);
      // Agregar índice y formatear datos
      products.value = products.value.map((product, index) => {
        const latestLot = product.lots && product.lots.length > 0 ? product.lots[0] : null;
        let lotCostInfo = 'Sin lotes';
        if (latestLot) {
          const createdDate = new Date(latestLot.created_at);
          const dateFormatted = `${createdDate.getDate()}/${String(createdDate.getMonth() + 1).padStart(2, '0')}/${createdDate.getFullYear()}`;
          lotCostInfo = `$${parseFloat(latestLot.unit_cost || 0).toFixed(2)} (${dateFormatted})`;
        }
        return {
          ...product,
          index: index + 1,
          lot_cost: lotCostInfo,
        };
      });
    }
  } catch (error) {
    console.error('Error al parsear productos:', error);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="pa-6">
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <div>
          <h2 class="text-h5">Productos Sin Proveedores</h2>
          <span class="text-caption text-medium-emphasis">
            Generado el: {{ new Date().toLocaleDateString('es-ES') }}
          </span>
        </div>
        <VChip color="primary" variant="tonal" size="large">
          Total: {{ products.length }}
        </VChip>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VDataTableServer
          :headers="headers"
          :items="products"
          :loading="loading"
          class="text-no-wrap"
          density="compact"
        >
          <template #item.index="{ item }">
            {{ item.index }}
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center">
              <VAvatar
                v-if="item.photo_url"
                size="34"
                rounded
                variant="tonal"
                class="me-3"
                :image="item.photo_url"
              />
              <span class="font-weight-medium">{{ item.name }}</span>
            </div>
          </template>

          <template #item.total_sold_completed="{ item }">
            {{ item.total_sold_completed ?? 0 }}
          </template>

          <template #item.promedio_calculado="{ item }">
            {{ parseFloat(item.promedio_calculado || 0).toFixed(2) }}
          </template>

          <template #item.unit_cost="{ item }">
            ${{ parseFloat(item.unit_cost || 0).toFixed(2) }}
          </template>

          <template #item.stockFaltante="{ item }">
            {{ item.stockFaltante ?? 0 }}
          </template>

          <template #item.sale_price="{ item }">
            ${{ parseFloat(item.sale_price || 0).toFixed(2) }}
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>
  </div>
</template>

