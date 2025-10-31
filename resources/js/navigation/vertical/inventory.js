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
        title: 'Grupos de Productos',
        to: 'inventory-group-products',
      },
       {
        title: 'Lotes',
        icon: {
        is: 'font-awesome-icon', 
        props: {
          icon: ['fas', 'barcode'],
          size: 'sm',
        },
        },
        children: [
        {
          title: 'Listado de lotes',
          to: 'lot-list',
        },
        {
          title: 'Productos sin lote',
          to: 'lot-products',
        }
        ],
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
          title: 'Inventarios Pendientes',
          to: 'cyclics-cyclic',
          action: 'manage',
          subject: 'supervisor',
        },
        {
          title: 'Inventarios Ciclicos',
          to: 'cyclics-users',
          action: 'manage',
          subject: 'user',
        },
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
        }
        ]
      }
    ],
  }, 
]
