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
        children: [
          {
            title: 'Listado',
            to: 'inventory-products',
        },
        {
          title: 'Código de barras',
          to: 'inventory-barcodes',
        },
        {
          title: 'Laboratorio',
          to: 'inventory-laboratory',
        }
        ],
      },
      {
        title: 'Grupos de Productos',
        to: 'inventory-group-products',
      },
       {
        title: 'Lotes',
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
          subject: 'supervisor',
        }
        ]
      },
      {
        title: 'Inventarios Ciclicos',
        to: 'cyclics-users',
        action: 'manage',
        subject: 'user',
      },
    ],
  }, 
]
