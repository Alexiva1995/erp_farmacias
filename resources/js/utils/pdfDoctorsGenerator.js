import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export default function pdfDoctorsGenerator(data) {
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    
    // 1. Logo (opcional - elimina si no necesitas)
    try {
        const logoSrc = '/images/logoEmpresa.png'; // Ajusta la ruta
        const logoWidth = 80;
        const logoHeight = 30;
        doc.addImage(logoSrc, 'PNG', (pageWidth - logoWidth) / 2, 15, logoWidth, logoHeight);
    } catch (error) {
        console.warn("No se pudo cargar el logo", error);
    }
    
    // 2. Encabezado
    const headerY = 50;
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('REPORTE DE DOCTORES', pageWidth / 2, headerY, { align: 'center' });
    
    // 3. Fecha y detalles
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    const today = new Date();
    const dateStr = `Generado el: ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    doc.text(dateStr, pageWidth - 15, headerY + 10, { align: 'right' });
    
    // 4. Configuración de la tabla
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
    
    // 5. Generar tabla
    autoTable(doc, {
        startY: headerY + 20,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [41, 128, 185], // Azul corporativo
            textColor: 255,
            halign: 'center'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245] // Gris claro para filas alternas
        },
        styles: {
            fontSize: 9,
            cellPadding: 3,
            overflow: 'linebreak'
        },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },  // #
            1: { cellWidth: 30 },                    // Identificación
            2: { cellWidth: 40 },                    // Nombre
            3: { cellWidth: 50 },                    // Dirección
            4: { cellWidth: 25, halign: 'center' }  // Fecha Registro
        },
        margin: { left: 15, right: 15 }
    });
    
    // 6. Pie de página
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(10);
        doc.text(
            `Página ${i} de ${pageCount}`,
            pageWidth / 2,
            doc.internal.pageSize.height - 10,
            { align: 'center' }
        );
    }
    
    // 7. Guardar PDF
    const fileName = `Doctores_${today.toISOString().slice(0, 10)}.pdf`;
    doc.save(fileName);
}
