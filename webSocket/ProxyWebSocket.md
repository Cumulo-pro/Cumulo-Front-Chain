# Steps to Implement WebSocket Proxy with Node.js
The implementation of a WebSocket Proxy with Node.js is necessary to test EVM WebSocket endpoints (WSS) from a web page for the following reasons:  

- CORS (Cross-Origin Resource Sharing) Restrictions: Modern browsers block WebSocket requests that attempt to connect directly to endpoints that do not allow cross-origin requests. Most EVM node servers do not enable CORS for WebSocket requests, preventing your web page from connecting directly to them. The proxy implemented with Node.js acts as an intermediary to bypass this restriction, allowing the web page to send requests to the proxy, which then connects to the WebSocket.  

- Connection Security: WebSockets often require secure connections (WSS), but from a browser, it’s not possible to establish this connection directly if the server is not properly configured to allow external connections. The proxy helps handle these connections securely from the backend.  

- Data Handling and Validation: The proxy can process and validate data before sending it to the EVM WebSocket and can also format and filter responses before delivering them to the web page. This provides greater control and security over the information exchanged between the frontend and backend.  

In addition, if we want to test it from a server via CLI, we can use *wscat*.  

In summary, the WebSocket Proxy acts as a secure bridge that enables the web page to effectively test EVM WSS endpoints, avoiding CORS restrictions and providing an additional layer of security and flexibility in the connection.  

# Index

  - [1. Install Node.js on your Server](#1-install-nodejs-on-your-server)
  - [2. Setting up a WebSocket Proxy with Node.js](#2-setting-up-a-websocket-proxy-with-nodejs)
  - [3. Configuring SystemD for your Node.js Proxy Server](#3-configuring-systemd-for-your-nodejs-proxy-server)
    - [a. Create a SystemD Service File](#a-create-a-systemd-service-file)
    - [b. Add Configuration to the Service File](#b-add-configuration-to-the-service-file)
    - [c. Reload SystemD to Apply Changes](#c-reload-systemd-to-apply-changes)
    - [d. Starting and Enabling the Service](#d-starting-and-enabling-the-service)
    - [e. Check Service Status](#e-check-service-status)
 - [4. Proxy testing with curl](#4-proxy-testing-with-curl)
    - [What You Should Expect](#what-you-should-expect)


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

### a. Create a SystemD Service File:  
Run the following command to create a service file for your Node.js application:  
```bash
sudo vi /etc/systemd/system/proxyServer.service
```

### b. Add Configuration to the Service File:  
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

### c. Reload SystemD to Apply Changes  
```bash
sudo systemctl daemon-reload
```

### d. Starting and Enabling the Service
Start your proxy server with:  
```bash
sudo systemctl start proxyServer
```
And make sure it starts automatically at system startup:  
```bash
sudo systemctl enable proxyServer
```

### e. Check Service Status  
You can verify that the service is working correctly with:  
```bash
sudo systemctl status proxyServer
```
Check your proxy logs with:  
```bash
journalctl -u proxyServer -f
```

## 4. Proxy testing with curl  
Use curl to send an HTTP POST request to the proxy and see if it responds correctly. Run the following command from your server terminal:  
```bash
curl -X POST http://localhost:3000/proxy \
-H "Content-Type: application/json" \
-d '{"wsUrl":"wss://story-testnet-wss.cumulo.me"}'
```

### What You Should Expect  
If the connection is successful, you will receive a JSON response similar to this:
```bash
{“result”:“{\”jsonrpc\“:\”2.0\“,\”id\“:1,\”result\“:\”0x123456\“}”}
Where 0x123456 would be the block in hexadecimal format returned by the WebSocket.
```  
If there is a connection error, you will receive an error message like:  
```bash
{“error”: “Error message indicating the problem”}
```  
This curl command will allow you to verify if your proxy is able to connect to the story-testnet-wss.cumulo.me WebSocket and if it is working as expected.

![image](https://github.com/user-attachments/assets/9120f34a-a273-4e85-ab10-da6ccc6812fd)


