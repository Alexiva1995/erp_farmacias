export default [
  {
    title: 'Business Intelligence',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'file-invoice'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Reporte ABC',
        to: 'business-intelligence-report-abc',
        action: 'manage',
      },
    ],
  }, 
]
