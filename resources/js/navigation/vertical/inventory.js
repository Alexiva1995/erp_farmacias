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
        title: 'Grupos de Productos',
        to: 'inventory-group-products',
      },
       {
        title: 'Lotes',
        to: 'lot-list',
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
        title: 'Inventario Ciclicos',
        icon: {
        is: 'font-awesome-icon', 
        props: {
          icon: ['fas', 'boxes-stacked'],
          size: 'sm',
        },
      },
        children: [
                  {
          title: 'Historial',
          to: 'cyclics-history',
          action: 'manage',
          subject: 'cyclic-history',
        },
         {
          title: 'Cierre de Inventario',
          to: 'cyclics-closing',
          action: 'manage',
          subject: 'closing-cyclics',
        },
        {
          title: 'Pendientes',
          to: 'cyclics-cyclic',
          action: 'manage',
          subject: 'pending-cyclics',
        },
        {
          title: 'Inventarios Users',
          to: 'cyclics-users',
          action: 'manage',
          subject: 'user',
        }
        ]
      },
      {
          title: 'Inventario  Ciclicos',
          to: 'cyclics-users',
          action: 'manage',
          subject: 'cycli-user',
        },
    ],
  }, 
]
