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
    ],
  }, 
]
