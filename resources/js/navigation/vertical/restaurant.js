export default [
  {
    title: 'Operativa',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'chart-line'],
        size: 'sm',
      },
    },
    action: 'manage',
    subject: 'admin',
    children: [
      {
        title: 'Auditoría de Procesos',
        to: 'restaurant-process-audit',
      },
    ],
  },
]
