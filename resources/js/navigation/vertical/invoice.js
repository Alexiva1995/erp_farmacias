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
        title: 'Facturas Pendientes',
        to: 'invoice-invoices',
      },
      {
        title: 'Facturas Cargadas',
        to: 'invoice-invoice-loaded',
      },
      {
        title: 'Facturas Por Ordenar',
        to: 'invoice-invoice-for-order',
      },
      {
        title: 'Facturas Ordenadas',
        to: 'invoice-invoice-ordered',
      },
    ],
  }, 
]
