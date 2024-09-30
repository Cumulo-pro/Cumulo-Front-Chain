# Steps to Implement WebSocket Proxy with Node.js

## 1. Install Node.js on your Server  

If you don't have Node.js installed on your server, install it with the following commands
```bash
sudo apt update  
sudo apt install -y nodejs npm
```

Verify the installation by running:
```bash
node -v
npm -v
```

## 2. Setting up a WebSocket Proxy with Node.js
```bash
mkdir proxyServer
cd proxyServer
sudo vi proxyServer.js
```

Copy the following code inside proxyServer.js:  
```bash
// proxyServer.js
const WebSocket = require('ws');
const express = require('express');
const cors = require('cors');
const app = express();
const port = 3000; // Puedes cambiar el puerto si es necesario

// Habilitar CORS para permitir solicitudes desde tu página web
app.use(cors());
app.use(express.json());

app.post('/proxy', (req, res) => {
    const wsUrl = req.body.wsUrl; // Obtén la URL del cuerpo de la solicitud

    // Verificar si se proporcionó una URL válida
    if (!wsUrl || (!wsUrl.startsWith('ws://') && !wsUrl.startsWith('wss://'))) {
        return res.status(400).json({ error: "Invalid WebSocket URL" });
    }

    const ws = new WebSocket(wsUrl);

    ws.on('open', () => {
        const message = JSON.stringify({
            "jsonrpc": "2.0",
            "id": 1,
            "method": "eth_blockNumber", // Método EVM para obtener el número de bloque
            "params": []
        });
        ws.send(message);
    });

    ws.on('message', (data) => {
        res.json({ result: data.toString() });
        ws.close();
    });

    ws.on('error', (error) => {
        res.status(500).json({ error: error.message });
    });

    ws.on('close', () => {
        console.log('WebSocket connection closed.');
    });
});

app.listen(port, () => {
    console.log(`Proxy server running at http://localhost:${port}`);
});
```

Run the proxy with:  
```bash
node proxyServer.js
```

## 3. Configuring SystemD for your Node.js Proxy Server  

To keep the proxy running even if you close the terminal, you can use the following steeps:

### A. Create a SystemD Service File:  
Run the following command to create a service file for your Node.js application:  
```bash
sudo vi /etc/systemd/system/proxyServer.service
```

### B. Add Configuration to the Service File:  
Copy and paste the following configuration into the file. Be sure to adjust the paths as needed:  
```bash
[Unit]
Description=Node.js Proxy Server for WebSocket
After=network.target

[Service]
ExecStart=/usr/bin/node /path/to/your/proxyServer.js
WorkingDirectory=/path/to/your/directory/proxyserver
Restart=always
User=<your user>
Group=arixScum8
Environment=PATH=/usr/bin:/usr/local/bin
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target

```



