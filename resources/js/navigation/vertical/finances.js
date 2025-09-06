export default [
  {
    title: 'Finanzas',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'chart-simple'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Rentabilidad',
        to: 'finances-profitability',
      },
      {
        title: 'Tasa de cambio',
        to: 'finances-exchangerate',
      },
      {
        title: 'Flujo de caja',
        to: 'finances-cashout',
      },
    ],
  },
]
