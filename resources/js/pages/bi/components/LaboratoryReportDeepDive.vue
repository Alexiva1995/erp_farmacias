<script setup>
import { computed, ref } from 'vue';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';
import { toast } from '@/plugins/sweetalert';

const props = defineProps({
  selectedLabId: {
    type: [Number, String, null],
    default: null
  },
  laboratories: {
    type: Array,
    default: () => []
  },
  deepDiveData: {
    type: Object,
    required: true
  },
  loadingDeepDive: {
    type: Boolean,
    default: false
  }
});

const { formatCurrency } = useCurrencyConverter();
const search = ref('');
const itemsPerPage = ref(10);

const headers = [
  { title: 'PRODUCTO', key: 'name', sortable: true },
  { title: 'UNIDADES', key: 'units', align: 'center', sortable: true },
  { title: 'VENTA BRUTA (USD)', key: 'revenue', align: 'end', sortable: true },
  { title: 'MARGEN ESTIMADO', key: 'estimated_margin', align: 'end', sortable: true }
];

const currentLabName = computed(() => {
  return props.laboratories.find(l => l.id === props.selectedLabId)?.name || 'Laboratorio Seleccionado';
});

const productsList = computed(() => {
  return props.deepDiveData.top_products || [];
});

const totalUnits = computed(() => {
  return productsList.value.reduce((acc, p) => acc + (parseFloat(p.units) || 0), 0);
});

const totalRevenue = computed(() => {
  return productsList.value.reduce((acc, p) => acc + (parseFloat(p.revenue) || 0), 0);
});

const totalMargin = computed(() => {
  return productsList.value.reduce((acc, p) => acc + (parseFloat(p.estimated_margin) || 0), 0);
});

// Exportar productos del laboratorio seleccionado a CSV
const exportProductsCsv = () => {
  try {
    if (!productsList.value.length) {
      toast.info('No hay productos para exportar');
      return;
    }

    let csv = 'Producto,Unidades,Venta Bruta USD,Margen Estimado USD\n';
    productsList.value.forEach(p => {
      csv += `"${(p.name || '').replace(/"/g, '""')}",${p.units || 0},${p.revenue || 0},${p.estimated_margin || 0}\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `productos_${currentLabName.value.toLowerCase().replace(/\s+/g, '_')}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toast.success('Productos exportados a CSV');
  } catch (err) {
    console.error('Error exportando productos:', err);
    toast.error('Error al exportar productos');
  }
};
</script>

<template>
  <VCard v-if="selectedLabId" border class="mt-4 rounded-lg overflow-hidden">
    <VCardTitle class="pa-4 border-b d-flex flex-wrap align-center justify-space-between ga-3 bg-surface">
      <div class="d-flex align-center">
        <VAvatar color="warning" variant="tonal" size="40" class="me-3">
          <VIcon icon="tabler-zoom-in" size="22" />
        </VAvatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold text-uppercase text-primary">
            Exploración Detallada: {{ currentLabName }}
          </div>
          <div class="text-caption text-medium-emphasis d-flex flex-wrap align-center ga-2 mt-1">
            <VChip size="x-small" variant="tonal" color="primary">
              {{ productsList.length }} Productos
            </VChip>
            <VChip size="x-small" variant="tonal" color="info">
              {{ Math.round(totalUnits).toLocaleString() }} Unidades
            </VChip>
            <VChip size="x-small" variant="tonal" color="success">
              {{ formatCurrency(totalRevenue) }} Venta
            </VChip>
            <VChip size="x-small" variant="tonal" color="secondary">
              {{ formatCurrency(totalMargin) }} Margen
            </VChip>
          </div>
        </div>
      </div>

      <div class="d-flex align-center ga-2" style="max-width: 380px; width: 100%;">
        <VTextField
          v-model="search"
          placeholder="Buscar producto..."
          prepend-inner-icon="tabler-search"
          variant="outlined"
          density="compact"
          hide-details="auto"
          clearable
        />
        <VBtn
          icon="tabler-file-spreadsheet"
          variant="tonal"
          color="success"
          density="comfortable"
          :disabled="loadingDeepDive || !productsList.length"
          @click="exportProductsCsv"
        >
          <VIcon icon="tabler-file-spreadsheet" />
          <VTooltip activator="parent" location="top">Exportar productos a CSV</VTooltip>
        </VBtn>
      </div>
    </VCardTitle>

    <VCardText class="pa-0">
      <div v-if="loadingDeepDive" class="pa-10">
        <VSkeletonLoader type="table-row-divider@6" />
      </div>
      <template v-else>
        <VDataTable
          :headers="headers"
          :items="productsList"
          :search="search"
          v-model:items-per-page="itemsPerPage"
          density="comfortable"
          hover
          class="border-t"
          no-data-text="No se encontraron productos vendidos para este laboratorio en el periodo"
        >
          <!-- Columna Producto -->
          <template #item.name="{ item }">
            <div class="text-caption font-weight-bold text-uppercase py-2">
              {{ item.name }}
            </div>
          </template>

          <!-- Columna Unidades -->
          <template #item.units="{ item }">
            <span class="font-weight-bold">
              {{ Math.round(item.units || 0).toLocaleString() }}
            </span>
          </template>

          <!-- Columna Venta Bruta -->
          <template #item.revenue="{ item }">
            <span class="font-weight-bold text-success">
              {{ formatCurrency(item.revenue) }}
            </span>
          </template>

          <!-- Columna Margen Estimado -->
          <template #item.estimated_margin="{ item }">
            <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
              {{ formatCurrency(item.estimated_margin) }}
            </VChip>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <VEmptyState
              icon="tabler-package-off"
              title="Sin productos"
              text="No se registraron productos vendidos para este laboratorio en el periodo"
              class="py-8"
            />
          </template>
        </VDataTable>
      </template>
    </VCardText>
  </VCard>
</template>
