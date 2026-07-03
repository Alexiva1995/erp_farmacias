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
        title: 'Promociones',
        action: 'manage',
        subject: 'admin',
        children: [
          {
            title: 'Individual',
            to: 'tpv-individual-offer',
          },
          {
            title: 'Packs',
            to: 'tpv-pack-offer',
          },
          {
            title: 'Categorías',
            to: 'tpv-category-offer',
          },
          {
            title: 'Empresas',
            to: 'tpv-company-offer',
          },
          {
            title: 'Médicos',
            to: 'tpv-doctor-offer',
          },
          {
            title: 'Recetas',
            to: 'tpv-prescription-offer',
          },
          {
            title: 'Vencimientos',
            to: 'tpv-expiration-offer',
          },
          {
            title: 'Generales',
            to: 'tpv-general-offer',
          },
        ],
      },
    ],
  },
]
