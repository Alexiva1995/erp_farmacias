export default [
  {
    title: 'BI',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'chart-bar'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Reporte ABC',
        to: 'bi-report-abc', // Necesitará una entrada en el router o arreglar la ruta de vue
      },
      {
        title: 'Reporte de Margen SKU',
        to: 'bi-report-sku',
      },
      {
        title: 'Dashboard Maestro',
        to: 'bi-report-products',
      },
      {
        title: 'BI Caducidad',
        to: 'bi-report-expiry',
      },
      {
        title: 'Laboratorios',
        to: 'bi-report-laboratories',
      },
      {
        title: 'Analíticas TPV',
        to: 'bi-analytics-pos',
      },
      {
        title: 'Análisis Cíclico',
        to: 'bi-inventory-cyclic',
      },
      {
        title: 'Promociones y Descuentos',
        to: 'bi-discounts',
      },
      {
        title: 'Analítica de Clientes',
        to: 'bi-customer-analytics',
      },
      {
        title: 'Rendimiento RRHH',
        to: 'bi-employee-performance',
      },
    ],
  }, 
];
