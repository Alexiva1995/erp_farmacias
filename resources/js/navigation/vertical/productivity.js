export default [
  {
    title: 'Productividad',
    icon: {
      is: 'font-awesome-icon', 
      props: {
        icon: ['fas', 'cog'],
        size: 'sm',
      },
    },
     action: 'manage',
    subject: 'productividad',
    children: [
      {
        title: 'Mi Limpieza',
        to: 'productivity-my-cleaning-activities',
      },
      {
        title: 'Laboratorios',
        to: 'productivity-laboratory',
      },
      {
        title: 'Productos',
        to: 'productivity-product',
      },
      {
        title: 'Tareas',
        to: 'productivity-employee-task',
      },
      {
        title: 'Revision de Actividades de Limpieza',
        to: 'productivity-supervisor-cleaning-activities',
        action: 'manage',
        subject: 'supervisor',
      },
      {
        title: 'Empleado del Mes',
        to: 'productivity-employee-month',
      },
    ],
  }, 
]
