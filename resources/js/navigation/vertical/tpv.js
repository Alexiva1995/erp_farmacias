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
        title: 'Pedidos',
        to: 'tpv-order-general',
       // meta: { roles: ['admin'] }
      },
       {
        title: 'Pedidos Usuario',
        to: 'tpv-order-user',
       // meta: { roles: ['user'] }
      },
      {
        title: 'Creditos',
        to: 'tpv-credit',
      },
    ],
  },
]
