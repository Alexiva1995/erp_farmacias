using System;
using System.IO;
using System.Net.Sockets;
using System.Threading;


namespace DemoTCPIP_PHP
{
    public class ConexionTcp
    {
        public Socket TcpCliente;
        public StreamReader StreamReader;
        public StreamWriter StreamWriter;
        public Thread ReadThread;

        public delegate void DataCarrier(string data);
        public event DataCarrier OnDataRecieved;

        public delegate void DisconnectNotify();
        public event DisconnectNotify OnDisconnect;

        public delegate void ErrorCarrier(Exception e);
        public event ErrorCarrier OnError;

        public ConexionTcp(Socket client)
        {
            var ns = new NetworkStream(client);
            StreamReader = new StreamReader(ns);
            StreamWriter = new StreamWriter(ns);
            TcpCliente = client;
        }
        private void EscribirMsj(string mensaje)
        {
            try
            {
                StreamWriter.Write(mensaje + "\0");
                StreamWriter.Flush();
            }
            catch (Exception e)
            {
                if (OnError != null)
                    OnError(e);
            }
        }
        public void EnviarPaquete(Paquete paquete)
        {
            EscribirMsj(paquete);
        }
    }
}
