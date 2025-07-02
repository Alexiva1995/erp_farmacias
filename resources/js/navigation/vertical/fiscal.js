export default [
  {
    title: 'Fiscal',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'barcode'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Listado de Histórico',
        to: 'lot-list',
      },
    ],
  },
]
