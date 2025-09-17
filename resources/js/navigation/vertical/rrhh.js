export default [
  {
    title: 'RRHH',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'users-viewfinder'],
        size: 'sm',
      },
    },
    children: [
      {
        title: 'Empleados',
        to: 'rrhh-employees',
      },
    ],
  },
]
