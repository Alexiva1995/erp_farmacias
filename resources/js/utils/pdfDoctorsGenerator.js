import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { applyPremiumHeader, applyFooter } from './pdfBaseStyles';

export default function pdfDoctorsGenerator(data) {
    const doc = new jsPDF();
    
    // Aplicar Encabezado Premium
    const startY = applyPremiumHeader(doc, 'Reporte de Doctores');
    
    // Configuración de la tabla
    const headers = [
        [
            { content: '#', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Identificación', styles: { fontStyle: 'bold' } },
            { content: 'Nombre', styles: { fontStyle: 'bold' } },
            { content: 'Dirección', styles: { fontStyle: 'bold' } },
            { content: 'Fecha Registro', styles: { fontStyle: 'bold' } }
        ]
    ];
    
    const rows = data.map((doctor, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        doctor.identification,
        doctor.name,
        doctor.address || 'N/A',
        { 
            content: new Date(doctor.created_at).toLocaleDateString('es-ES'),
            styles: { halign: 'center' }
        }
    ]);
    
    // Generar tabla
    autoTable(doc, {
        startY: startY,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [240, 240, 240], // Gris claro estilo nómina
            textColor: 0,
            halign: 'center',
            fontStyle: 'bold'
        },
        styles: {
            fontSize: 8,
            cellPadding: 3,
            overflow: 'linebreak'
        },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },
            1: { cellWidth: 35 },
            2: { cellWidth: 45 },
            3: { cellWidth: 'auto' },
            4: { cellWidth: 30, halign: 'center' }
        },
        margin: { left: 15, right: 15 }
    });
    
    // Aplicar Pie de Página
    applyFooter(doc);
    
    // Guardar PDF
    const todayStr = new Date().toISOString().slice(0, 10);
    doc.save(`Doctores_${todayStr}.pdf`);
}
