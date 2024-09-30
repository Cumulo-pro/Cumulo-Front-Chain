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
