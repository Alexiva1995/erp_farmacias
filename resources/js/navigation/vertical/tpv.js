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
      {
        title: 'Devoluciones',
        to: 'tpv-returns',
      },
      {
        title: 'Devoluciones Usuario',
        to: 'tpv-returns-user',
      },
      {
        title: 'Devoluciones Supervisor',
        to: 'tpv-returns-supervisor',
      },
      {
        title: 'Promociones',
        children: [
          {
            title: 'Pack de Productos',
            to: 'tpv-pack-offer',
          },
          {
            title: 'Oferta Individual',

            to: 'tpv-individual-offer',
          },
          {
            title: 'Oferta por Categoria',

            to: 'tpv-category-offer',
          },
          {
            title: 'Oferta por Empresa',

            to: 'tpv-company-offer',
          },
          {
            title: 'Oferta por Medico',

            to: 'tpv-doctor-offer',
          },
          {
            title: 'Oferta por Recipe',

            to: 'tpv-prescription-offer',
          },
          {
            title: 'Oferta de Caducidad',

            to: 'tpv-expiration-offer',
          },
        ],
      },
    ],
  },
]
