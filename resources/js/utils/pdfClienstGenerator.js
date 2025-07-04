import jsPDF from 'jspdf';
// import autoTable from 'jspdf-autotable';

export default function pdfClienstGenerator(data){
    const doc = new jsPDF();

    doc.save(`test.pdf`);
}
