export default [
  {
    title: 'Prestamos',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'boxes-stacked'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Lista de Prestamos',
        to: 'loans-list',
      },
    ],
  }, 
]
