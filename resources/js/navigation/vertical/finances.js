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
        title: 'Gasto',
        icon: {
          is: 'font-awesome-icon', 
          props: {
            icon: ['fas', 'chart-simple'],
            size: 'sm',
          },
        },
        children: [
          {
            title: 'Gastos',
            to: 'finances-expense-expenses',
          },
          {
            title: 'Gastos Pendientes',
            to: 'finances-expense-pending-expenses',
          },
          {
            title: 'Gastos Recurrentes',
            to: 'finances-expense-recurring-expense',
          }
        ],
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
        title: 'Estado de Resultados',
        to: 'finances-income-statement',
      },
      {
        title: 'Nómina', 
        to: 'finances-payslips',
      },
    ],
  },
]
