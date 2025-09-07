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
      }
    ],
  },
]
