# 🛰️ check_rpc — Multi-chain RPC Monitoring Tool
check_rpc is a decentralized monitoring system designed to evaluate the availability, synchronization, and responsiveness of public blockchain RPC endpoints across multiple networks.

It is designed for validators, infrastructure providers, and ecosystem maintainers who need trustable, transparent, and real-time observability over the nodes that serve JSON-RPC data.

📌 Purpose
The tool was built to offer:

- A consistent and reproducible methodology to test the health of JSON-RPC nodes.
- A multi-chain perspective, aggregating results for dozens of validators per chain.
- A live web dashboard and API that shows clear, color-coded statuses and metrics.

This provides visibility not only into the availability of endpoints, but also into block synchronization, latency performance, and node configuration consistency.

📊 What Does It Measure?
Each node is tested using a real status JSON-RPC call. This is a standard method supported by Tendermint-based and Cosmos SDK chains, and provides:

- Block height: current latest block served by the node.
- Indexing status: whether the node supports transaction indexing.
- Sync state: whether the node is fully synced with the network.
- Latency:
  - Per-region latency: round-trip time from each regional server (e.g. US, EU, UK).
  - Average latency: computed from valid regional latencies.
- Node ID: unique identifier of the node (from Tendermint).
- Software version: of the binary or daemon running.
- Moniker: human-readable alias of the node (if set).
- Validator status: inferred from the node’s voting power or inclusion in validator sets.

The result is a uniform profile of each node tested.

🔁 How It Works (At a High Level)
- A hosted `chains.json` file defines which chains to test and where to fetch lists of RPC endpoints.
- Each endpoint is queried concurrently using JSON-RPC with strict timeout control.
- A per-chain aggregation summarizes:
  - Node-by-node statuses
  - Per-region latency measurements and average
  - Availability and sync status
- The results are served as JSON (`/check-rpcs`) and rendered in a responsive frontend dashboard.
- The frontend includes sortable columns for live analysis and comparison.
- The system is designed to run continuously and autonomously, refreshing the data every 5 minutes and ignoring nodes that return invalid or malformed responses.

🔐 Why It's Trustworthy
✅ Uses real RPC queries — no synthetic checks or hardcoded assumptions.  
🧪 Applies the same logic across all endpoints for consistency.  
📉 Excludes nodes with unreachable or invalid RPCs from metrics (no skew).  
📂 All chains and endpoints are publicly listed in GitHub (auditable).  
🚫 No proprietary APIs — only open, documented standards like Tendermint `/status`.  
🖥 Server locations are fixed and transparent (latency is geographically relative, but consistent).  
💡 Data is kept read-only and is not modified or filtered beyond validation.  

👥 Intended Audience
- Validator operators looking to audit RPC uptime across competitors.
- Blockchain core teams monitoring network health.
- Delegators evaluating infrastructure quality.
- Bridge operators or indexers verifying endpoint reliability.

## 🌐 Distributed Latency Measurement

Unlike traditional uptime checkers that test from a single server, **check_rpc** uses a distributed aggregation system to provide **geographically-aware performance insights**. This makes latency measurements **realistic, transparent, and representative** of what users experience globally.

---

### 📍 How Regional Latency Is Measured

The system is composed of multiple regional agents, each executing the same `check_rpc` script from different data centers:

- 🇺🇸 **United States**
- 🇪🇺 **Central Europe**
- 🇬🇧 **United Kingdom**

Each agent performs a real JSON-RPC call (typically `GET /status`) against each endpoint listed. Unlike synthetic ping tests, these are **live application-level RPC queries**, returning:

- Full block height and node status
- HTTP response latency
- Node metadata (ID, moniker, version)
- Indexing and synchronization flags

---

### 🧠 Aggregator Logic

A central service (`aggregator.js`) collects the JSON outputs from all active regional agents. It merges and processes the results using the following logic:

1. **Match endpoints** across all sources by `rpc` URL.
2. **Store each regional latency sample** under `latencySamples`.
3. **Compute `averageLatency`** from all valid samples.
4. **Expose** both regional and average metrics through `/aggregate-rpcs`.

This allows every RPC node to be evaluated **per location** and across **regions**, offering detailed observability.

### 💡 Why It Matters

- 🔬 **Latency is relative** — this model captures true RPC responsiveness per region.
- 🛠 **Validators and node providers** can compare how their infrastructure performs globally.
- 🌎 **Delegators, bridges, and explorers** can choose RPCs with optimal proximity.
- 📉 **Detect asymmetric networking issues** or overloaded regional nodes.

---

### 🔐 Trust & Reproducibility

| Property                  | Description                                                                 |
|---------------------------|-----------------------------------------------------------------------------|
| ✅ Real Queries           | Uses standard JSON-RPC `/status` calls — no fakes or mocks                  |
| 🧪 Uniform Evaluation     | All endpoints are tested under identical logic                              |
| 🌍 Regional Transparency  | Server regions are fixed and documented                                     |
| 🚫 Invalid Nodes Skipped  | Errors and non-responders are excluded from latency averages                |
| 📁 Open Definitions       | Chain & endpoint definitions are public (e.g. `chains.json` on GitHub)      |

---

With **regional probing** and **smart aggregation**, `check_rpc` becomes a powerful and transparent monitoring layer for blockchain RPC infrastructure — **trusted by validators, explorers, and protocol teams alike**.

---

### 📊 Example Output (Per RPC Node)

```json
{
  "name": "Polkachu",
  "rpc": "https://xrp-testnet-rpc.polkachu.com",
  "status": "Synced",
  "block": "1487768",
  "indexing": "Indexed",
  "moniker": "hello-xrp-testrelay",
  "node_id": "737b3b337173cc00830a43314cff8d6a1ae8b046",
  "version": "0.38.17",
  "isValidator": false,
  "latencySamples": [
    { "location": "US", "ms": 347 },
    { "location": "EU", "ms": 205 }
  ],
  "averageLatency": 276,
  "latencyByRegion": [
    { "location": "US", "ms": 347 },
    { "location": "EU", "ms": 205 }
  ]
}
```

📜 License
MIT © [Cumulo Pro](https://cumulo.pro)
