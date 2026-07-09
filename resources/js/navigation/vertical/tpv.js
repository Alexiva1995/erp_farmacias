export default [
  {
    title: 'TPV',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'file-lines'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Cotización',
        to: 'tpv-quotation',
      },
      {
        title: 'Punto de Venta',
        to: 'tpv-order-user',
      },
       {
        title: 'Créditos',
        to: 'tpv-credit',
      },
      {
        title: 'Pedidos',
        to: 'tpv-order-general',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Pedidos Eco',
        to: 'tpv-ecommerce-orders',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Ecommerce',
        to: 'tova-store',
      },
    ],
  },
]
