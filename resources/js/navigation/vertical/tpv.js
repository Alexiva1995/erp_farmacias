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
        title: 'Creditos',
        to: 'tpv-credit',
      },
      {
        title: 'Devoluciones',
        action: 'manage',
        subject: 'admin',
        children: [
         {
          title: 'Usuario',
          to: 'tpv-returns-user',
        },
        {
          title: 'Supervisor',
          to: 'tpv-returns-supervisor',
        },
          {
           title: 'Generales',
            to: 'tpv-returns',
          },
          
        ],
      },
      {
        title: 'Devoluciones',
        to: 'tpv-returns-user',
        action: 'manage',
        subject: 'user',
      },
      {
        title: 'Devoluciones',
        action: 'manage',
        subject: 'supervisor',
        children: [
          {
            title: 'Usuario',
            to: 'tpv-returns-user',
          },
          {
            title: 'Supervisor',
            to: 'tpv-returns-supervisor',
          },
        ],
      },
      {
        title: 'Promociones',
        action: 'manage',
        subject: 'admin',
        children: [
          {
            title: 'Pack de Productos',
            to: 'tpv-pack-offer',
          },
          {
            title: 'Individual',

            to: 'tpv-individual-offer',
          },
          {
            title: 'Categoria',

            to: 'tpv-category-offer',
          },
          {
            title: 'Empresa',

            to: 'tpv-company-offer',
          },
          {
            title: 'Caducidad',

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
