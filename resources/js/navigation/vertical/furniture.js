export default [
  {
    title: 'Mobiliario',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'boxes-stacked'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Lista de Mobiliario',
        to: 'furnitures-list',
      },
    ],
  }, 
]
