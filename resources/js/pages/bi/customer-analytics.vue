<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { applyPremiumHeader, applyFooter } from '@/utils/pdfBaseStyles';

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
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const subtitle = `Periodo: ${startDate.value} al ${endDate.value}`;
    let currentY = applyPremiumHeader(doc, 'Reporte Ejecutivo de Analítica de Clientes', subtitle);

    const kpis = analyticsData.value.kpis || {};

    // 1. Resumen de KPIs
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(226, 0, 116);
    doc.text('1. RESUMEN DE INDICADORES CLAVE (KPIS)', 15, currentY);
    currentY += 4;

    const kpiRows = [
      [
        'Tasa de Retención (CRR)', `${(kpis.crr || 0).toFixed(1)}%`,
        'Tasa de Recompra', `${(kpis.repurchase_rate || 0).toFixed(1)}%`,
      ],
      [
        'Tasa de Abandono (Churn)', `${(kpis.churn_rate || 0).toFixed(1)}%`,
        'LTV Promedio', `$${(kpis.avg_ltv || 0).toFixed(2)}`,
      ],
      [
        'Ticket Promedio (AOV)', `$${(kpis.aov || 0).toFixed(2)}`,
        'Ingresos Totales', `$${(kpis.total_revenue || 0).toFixed(2)} (${kpis.total_orders || 0} órdenes)`,
      ],
    ];

    autoTable(doc, {
      body: kpiRows,
      startY: currentY,
      theme: 'grid',
      styles: { fontSize: 9, cellPadding: 2.5, font: 'helvetica' },
      columnStyles: {
        0: { fontStyle: 'bold', fillColor: [248, 249, 250], cellWidth: 50 },
        1: { cellWidth: 40 },
        2: { fontStyle: 'bold', fillColor: [248, 249, 250], cellWidth: 50 },
        3: { cellWidth: 40 },
      },
    });

    currentY = doc.lastAutoTable.finalY + 8;

    // 2. Pirámide de Valor y Segmentación
    const seg = analyticsData.value.segmentation || {};
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(226, 0, 116);
    doc.text('2. SEGMENTACIÓN POR VALOR DEL CLIENTE', 15, currentY);
    currentY += 4;

    const segRows = [
      ['Platino (Top 5%)', `${seg.platinum?.count || 0}`, `$${(seg.platinum?.revenue || 0).toFixed(2)}`, `$${(seg.platinum?.avg_per_client || 0).toFixed(2)}`],
      ['Oro (Top 15%)', `${seg.gold?.count || 0}`, `$${(seg.gold?.revenue || 0).toFixed(2)}`, `$${(seg.gold?.avg_per_client || 0).toFixed(2)}`],
      ['Plata (Top 30%)', `${seg.silver?.count || 0}`, `$${(seg.silver?.revenue || 0).toFixed(2)}`, `$${(seg.silver?.avg_per_client || 0).toFixed(2)}`],
      ['Bronce (Resto 50%)', `${seg.bronze?.count || 0}`, `$${(seg.bronze?.revenue || 0).toFixed(2)}`, `$${(seg.bronze?.avg_per_client || 0).toFixed(2)}`],
    ];

    autoTable(doc, {
      head: [['Segmento', 'N° Clientes', 'Facturación Total', 'Gasto Promedio']],
      body: segRows,
      startY: currentY,
      theme: 'grid',
      headStyles: { fillColor: [226, 0, 116], textColor: [255, 255, 255], fontStyle: 'bold' },
      styles: { fontSize: 8.5, cellPadding: 2 },
    });

    currentY = doc.lastAutoTable.finalY + 8;

    // 3. Clientes Críticos en Riesgo (RFM)
    const atRisk = analyticsData.value.at_risk || [];
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(226, 0, 116);
    doc.text('3. TOP CLIENTES CRÍTICOS EN RIESGO DE ABANDONO', 15, currentY);
    currentY += 4;

    if (atRisk.length > 0) {
      const riskRows = atRisk.map((c) => [
        `${c.name || ''} ${c.last_name || ''}`,
        c.phone || 'N/A',
        c.last_order_date || 'N/A',
        `${c.recency_days || 0} días`,
        `$${Number(c.monetary || 0).toFixed(2)}`,
      ]);

      autoTable(doc, {
        head: [['Cliente', 'Teléfono', 'Última Compra', 'Inactividad', 'Gasto Total']],
        body: riskRows,
        startY: currentY,
        theme: 'striped',
        headStyles: { fillColor: [255, 76, 81], textColor: [255, 255, 255], fontStyle: 'bold' },
        styles: { fontSize: 8, cellPadding: 2 },
      });
    }

    applyFooter(doc);
    doc.save(`analitica_clientes_${startDate.value}_al_${endDate.value}.pdf`);
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
