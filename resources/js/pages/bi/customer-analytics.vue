<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { generateBiCustomerExecutivePdf } from '@/utils/pdfBiCustomerAnalyticsGenerator';

import CustomerAnalyticsHeader from '@/components/bi/customer-analytics/CustomerAnalyticsHeader.vue';
import CustomerAnalyticsKpis from '@/components/bi/customer-analytics/CustomerAnalyticsKpis.vue';
import CustomerAcquisitionChart from '@/components/bi/customer-analytics/CustomerAcquisitionChart.vue';
import CustomerFrequencyChart from '@/components/bi/customer-analytics/CustomerFrequencyChart.vue';
import CustomerCohortsTable from '@/components/bi/customer-analytics/CustomerCohortsTable.vue';
import CustomerSegmentationTreemap from '@/components/bi/customer-analytics/CustomerSegmentationTreemap.vue';
import CustomerAtRiskTable from '@/components/bi/customer-analytics/CustomerAtRiskTable.vue';

// --- ESTADO REACTIVO ---
const loading = ref(false);
const exportingPdf = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10));
const endDate = ref(new Date().toISOString().substring(0, 10));
const analyticsData = ref(null);

let abortController = null;

// --- CARGA DE DATOS ASÍNCRONA ---
const fetchAnalytics = async () => {
  if (abortController) {
    abortController.abort();
  }
  abortController = new AbortController();

  loading.value = true;

  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
    };
    const { data } = await axios.get('/bi/customers/dashboard', {
      params,
      signal: abortController.signal,
    });
    analyticsData.value = data;
  } catch (err) {
    if (err.name !== 'CanceledError') {
      const message = err.response?.data?.message || 'Error al cargar la analítica de clientes.';
      toast.error(message);
      console.error('Error al cargar analítica de clientes:', err);
    }
  } finally {
    loading.value = false;
  }
};

// --- EXPORTACIÓN EJECUTIVA A PDF ---
const handleExportPdf = () => {
  if (!analyticsData.value) {
    toast.warning('No hay datos disponibles para exportar.');
    return;
  }

  exportingPdf.value = true;
  try {
    generateBiCustomerExecutivePdf(analyticsData.value, {
      start_date: startDate.value,
      end_date: endDate.value,
    });
    toast.success('Reporte ejecutivo PDF generado exitosamente.');
  } catch (e) {
    console.error('Error generando reporte PDF:', e);
    toast.error('Ocurrió un error al generar el PDF.');
  } finally {
    exportingPdf.value = false;
  }
};

onMounted(() => {
  fetchAnalytics();
});

watch([startDate, endDate], () => {
  fetchAnalytics();
});
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Header y Filtros de Fecha -->
    <CustomerAnalyticsHeader
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :loading="loading"
      :exporting-pdf="exportingPdf"
      @refresh="fetchAnalytics"
      @export-pdf="handleExportPdf"
    />

    <!-- Fila 1: KPIs Principales -->
    <CustomerAnalyticsKpis
      :kpis="analyticsData?.kpis"
      :loading="loading"
    />

    <!-- Fila 2: Crecimiento y Frecuencia -->
    <VRow class="mb-6" dense>
      <VCol cols="12" md="8">
        <CustomerAcquisitionChart
          :growth-data="analyticsData?.growth"
          :loading="loading"
        />
      </VCol>

      <VCol cols="12" md="4">
        <CustomerFrequencyChart
          :frequency="analyticsData?.frequency"
          :total-customers="analyticsData?.kpis?.total_customers"
          :loading="loading"
        />
      </VCol>
    </VRow>

    <!-- Fila 3: Análisis de Cohortes -->
    <VRow class="mb-6" dense>
      <VCol cols="12">
        <CustomerCohortsTable
          :cohorts="analyticsData?.cohorts"
          :loading="loading"
        />
      </VCol>
    </VRow>

    <!-- Fila 4: Segmentación y Clientes en Riesgo -->
    <VRow dense>
      <VCol cols="12" md="6">
        <CustomerSegmentationTreemap
          :segmentation="analyticsData?.segmentation"
          :loading="loading"
        />
      </VCol>

      <VCol cols="12" md="6">
        <CustomerAtRiskTable
          :at-risk="analyticsData?.at_risk"
          :loading="loading"
        />
      </VCol>
    </VRow>
  </VContainer>
</template>
