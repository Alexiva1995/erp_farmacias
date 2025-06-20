export default [
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
]
