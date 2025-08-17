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
            title: 'Facturas',
            to: 'invoice-invoices',
        },
        {
            title: 'Registrar Facturas',
            to: 'invoice-register',
        },
    ],
  }, 
]
