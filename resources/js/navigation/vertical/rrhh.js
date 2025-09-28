export default [
  {
    title: 'RRHH',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'users'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Empleados',
        to: 'rrhh-employees',
      },
      {
        title: 'Renuncias',
        to: 'rrhh-resignations',
      },
      {
        title: 'Prestaciones Sociales',
        to: 'rrhh-social-benefits',
      },
    ],
  },
]
