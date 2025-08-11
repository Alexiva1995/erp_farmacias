export default [
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
      },
      {
        title: 'Inventarios Users',
        to: 'cyclics-users',
      },
      {
        title: 'Inventarios historial',
        to: 'cyclics-history',
      },
      {
        title: 'Cierre de Inventario',
        to: 'cyclics-closing'
      }
    ]
  }
]
