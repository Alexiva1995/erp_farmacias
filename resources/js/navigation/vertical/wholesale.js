export default [
  {
    title: 'Droguería B2B',
    icon: {
      is: 'font-awesome-icon',
      props: {
        icon: ['fas', 'truck'],
        size: 'sm',
      },
    },
    action: 'manage',
    subject: 'supervisor-or-admin',
    children: [
      {
        title: 'Cartera y Cupos',
        to: 'wholesale-portfolio',
        action: 'manage',
        subject: 'supervisor-or-admin',
      },
      {
        title: 'Telemarketing / Pedidos',
        to: 'wholesale-telemarketing-workstation',
        action: 'manage',
        subject: 'supervisor-or-admin',
      },
      {
        title: 'Marketplace Farmacias',
        to: 'wholesale-marketplace',
        action: 'manage',
        subject: 'supervisor-or-admin',
      },
      {
        title: 'Cobranzas y Conciliación',
        to: 'wholesale-collections',
        action: 'manage',
        subject: 'supervisor-or-admin',
      },
      {
        title: 'Almacén y Despacho WMS',
        to: 'wholesale-wms-dispatch-board',
        action: 'manage',
        subject: 'supervisor-or-admin',
      },
    ],
  },
]
