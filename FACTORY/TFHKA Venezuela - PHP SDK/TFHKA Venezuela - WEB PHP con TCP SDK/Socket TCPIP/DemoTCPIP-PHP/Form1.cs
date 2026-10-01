using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Text;
using System.Windows.Forms;
using TfhkaNet.IF.VE;
using TfhkaNet.IF;
using System.Net.Sockets;
using System.Net;
using System.Threading;
using System.IO;
using System.Net.NetworkInformation;

namespace DemoTCPIP_PHP
{
    public partial class Form1 : Form
    {
        Tfhka impresora;
        PrinterStatus status;
        S1PrinterData S1Data;
        S2PrinterData S2Data;
        S3PrinterData S3Data;
        S4PrinterData S4Data;
        S5PrinterData S5Data;
        ReportData reportData;
        string puerto = "";
        List<string> puertos = new List<string>();
        public delegate void ClientCarrier(ConexionTcp conexionTcp);
        public event ClientCarrier OnClientConnected;
        public event ClientCarrier OnClientDisconnected;
        public delegate void DataRecieved(ConexionTcp conexionTcp, string data);
        public event DataRecieved OnDataRecieved;
        private Socket _tcpListener;
        private Thread _acceptThread;
        private List<ConexionTcp> connectedClients = new List<ConexionTcp>();
        IPHostEntry _ipHostInfo = Dns.GetHostEntry(Dns.GetHostName());
        IPEndPoint ipEnd;

        public Form1()
        {
            InitializeComponent();
            impresora = new Tfhka();
            impresora.SendCmdRetryAttempts = 2;
            pictureBox1.Visible = true;
        }

        private void mostrarStatus()
        {
            toolStripLabel1.Text = "";
            status = impresora.GetPrinterStatus();
            toolStripLabel1.Text = "Status: " + status.PrinterStatusCode.ToString() + " , " + "Error: " + status.PrinterErrorCode.ToString();
        }

        private void statusYErrorToolStripMenuItem_Click(object sender, EventArgs e)
        {
            mostrarStatus();
        }

        private void conectarConImpresora(string puerto)
        {
            if (puerto.Length > 3)
            {
                if (impresora.OpenFpCtrl(puerto))
                {
                    toolStripDropDownButton1.Enabled = true;
                    btnConectar.Enabled = true;
                    btnConectar.Text = "Desconectar";
                    btnTcpOnOff.Enabled = true;
                    obtenerDireccionesIp();
                    label2.Text = "Conectada";
                    label2.BackColor = Color.LightGreen;
                    cmbDireccionIp.Enabled = true;
                    nudPuertoServicio.Enabled = true;
                }
                else
                {
                    toolStripLabel1.Text = "Error abriendo el Puerto: " + puerto;
                }
            }
            else
            {
                toolStripLabel1.Text = "No se dectectaron puertos válidos";
            }
        }

        private void detectarPuerto()
        {
            foreach (string port1 in System.IO.Ports.SerialPort.GetPortNames())
            {
                puertos.Add(port1);
            }
            puertos.Sort();

            foreach (string port2 in puertos)
            {
                try
                {
                    if (impresora.OpenFpCtrl(port2))
                    {
                        if (impresora.CheckFPrinter())
                        {
                            puerto = port2;
                            toolStripLabel1.Text = "Impresora dectecta en: " + port2;
                            impresora.CloseFpCtrl();
                            break;
                        }
                        else
                        {
                            toolStripLabel1.Text = port2 + "no contiene impresora";
                        }
                        impresora.CloseFpCtrl();
                    }
                }
                catch (Exception a)
                {
                    MessageBox.Show("Error detectando los pruertos:\n" + a.Message );
                }
            }

            this.Refresh();
            
        }

        private void Form1_Load(object sender, EventArgs e)
        {
            OnDataRecieved += MensajeRecibido;
            OnClientConnected += ConexionRecibida;
            OnClientDisconnected += ConexionCerrada;
            
        }

        private void EscucharClientes(string direccion, int port)
        {
            try
            {
                ipEnd = new IPEndPoint(IPAddress.Parse(direccion), port);
                _tcpListener = new Socket(AddressFamily.InterNetwork, SocketType.Stream, ProtocolType.Tcp);
                _tcpListener.Bind(ipEnd);
                MessageBox.Show(String.Format("Socket Bind on: {0}", ipEnd.ToString()));
                _tcpListener.Listen(500);
                _acceptThread = new Thread(AceptarClientes);
                _acceptThread.Start();
                pictureBox1.Visible = false;
                pictureBox2.Visible = true;
            }
            catch (Exception e)
            {
                MessageBox.Show(e.Message.ToString());
                
            }
        }

        private void MensajeRecibido(ConexionTcp conexionTcp, string datos)
        {
            bool resp;
            Paquete msgPack;
            var paquete = new Paquete(datos);
            string comando = paquete.Comando;
            switch (comando)
            {
                case "CheckFprinter()":
                    resp = impresora.CheckFPrinter();
                    msgPack = new Paquete("Resultado", resp.ToString());
                    conexionTcp.EnviarPaquete(msgPack);
                    break;

                case "SendCmd()":
                    resp = impresora.SendCmd(paquete.Contenido);
                    msgPack = new Paquete("Resultado", resp.ToString());
                    conexionTcp.EnviarPaquete(msgPack);
                    break;

                case "ReadFpStatus()":
                    status = impresora.GetPrinterStatus();
                    msgPack = new Paquete("Resultado", status.PrinterStatusCode.ToString() + "|" + status.PrinterStatusDescription.ToString() + "|" + status.PrinterErrorCode.ToString() + "|" + status.PrinterErrorDescription.ToString());
                    conexionTcp.EnviarPaquete(msgPack);
                    break;

                case "UploadReport()":
                    switch(paquete.Contenido){
                        case "U0X":
                            reportData = impresora.GetXReport();
                            msgPack = new Paquete("Resultado", reportData.NumberOfLastZReport.ToString() + "|" + reportData.ZReportDate.Date.ToShortDateString() +"|"
                                + reportData.ZReportDate.TimeOfDay.ToString() + "|" + reportData.NumberOfLastInvoice.ToString() + "|" + reportData.LastInvoiceDate.Date.ToShortDateString()
                                + "|" + reportData.LastInvoiceDate.TimeOfDay.ToString() + reportData.NumberOfLastCreditNote.ToString() +"|" + reportData.NumberOfLastDebitNote.ToString()
                                + "|" + reportData.NumberOfLastNonFiscal.ToString() + "|" + reportData.FreeSalesTax.ToString() + "|" + reportData.GeneralRate1Sale.ToString() 
                                + "|" + reportData.GeneralRate1Tax.ToString() + "|" + reportData.ReducedRate2Sale.ToString() + "|" + reportData.ReducedRate2Tax.ToString()
                                + "|" + reportData.AdditionalRate3Sale.ToString() + "|" + reportData.AdditionalRate3Tax.ToString() + "|" + reportData.FreeTaxDebit.ToString()
                                + "|" + reportData.GeneralRateDebit.ToString() + "|" + reportData.GeneralRateTaxDebit.ToString() + "|" + reportData.ReducedRateDebit.ToString()
                                + "|" + reportData.ReducedRateTaxDebit.ToString() + "|" + reportData.AdditionalRateDebit.ToString() + "|" + reportData.AdditionalRateTaxDebit.ToString()
                                + "|" + reportData.FreeTaxDevolution.ToString() + "|" + reportData.GeneralRateDevolution.ToString() + "|" + reportData.GeneralRateTaxDevolution.ToString() 
                                + "|" + reportData.ReducedRateDevolution.ToString() + "|" + reportData.ReducedRateTaxDevolution.ToString() + "|" + reportData.AdditionalRateDevolution.ToString()
                                + "|" + reportData.AdditionalRateTaxDevolution.ToString());
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                        case "U0Z":
                            reportData = impresora.GetZReport();
                            msgPack = new Paquete("Resultado", reportData.NumberOfLastZReport.ToString() + "|" + reportData.ZReportDate.Date.ToShortDateString() + "|"
                                + reportData.ZReportDate.TimeOfDay.ToString() + "|" + reportData.NumberOfLastInvoice.ToString() + "|" + reportData.LastInvoiceDate.Date.ToShortDateString()
                                + "|" + reportData.LastInvoiceDate.TimeOfDay.ToString() + reportData.NumberOfLastCreditNote.ToString() + "|" + reportData.NumberOfLastDebitNote.ToString()
                                + "|" + reportData.NumberOfLastNonFiscal.ToString() + "|" + reportData.FreeSalesTax.ToString() + "|" + reportData.GeneralRate1Sale.ToString()
                                + "|" + reportData.GeneralRate1Tax.ToString() + "|" + reportData.ReducedRate2Sale.ToString() + "|" + reportData.ReducedRate2Tax.ToString()
                                + "|" + reportData.AdditionalRate3Sale.ToString() + "|" + reportData.AdditionalRate3Tax.ToString() + "|" + reportData.FreeTaxDebit.ToString()
                                + "|" + reportData.GeneralRateDebit.ToString() + "|" + reportData.GeneralRateTaxDebit.ToString() + "|" + reportData.ReducedRateDebit.ToString()
                                + "|" + reportData.ReducedRateTaxDebit.ToString() + "|" + reportData.AdditionalRateDebit.ToString() + "|" + reportData.AdditionalRateTaxDebit.ToString()
                                + "|" + reportData.FreeTaxDevolution.ToString() + "|" + reportData.GeneralRateDevolution.ToString() + "|" + reportData.GeneralRateTaxDevolution.ToString()
                                + "|" + reportData.ReducedRateDevolution.ToString() + "|" + reportData.ReducedRateTaxDevolution.ToString() + "|" + reportData.AdditionalRateDevolution.ToString()
                                + "|" + reportData.AdditionalRateTaxDevolution.ToString());
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                    }
                break;

                case "UploadStatus()":
                    switch (paquete.Contenido)
                    {
                        case "S1":
                            S1Data = impresora.GetS1PrinterData();
                            msgPack = new Paquete("Resultado", S1Data.CashierNumber.ToString() + "|" + S1Data.TotalDailySales+"|"+S1Data.LastInvoiceNumber
                                +"|"+S1Data.QuantityOfInvoicesToday.ToString()+"|"+S1Data.LastDebitNoteNumber.ToString()+"|"+S1Data.QuantityOfDebitNotesToday.ToString()
                                +"|"+S1Data.LastCreditNoteNumber.ToString()+"|"+S1Data.QuantityOfCreditNotesToday.ToString()+"|"+S1Data.LastNonFiscalDocNumber.ToString()
                                +"|"+S1Data.QuantityNonFiscalDocuments.ToString()+"|"+S1Data.AuditReportsCounter.ToString()+"|"+S1Data.DailyClosureCounter.ToString()
                                +"|"+S1Data.RIF+"|"+S1Data.RegisteredMachineNumber+"|"+S1Data.CurrentPrinterDateTime.ToString("HH:mm:ss")+"|"+S1Data.CurrentPrinterDateTime.ToString("dd-MM-yy"));
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                        case "S2":
                            S2Data = impresora.GetS2PrinterData();
                            msgPack = new Paquete("Resultado", S2Data.SubTotalBases.ToString() + "|" + S2Data.SubTotalTax + "|" + S2Data.QuantityArticles
                                + "|" + S2Data.AmountPayable.ToString() + "|" + S2Data.NumberPaymentsMade.ToString() + "|" + S2Data.TypeDocument.ToString());
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                        case "S3":
                            S3Data = impresora.GetS3PrinterData();
                            msgPack = new Paquete("Resultado", S3Data.TypeTax1.ToString() + "|" + S3Data.Tax1 + "|" + S3Data.TypeTax2
                                + "|" + S3Data.Tax2 + "|" + S3Data.TypeTax3 + "|" + S3Data.Tax3
                                + "|" + S3Data.AllSystemFlags.ToString());
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                        case "S4":
                            string data = "";
                            S4Data = impresora.GetS4PrinterData();
                            foreach(double value in S4Data.AccumulatedMountsAllMeansOfPayment)
                            {
                                data += value.ToString() + "|";
                            }
                            msgPack = new Paquete("Resultado", data.Remove(data.Length-1));
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                        case "S5":
                            S5Data = impresora.GetS5PrinterData();
                            msgPack = new Paquete("Resultado", S5Data.RIF + "|" + S5Data.RegisteredMachineNumber + "|" + S5Data.AuditMemoryNumber
                                + "|" + S5Data.AuditMemoryTotalCapacity.ToString() + "|" + S5Data.AuditMemoryFreeCapacity.ToString() + "|" + S5Data.NumberRegisteredDocuments.ToString());
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                        default:
                            msgPack = new Paquete("Resultado", "Error");
                            conexionTcp.EnviarPaquete(msgPack);
                            break;
                    }
                    break;
                default:
                    msgPack = new Paquete("Resultado", "Comando desconocido");
                    conexionTcp.EnviarPaquete(msgPack);
                    break;
            }
            
        }

        private void ConexionRecibida(ConexionTcp conexionTcp)
        {
            lock (connectedClients)
                if (!connectedClients.Contains(conexionTcp))
                    connectedClients.Add(conexionTcp);
            this.Invoke(new MethodInvoker(delegate () { pictureBox3.BackColor = cGetColor(connectedClients.Count); }));
        }

        private Color cGetColor(int count)
        {
            Color myRgbColor = new Color();
            if (count == 0)
            {
                myRgbColor = Color.Transparent;
            }
            else if (count <= 6)
            {  
            myRgbColor = Color.FromArgb(50, 0, (11 / (count + 1)) * 22, 0);
            }else if(count<=16){
                myRgbColor = Color.FromArgb(100, 255, 153, 51);
            }
            else
            {
                myRgbColor = Color.FromArgb(100, 255, 0, 0);
            }
            return myRgbColor;
        }

        private void ConexionCerrada(ConexionTcp conexionTcp)
        {
            lock (connectedClients)
                if (connectedClients.Contains(conexionTcp))
                {
                    int cliIndex = connectedClients.IndexOf(conexionTcp);
                    connectedClients.RemoveAt(cliIndex);
                }
            this.Invoke(new MethodInvoker(delegate () { pictureBox3.BackColor = cGetColor(connectedClients.Count); }));
        }

        private void AceptarClientes()
        {
            do
            {
                try
                {
                    var conexion = _tcpListener.Accept();
                    var srvClient = new ConexionTcp(conexion)
                    {
                        ReadThread = new Thread(LeerDatos)
                    };
                    srvClient.ReadThread.Start(srvClient);

                    if (OnClientConnected != null)
                        OnClientConnected(srvClient);
                }
                catch (Exception e)
                {
                    MessageBox.Show(e.Message.ToString());
                }

            } while (true);
        }


        private void LeerDatos(object client)
        {
            var cli = client as ConexionTcp;
            var charBuffer = new List<int>();

            do
            {
                try
                {
                    if (cli == null)
                    {
                        break;
                    }
                    if (cli.StreamReader.EndOfStream)
                    {
                        break;
                    }
                    int charCode = cli.StreamReader.Read();
                    if (charCode == -1)
                    {
                        break;
                    }
                    if (charCode != 0)
                    {
                        charBuffer.Add(charCode);
                        continue;
                    }
                    if (OnDataRecieved != null)
                    {
                        var chars = new char[charBuffer.Count];
                        //Convert all the character codes to their representable characters
                        for (int i = 0; i < charBuffer.Count; i++)
                        {
                            chars[i] = Convert.ToChar(charBuffer[i]);
                        }
                        //Convert the character array to a string
                        var message = new string(chars);

                        //Invoke our event
                        OnDataRecieved(cli, message);
                    }
                    charBuffer.Clear();
                }
                catch (IOException)
                {
                    break;
                }
                catch (Exception e)
                {
                    MessageBox.Show(e.Message.ToString());

                    break;
                }
            } while (true);

            if (OnClientDisconnected != null)
                OnClientDisconnected(cli);
        }

        private void btnTcpOnOff_Click(object sender, EventArgs e)
        {
            if (btnTcpOnOff.Text.Contains("Iniciar"))
            {
                btnTcpOnOff.Text = "Detener";
                btnConectar.Enabled = false;
                nudPuertoServicio.Enabled = false;
                cmbDireccionIp.Enabled = false;
                this.Refresh();
                EscucharClientes(cmbDireccionIp.SelectedItem.ToString(), (int)nudPuertoServicio.Value);

            }
            else if (btnTcpOnOff.Text.Contains("Detener"))
            {
                try
                {
                    btnTcpOnOff.Text = "Iniciar";
                    btnConectar.Enabled = true;
                    nudPuertoServicio.Enabled = true;
                    cmbDireccionIp.Enabled = true;
                    CloseConnection(_tcpListener);
                    _acceptThread.Abort();

                }
                catch (Exception a)
                {
                    MessageBox.Show(a.Message);
                }
                finally
                { 
                    pictureBox1.Visible = true;
                    pictureBox2.Visible = false;
                }
            }
        }

        private void button1_Click(object sender, EventArgs e)
        {
            if (btnConectar.Text.Contains("Conectar"))
            {
                this.Cursor = Cursors.WaitCursor;
                this.Refresh();
                detectarPuerto();
                conectarConImpresora(puerto);
                btnTcpOnOff.Enabled= true;
                this.Cursor = Cursors.Default;
            }
            else if (btnConectar.Text.Contains("Desconectar"))
            {
                impresora.CloseFpCtrl();
                btnTcpOnOff.Enabled = false;
                btnConectar.Text = "Conectar";
                label2.Text = "Desconectada";
                label2.BackColor = Color.LightYellow;
                toolStripLabel1.Text = "";
                toolStripDropDownButton1.Enabled = false;
                cmbDireccionIp.Enabled = false;
                nudPuertoServicio.Enabled = false;
            }

        }

        private void Form1_FormClosing(object sender, FormClosingEventArgs e)
        {
            System.Environment.Exit(0);
        }

        void CloseConnection(Socket socket)
        {
            if (socket.Connected)
            {
                socket.Shutdown(SocketShutdown.Both);
                
            }
            socket.Close();
        }

        private void obtenerDireccionesIp()
        {
            cmbDireccionIp.Items.Clear();

            // Get a list of all network interfaces (usually one per network card, dialup, and VPN connection) 
            NetworkInterface[] networkInterfaces = NetworkInterface.GetAllNetworkInterfaces();

            foreach (NetworkInterface network in networkInterfaces)
            {
                // Read the IP configuration for each network 
                IPInterfaceProperties properties = network.GetIPProperties();

                // Each network interface may have multiple IP addresses 
                foreach (IPAddressInformation address in properties.UnicastAddresses)
                {
                    // We're only interested in IPv4 addresses for now 
                    if (address.Address.AddressFamily != AddressFamily.InterNetwork)
                        continue;

                    // Ignore loopback addresses (e.g., 127.0.0.1) 
                    if (IPAddress.IsLoopback(address.Address))
                        continue;

                    cmbDireccionIp.Items.Add(address.Address.ToString());
                    cmbDireccionIp.SelectedIndex = 0;
                }
            }

        }
    }
}
