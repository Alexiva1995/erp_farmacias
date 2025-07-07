import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export default function pdfClienstGenerator(data) {
    console.log("que paso => ",data)
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    
    // 1. Logo (opcional - elimina si no necesitas)
    // try {
    //     const logoSrc = '/images/logoEmpresa.png'; // Ajusta la ruta
    //     const logoWidth = 80;
    //     const logoHeight = 30;
    //     doc.addImage(logoSrc, 'PNG', (pageWidth - logoWidth) / 2, 15, logoWidth, logoHeight);
    // } catch (error) {
    //     console.warn("No se pudo cargar el logo", error);
    // }
    
    // 2. Encabezado
    const headerY = 10; // Ajusta según necesidad
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('REPORTE DE CLIENTES', pageWidth / 2, headerY, { align: 'center' });
    
    // 3. Información de la empresa
    // doc.setFontSize(11);
    // doc.setFont('helvetica', 'normal');
    // if (data[0]?.company) {
    //     doc.text(`Empresa: ${data[0].company.name}`, 15, headerY + 10);
    // }
    
    // 4. Fecha y detalles
    const today = new Date();
    const dateStr = `Generado el: ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    doc.text(dateStr, pageWidth - 15, headerY + 10, { align: 'right' });
    
    // 5. Configuración de la tabla
    const headers = [
        [
            { content: '#', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Identificación', styles: { fontStyle: 'bold' } },
            { content: 'Tipo', styles: { fontStyle: 'bold' } },
            { content: 'Nombre Completo', styles: { fontStyle: 'bold' } },
            { content: 'Teléfono', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Email', styles: { fontStyle: 'bold' } }
        ]
    ];
    
    const rows = data.map((client, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        client.identification,
        client.identification_type,
        `${client.name} ${client.last_name || ''}`.trim(),
        { content: client.phone || 'N/A', styles: { halign: 'center' } },
        client.email || 'N/A'
    ]);
    
    // 6. Generar tabla
    autoTable(doc, {
        startY: headerY + 20,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [41, 128, 185],
            textColor: 255,
            halign: 'center'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },
        styles: {
            fontSize: 9,
            cellPadding: 3,
            overflow: 'linebreak'
        },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },
            1: { cellWidth: 30 },
            2: { cellWidth: 20 },
            3: { cellWidth: 45 },
            4: { cellWidth: 25, halign: 'center' },
            5: { cellWidth: 60 }
        },
        margin: { left: 15, right: 15 }
    });
    
    // 7. Pie de página
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
    
    // 8. Guardar PDF
    const fileName = `Clientes_Reporte_${today.toISOString().slice(0, 10)}.pdf`;
    doc.save(fileName);
}
