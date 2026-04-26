export default [
  {
    title: 'Inventario',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'boxes-stacked'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Productos',
        to: 'inventory-products',
      },
      {
        title: 'Lotes',
        to: 'lot-list',
      },
      {
        title: 'Optimización',
        children: [
          {
            title: 'Sin Grupo',
            to: 'inventory-products-without-group',
          },
          {
            title: 'Productos incompletos',
            to: 'inventory-incomplete-products',
          },
          {
            title: 'Lotes sin Ubicación',
            to: 'inventory-lots-without-location',
          },
          {
            title: 'Lotificación',
            to: 'inventory-lotificacion',
          },
        ],
      },
      {
        title: 'Grupos de Productos',
        to: 'inventory-group-products',
      },
      {
        title: 'Caducidad',
        to: 'inventory-expirations',
      },
      {
        title: 'Trazabilidad',
        to: 'inventory-traceability',
      },
      {
        title: 'Psicotropicos',
        to: 'inventory-psychotropics',
      },
      {
        title: 'Control de Stock',
        to: 'inventory-stock',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Ubicaciones',
        to: 'inventory-locations',
      },
      {
        title: 'Laboratorios',
        to: 'inventory-laboratories',
      },
    ],
  }, 
]
