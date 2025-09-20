# 🛡️ Warden Validator Resources

A curated index of validator and infrastructure resources for the **Warden Protocol**, focused on Barra testnet validator nodes and supporting tooling.

## 🌐 Overview

This repository powers the **Validator Resources** section on the [Warden Services Page](https://cumulo.pro/services/warden/resources), displaying detailed, structured data about validator infrastructure and endpoints.

The frontend consumes a single JSON file:

- [`validators.json`](https://github.com/Cumulo-pro/Cumulo-Front-Chain/blob/main/warden/barra/validators.json): Validator node endpoints and monitoring tools.

Each entry includes public validator data such as name, RPC endpoints, WebSocket URLs, Prometheus metrics, Grafana dashboards, GitHub repos, and monitoring tools.

## ⚙️ How It Works

The data is loaded dynamically by the Cumulo services frontend, offering a clean and searchable UI to explore validator endpoints and monitoring tools.

All resources can be updated via Pull Request, either by contributors or the validators themselves.  
This ensures timely and decentralized updates to node data across the Warden ecosystem.

## 🛠️ Contributing

To add or update validator entries:

1. Fork the repo.  
2. Edit [`validators.json`](https://github.com/Cumulo-pro/Cumulo-Front-Chain/blob/main/warden/barra/validators.json).  
3. Submit a Pull Request with a clear summary of your additions or changes.  

> Make sure your JSON is valid. You can use [JSONLint](https://jsonlint.com) to verify before committing.

## 📄 JSON Structure Example

```json
{
  "name": "Cumulo",
  "x": "Cumulo_pro",
  "webservices": "https://cumulo.pro/services/warden_barra",
  "validatorlogo": "cumulo.jpg",
  "rpc": "https://rpc.barra.cumulo.com.es",
  "websocket": "wss://ws.barra.cumulo.com.es",
  "tools_url": {
    "Node Monitoring": "https://github.com/Cumulo-pro/warden_tools/blob/main/README.md",
    "Grafana Dashboard": "https://grafana.cumulo.com.es/d/warden-barra"
  },
  "content": {
    "Guide to Set Up a Warden Full Node": "https://cumulo.pro/services/warden/install",
    "Guide to Create a Validator on Barra": "https://cumulo.pro/services/warden/validator",
    "Validator JSON Example": "https://github.com/Cumulo-pro/Cumulo-Front-Chain/blob/main/warden/barra/validators.json"
  }
}
```
