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
        title: 'Promociones',
        action: 'manage',
        subject: 'admin',
        children: [
          {
            title: 'Oferta General',
            to: { path: '/tpv/generalOffer' },
          },
          {
            title: 'Oferta Individual',
            to: { path: '/tpv/individualOffer' },
          },
          {
            title: 'Oferta por Categoría',
            to: { path: '/tpv/categoryOffer' },
          },
          {
            title: 'Oferta de Combos / Packs',
            to: { path: '/tpv/packOffer' },
          },
          {
            title: 'Oferta por Convenio',
            to: { path: '/tpv/companyOffer' },
          },
          {
            title: 'Oferta por Médico',
            to: { path: '/tpv/doctorOffer' },
          },
          {
            title: 'Oferta por Receta / Récipe',
            to: { path: '/tpv/prescriptionOffer' },
          },
          {
            title: 'Oferta por Caducidad',
            to: { path: '/tpv/expirationOffer' },
          },
        ]
      }
    ],
  },
]
