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
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Tasa de cambio',
        to: 'finances-exchangerate',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Por Pagar',
        to: 'finances-pending-payments',
        action: 'manage',
        subject: 'finances-pending-payments',
      },
      {
            title: 'Historial de Pagos',
            to: 'finances-payment-history',
            action: 'manage',
            subject: 'finances-payment-history',
          },
          {
            title: 'Flujo de caja',
            to: 'finances-cashout',
            action: 'manage',
            subject:'finances-cashout',
          },
          {
            title: 'Nómina',
            to: 'finances-payslips',
            action: 'manage',
            subject:'finances-payslips',
          },
          {
            title: 'Cierre de caja',
            to: 'finances-cash-closure',
            action: 'manage',
            subject:'finances-cash-closure',
          },
          {
            title: 'Cierre de caja Usuarios',
            to: 'finances-cash-closure-user',
            action: 'manage',
            subject:'finances-cash-closure-user',
          },
          {
            title: 'Cierre de caja',
            to: 'finances-cash-closure-user',
            action: 'manage',
            subject:'user',
          },
          {
            title: 'Estado de Resultados',
            to: 'finances-income-statement',
            action: 'manage',
            subject: 'admin',
          },
      {
        title: 'Gasto',
        action: 'manage',
        subject: 'admin',
        children: [
          {
            title: 'Gastos',
            to: 'finances-expense-expenses',
          },
          {
            title: 'Gastos Pendientes',
            to: 'finances-expense-pending-expenses',
          },
          /*{
            title: 'Gastos Recurrentes',
            to: 'finances-expense-recurring-expense',
          }*/
        ],
      },
      {
        title: 'Gastos',
        to: 'finances-expense-expenses',
        action: 'manage',
        subject: 'gastos-expenses',
      },
      {
        title: 'Balance General',
        to: 'balance-general',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Mobiliario',
        to: 'furnitures-list',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Prestamos',
        to: 'loans-list',
        action: 'manage',
        subject: 'admin',
      },
    ],
  },
]
