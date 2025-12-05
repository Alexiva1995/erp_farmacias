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
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Prestaciones Sociales',
        to: 'rrhh-social-benefits',
        action: 'manage',
        subject: 'admin',
      },
      {
        title: 'Renuncias',
        to: 'rrhh-resignations',
      },
       {
        title: 'Productividad',
        action: 'manage',
        subject: 'admin',
        children: [
          {
            title: 'Limpieza',
            to: 'productivity-cleaning',
          },
          {
            title: 'Laboratorios Empleados',
            to: 'productivity-laboratory',
          },
          {
         title: 'Productos Empleados',
          to: 'productivity-product',
          },
          {
          title: 'Tareas Empleados',
            to: 'productivity-employee-task',
        },
        ],
      }
    ],
  },
]
