# 🛰️ check_rpc — Multi-chain RPC Monitoring Tool

**check_rpc** is a decentralized monitoring system designed to evaluate the availability, synchronization, and responsiveness of public blockchain RPC endpoints across multiple networks.

It is designed for validators, infrastructure providers, and ecosystem maintainers who need **trustable, transparent, and real-time observability** over the nodes that serve JSON-RPC data.

---

## 📌 Purpose

The tool was built to offer:

- A **consistent and reproducible methodology** to test the health of JSON-RPC nodes.
- A **multi-chain perspective**, aggregating results for dozens of validators per chain.
- A **live web dashboard and API** that shows clear, color-coded statuses and metrics.

This provides visibility not only into the availability of endpoints, but also into **block synchronization, latency performance, and node configuration consistency**.

---

## 📊 What Does It Measure?

Each node is tested using a real `status` JSON-RPC call. This is a standard method supported by Tendermint-based and Cosmos SDK chains, and provides:

- **Block height**: current latest block served by the node.
- **Indexing status**: whether the node supports transaction indexing.
- **Sync state**: whether the node is fully synced with the network.
- **Latency**: round-trip time of the JSON-RPC call (in milliseconds).
- **Node ID**: unique identifier of the node (from Tendermint).
- **Software version**: of the binary or daemon running.
- **Moniker**: human-readable alias of the node (if set).
- **Validator status**: inferred from the node’s voting power or inclusion in validator sets.

The result is a **uniform profile** of each node tested.

---

## 🔁 How It Works (At a High Level)

1. A hosted `chains.json` file defines which chains to test and where to fetch lists of RPC endpoints.
2. Each endpoint is queried concurrently using JSON-RPC with strict timeout control.
3. A per-chain aggregation summarizes:
   - Node-by-node statuses
   - Averages (like latency)
   - Region information (if configured)
4. The results are served as JSON (`/check-rpcs`) and rendered in a responsive frontend dashboard.
5. The frontend includes **sortable columns** for live analysis and comparison.

The system is designed to run **continuously and autonomously**, refreshing the data every 5 minutes and ignoring nodes that return invalid or malformed responses.

---

## 🔐 Why It's Trustworthy

- ✅ Uses **real RPC queries** — no synthetic checks or hardcoded assumptions.
- 🧪 Applies the same logic across all endpoints for consistency.
- 📉 Excludes nodes with unreachable or invalid RPCs from metrics (no skew).
- 📂 All chains and endpoints are **publicly listed** in GitHub (auditable).
- 🚫 No proprietary APIs — only open, documented standards like Tendermint `/status`.
- 🖥 Server location is fixed and transparent (latency is geographically relative, but consistent).
- 💡 Data is kept **read-only** and is not modified or filtered beyond validation.

---

## 👥 Intended Audience

- Validator operators looking to audit RPC uptime across competitors.
- Blockchain core teams monitoring network health.
- Delegators evaluating infrastructure quality.
- Bridge operators or indexers verifying endpoint reliability.

---

## 📈 Example Output (JSON)

```json
{
  "XRPL EVM Testnet": [
    {
      "name": "Polkachu",
      "rpc": "https://xrp-testnet-rpc.polkachu.com",
      "status": "Synced",
      "block": "1481390",
      "indexing": "Indexed",
      "moniker": "hello-xrp-testrelay",
      "node_id": "737b3b33...",
      "version": "0.38.17",
      "isValidator": false,
      "latency": 353,
      "detail": "OK"
    }
  ]
}```

## 📜 License

MIT © [Cumulo Pro](https://cumulo.pro)
