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
        title: 'Caducidad',
        to: 'inventory-expirations',
      },
    ],
  },
  
]
