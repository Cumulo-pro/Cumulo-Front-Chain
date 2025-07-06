# 🧩 Celestia Validator Resources

A curated index of validator resources for the Celestia ecosystem, designed to improve discoverability, accessibility, and collaboration across mainnet and testnet participants.

## 🌐 Overview

This repository powers the **Validator Resources** section on the [Celestia Services Page](https://cumulo.pro/services/celestia), displaying detailed, structured data about validators on both mainnet and testnet. The frontend consumes two JSON files:

- `validators.json`: Mainnet validator resources  
- `validators_testnet.json`: Testnet validator resources

Each entry includes public validator data such as name, moniker, RPC endpoints, APIs, snapshots, explorer links, GitHub repos, and other relevant infrastructure tools.

## ⚙️ How It Works

The data is loaded directly by the services frontend, offering a fast and indexed UI that allows users to search and filter validators and their resources.

All content can be updated via Pull Request, either by contributors or the validators themselves. This makes it easy for any validator to publish or update their own endpoints and tooling with a simple JSON edit.

## 🛠️ Contributing

To propose changes or add your validator’s resources:

1. Fork the repo.
2. Edit the appropriate file (`validators.json` or `validators_testnet.json`).
3. Submit a Pull Request with a clear description of your changes.

> Make sure your JSON is valid. You can use tools like [JSONLint](https://jsonlint.com) to validate before submitting.

## 📄 JSON Structure Example

```json
{
  "name": "Cumulo Pro",
  "moniker": "Cumulo",
  "rpc": "https://rpc.celestia.cumulo.com.es",
  "api": "https://api.celestia.cumulo.com.es",
  "explorer": "https://explorer.celestia.org/validator/celestiavaloper...",
  "snap_url": "https://snapshots.cumulo.com.es/celestia/latest.tar.lz4",
}
