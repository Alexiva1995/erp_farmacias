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
        title: 'Por Pagar',
        to: 'finances-pending-payments',
      },
      {
        title: 'Historial de Pagos',
        to: 'finances-payment-history',
      },
      {
        title: 'Flujo de caja',
        to: 'finances-cashout',
      },
      {
        title: 'Cierre de caja',
        to: 'finances-cash-closure',
      },
    ],
  },
]
