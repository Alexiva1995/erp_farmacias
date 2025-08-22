export default [
  {
    title: 'Facturas',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'boxes-stacked'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Registrar Facturas',
        to: 'invoice-register',
      },
      {
        title: 'Facturas Cargadas',
        to: 'invoice-invoices',
      },
      {
        title: 'Facturas por ordenar',
        to: 'invoice-invoice-for-order',
      },
    ],
  }, 
]
