export default [
  {
    title: 'Facturas',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'file-invoice'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Registrar',
        to: 'invoice-register',
      },
      {
        title: 'Pendientes',
        to: 'invoice-invoices',
      },
      {
        title: 'Cargadas',
        to: 'invoice-invoice-loaded',
      },
      {
        title: 'Por Ordenar',
        to: 'invoice-invoice-for-order',
      },
      {
        title: 'Ordenadas',
        to: 'invoice-invoice-ordered',
      },
    ],
  }, 
]
